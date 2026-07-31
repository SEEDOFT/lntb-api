<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceActivation;
use App\Models\DeviceActivationAudit;
use App\Models\DeviceStatus;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeviceActivationService
{
    private const string INVALID_CODE = 'INVALID_DEVICE_ACTIVATION';

    /**
     * @return array{activation: DeviceActivation, payload: string}
     */
    public function prepare(
        string $serialNumber,
        string $customerLogin,
        string $operatorIdentifier,
    ): array {
        $token = $this->generateToken();

        return DB::transaction(function () use (
            $serialNumber,
            $customerLogin,
            $operatorIdentifier,
            $token,
        ): array {
            $device = Device::query()
                ->where('serial_number', trim($serialNumber))
                ->lockForUpdate()
                ->first();
            if ($device === null) {
                throw new BusinessException('DEVICE_NOT_FOUND', 'The device was not found.', 404);
            }

            $this->ensureAvailable($device);
            $customer = $this->resolveCustomer($customerLogin);

            $previous = DeviceActivation::query()
                ->where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get();

            foreach ($previous as $activation) {
                $activation->forceFill(['revoked_at' => now()])->save();
                $this->audit($activation, 'revoked', null, $operatorIdentifier);
            }

            $activation = DeviceActivation::query()->create([
                'device_id' => $device->id,
                'intended_user_id' => $customer->id,
                'public_reference' => (string) Str::uuid(),
                'token_hash' => hash('sha256', $token),
                'prepared_by_identifier' => $operatorIdentifier,
                'failed_attempts' => 0,
                'issued_at' => now(),
                'expires_at' => now()->addDays(max(1, (int) config('device_activation.ttl_days'))),
            ]);
            $this->audit($activation, 'prepared', $customer->id, $operatorIdentifier);

            return [
                'activation' => $activation,
                'payload' => json_encode([
                    'v' => 1,
                    'device_ref' => $activation->public_reference,
                    'activation_token' => $token,
                    'device_name' => $device->name,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ];
        });
    }

    public function activate(User $user, array $data): Device
    {
        $device = DB::transaction(function () use ($user, $data): ?Device {
            $activation = DeviceActivation::query()
                ->where('public_reference', $data['device_ref'])
                ->lockForUpdate()
                ->first();

            if ($activation === null) {
                $this->audit(null, 'rejected', $user->id, 'user:'.$user->id);

                return null;
            }

            $device = Device::query()->lockForUpdate()->find($activation->device_id);
            $valid = $device !== null
                && $activation->intended_user_id === $user->id
                && $activation->revoked_at === null
                && $activation->consumed_at === null
                && $activation->expires_at->isFuture()
                && $activation->failed_attempts < max(1, (int) config('device_activation.max_failed_attempts'))
                && hash_equals($activation->token_hash, hash('sha256', $data['activation_token']))
                && $device->owner_user_id === null
                && $device->device_status_id === $this->statusId(DeviceStatus::AVAILABLE);

            if (! $valid) {
                $activation->increment('failed_attempts');
                $this->audit($activation, 'rejected', $user->id, 'user:'.$user->id);

                return null;
            }

            $device->forceFill([
                'owner_user_id' => $user->id,
                'device_status_id' => $this->statusId(DeviceStatus::ACTIVE),
                'name' => $data['name'] ?? $device->name,
                'claimed_at' => now(),
                'claim_code_used_at' => now(),
            ])->save();
            $activation->forceFill(['consumed_at' => now()])->save();
            $this->audit($activation, 'activated', $user->id, 'user:'.$user->id);

            return $device->load(['type', 'status']);
        });

        if ($device === null) {
            throw new BusinessException(
                self::INVALID_CODE,
                'The device activation could not be completed.',
                422,
            );
        }

        return $device;
    }

    private function ensureAvailable(Device $device): void
    {
        if (
            $device->owner_user_id !== null
            || $device->device_status_id !== $this->statusId(DeviceStatus::AVAILABLE)
        ) {
            throw new BusinessException(
                'DEVICE_NOT_AVAILABLE',
                'The device is not available for activation.',
            );
        }
    }

    private function resolveCustomer(string $login): User
    {
        $normalized = trim($login);
        $query = User::query()->where('user_status_id', $this->userStatusId(UserStatus::ACTIVE));

        if (str_contains($normalized, '@')) {
            $users = $query->where('email', strtolower($normalized))->get();
        } elseif (str_starts_with($normalized, '+')) {
            $digits = preg_replace('/\D/', '', $normalized) ?? '';
            $users = $query->get()->filter(
                fn (User $user): bool => ltrim((string) $user->country_code, '+').$user->phone_number === $digits,
            );
        } else {
            $digits = preg_replace('/\D/', '', $normalized) ?? '';
            $users = $query->where('phone_number', $digits)->get();
        }

        if ($users->count() !== 1) {
            throw new BusinessException(
                'CUSTOMER_NOT_FOUND',
                'One active customer account could not be resolved.',
                404,
            );
        }

        return $users->firstOrFail();
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function statusId(string $code): int
    {
        return (int) DeviceStatus::query()->where('code', $code)->valueOrFail('id');
    }

    private function userStatusId(string $code): int
    {
        return (int) UserStatus::query()->where('code', $code)->valueOrFail('id');
    }

    private function audit(
        ?DeviceActivation $activation,
        string $eventCode,
        ?int $userId,
        ?string $actorIdentifier,
    ): void {
        DeviceActivationAudit::query()->create([
            'device_activation_id' => $activation?->id,
            'device_id' => $activation?->device_id,
            'user_id' => $userId,
            'event_code' => $eventCode,
            'actor_identifier' => $actorIdentifier,
            'occurred_at' => now(),
        ]);
    }
}
