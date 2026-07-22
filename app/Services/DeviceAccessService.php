<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceUserAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeviceAccessService
{
    public function grant(Device $device, User $owner, string $login): DeviceUserAccess
    {
        return DB::transaction(function () use ($device, $owner, $login): DeviceUserAccess {
            Device::query()->lockForUpdate()->findOrFail($device->id);

            $field = str_contains($login, '@') ? 'email' : 'phone_number';
            $grantee = User::query()->where($field, $login)->first();
            if ($grantee === null) {
                throw new BusinessException('USER_NOT_FOUND', 'The registered user was not found.', 404);
            }
            if ($grantee->id === $owner->id) {
                throw new BusinessException('OWNER_CANNOT_BE_GRANTED', 'The device owner cannot be granted access.');
            }

            $existing = DeviceUserAccess::query()
                ->where('device_id', $device->id)
                ->where('user_id', $grantee->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->load('status');
                if ($existing->status->code === DeviceAccessStatus::ACTIVE) {
                    throw new BusinessException('ACCESS_ALREADY_EXISTS', 'The user already has access to this device.');
                }
            }

            $this->ensureCapacity($device->id);

            $values = [
                'granted_by_user_id' => $owner->id,
                'device_access_status_id' => DeviceAccessStatus::ID_ACTIVE,
                'granted_at' => now(),
                'revoked_at' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($values)->save();

                return $existing->load(['user.status', 'grantedBy.status', 'status']);
            }

            return DeviceUserAccess::query()->create([
                'device_id' => $device->id,
                'user_id' => $grantee->id,
                ...$values,
            ])->load(['user.status', 'grantedBy.status', 'status']);
        });
    }

    public function revoke(Device $device, DeviceUserAccess $access, User $owner): DeviceUserAccess
    {
        return DB::transaction(function () use ($device, $access): DeviceUserAccess {
            Device::query()->lockForUpdate()->findOrFail($device->id);
            $locked = DeviceUserAccess::query()->lockForUpdate()->findOrFail($access->id);

            if ($locked->device_id !== $device->id) {
                throw new BusinessException('ACCESS_NOT_FOUND', 'The access record was not found.', 404);
            }

            $locked->load('status');
            if ($locked->status->code !== DeviceAccessStatus::ACTIVE) {
                throw new BusinessException('INVALID_ACCESS_TRANSITION', 'The access record cannot be revoked.');
            }

            $locked->forceFill([
                'device_access_status_id' => DeviceAccessStatus::ID_REVOKED,
                'revoked_at' => now(),
            ])->save();

            return $locked->load(['user.status', 'grantedBy.status', 'status']);
        });
    }

    private function ensureCapacity(int $deviceId, ?int $excludeAccessId = null): void
    {
        $query = DeviceUserAccess::query()
            ->where('device_id', $deviceId)
            ->whereHas('status', fn ($q) => $q->where('code', DeviceAccessStatus::ACTIVE));

        if ($excludeAccessId !== null) {
            $query->where('id', '!=', $excludeAccessId);
        }

        if ($query->count() >= 5) {
            throw new BusinessException('DEVICE_ACCESS_LIMIT_REACHED', 'The device already has five active shared users.');
        }
    }
}
