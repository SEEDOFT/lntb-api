<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DeviceService
{
    public function accessible(User $user): Collection
    {
        return Device::query()
            ->with(['type', 'status'])
            ->where(function ($query) use ($user): void {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('accessRecords', function ($access) use ($user): void {
                        $access->where('user_id', $user->id)
                            ->whereHas('status', fn ($status) => $status->where('code', DeviceAccessStatus::ACTIVE));
                    });
            })->orderBy('name')->orderBy('id')->get();
    }

    public function claim(User $user, array $data): Device
    {
        return DB::transaction(function () use ($user, $data): Device {
            $device = Device::query()->where('mac_address', $data['mac_address'])->lockForUpdate()->first();
            if ($device === null) {
                throw new BusinessException('DEVICE_NOT_FOUND', 'The device was not found.', 404);
            }
            if ($device->owner_user_id !== null) {
                throw new BusinessException('DEVICE_ALREADY_CLAIMED', 'The device has already been claimed.');
            }
            if ($device->claim_code_used_at !== null) {
                throw new BusinessException('CLAIM_CODE_ALREADY_USED', 'The claim code has already been used.');
            }
            $availableId = DeviceStatus::ID_AVAILABLE;
            if ($device->device_status_id !== $availableId) {
                throw new BusinessException('DEVICE_NOT_AVAILABLE', 'The device is not available.');
            }
            if (! Hash::check($data['claim_code'], $device->claim_code_hash)) {
                throw new BusinessException('INVALID_CLAIM_CODE', 'The claim code is invalid.', 422);
            }
            $device->forceFill([
                'owner_user_id' => $user->id,
                'device_status_id' => DeviceStatus::ID_ACTIVE,
                'name' => $data['name'] ?? $device->name,
                'claimed_at' => now(),
                'claim_code_used_at' => now(),
            ])->save();

            return $device->load(['type', 'status']);
        });
    }
}
