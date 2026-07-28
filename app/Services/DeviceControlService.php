<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceControl;
use App\Models\DeviceControlStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeviceControlService
{
    /**
     * @param  list<int>  $deviceIds
     * @param  array<string, mixed>  $data
     * @return array{accepted_count: int, failed_count: int, results: list<array<string, mixed>>}
     */
    public function createBatch(User $user, array $deviceIds, array $data): array
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get()->keyBy('id');
        $results = [];
        $accepted = 0;

        foreach ($deviceIds as $deviceId) {
            $device = $devices->get($deviceId);
            if ($device === null || Gate::forUser($user)->denies('control', $device)) {
                $results[] = [
                    'device_id' => $deviceId,
                    'accepted' => false,
                    'error_code' => 'DEVICE_ACCESS_DENIED',
                ];

                continue;
            }

            try {
                $results[] = [
                    'device_id' => $deviceId,
                    'accepted' => true,
                    'control' => $this->create($device, $user, $data),
                ];
                $accepted++;
            } catch (\Throwable) {
                $results[] = [
                    'device_id' => $deviceId,
                    'accepted' => false,
                    'error_code' => 'CONTROL_CREATION_FAILED',
                ];
            }
        }

        return [
            'accepted_count' => $accepted,
            'failed_count' => count($results) - $accepted,
            'results' => $results,
        ];
    }

    public function create(Device $device, User $user, array $data): DeviceControl
    {
        return DB::transaction(function () use ($device, $user, $data): DeviceControl {
            Device::query()->lockForUpdate()->findOrFail($device->id);

            $control = DeviceControl::query()->create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'device_control_status_id' => $this->statusId(DeviceControlStatus::PENDING),
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

            $targetId = in_array($target, [DeviceControlStatus::COMPLETED, DeviceControlStatus::FAILED], true)
                ? $this->statusId($target)
                : null;

            if ($targetId === null || ! in_array($target, $allowed[$locked->status->code] ?? [], true)) {
                throw new BusinessException('INVALID_CONTROL_TRANSITION', 'The control status transition is invalid.');
            }

            $locked->forceFill([
                'device_control_status_id' => $targetId,
                'completed_at' => in_array($target, [DeviceControlStatus::COMPLETED, DeviceControlStatus::FAILED], true) ? now() : null,
                'failure_message' => $failureMessage,
            ])->save();

            return $locked->load('status');
        });
    }

    private function statusId(string $code): int
    {
        return (int) DeviceControlStatus::query()->where('code', $code)->valueOrFail('id');
    }
}
