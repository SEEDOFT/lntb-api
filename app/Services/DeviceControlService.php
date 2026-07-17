<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceControl;
use App\Models\DeviceControlStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeviceControlService
{
    public function create(Device $device, User $user, array $data): DeviceControl
    {
        return DB::transaction(function () use ($device, $user, $data): DeviceControl {
            Device::query()->lockForUpdate()->findOrFail($device->id);

            $control = DeviceControl::query()->create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'device_control_status_id' => DeviceControlStatus::resolveId(DeviceControlStatus::PENDING),
                'control_type' => $data['control_type'],
                'control_data' => $data['control_data'] ?? null,
                'requested_at' => now(),
            ]);

            return $control->load(['user.status', 'status']);
        });
    }

    public function transition(DeviceControl $control, string $target, ?string $failureMessage = null): DeviceControl
    {
        $allowed = [
            DeviceControlStatus::PENDING => [DeviceControlStatus::COMPLETED, DeviceControlStatus::FAILED],
        ];

        return DB::transaction(function () use ($control, $target, $failureMessage, $allowed): DeviceControl {
            $locked = DeviceControl::query()->with('status')->lockForUpdate()->findOrFail($control->id);

            if (! in_array($target, $allowed[$locked->status->code] ?? [], true)) {
                throw new BusinessException('INVALID_CONTROL_TRANSITION', 'The control status transition is invalid.');
            }

            $locked->forceFill([
                'device_control_status_id' => DeviceControlStatus::resolveId($target),
                'completed_at' => in_array($target, [DeviceControlStatus::COMPLETED, DeviceControlStatus::FAILED], true) ? now() : null,
                'failure_message' => $failureMessage,
            ])->save();

            return $locked->load('status');
        });
    }
}
