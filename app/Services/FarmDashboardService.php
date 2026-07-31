<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FarmDashboardService
{
    /** @return array<string, mixed> */
    public function dashboard(Farm $farm, User $user): array
    {
        $metrics = DB::table('sensor_readings as readings')
            ->join('sensor_types as types', 'types.id', '=', 'readings.sensor_type_id')
            ->join('devices', 'devices.id', '=', 'readings.device_id')
            ->join('device_types', 'device_types.id', '=', 'devices.device_type_id')
            ->where('readings.farm_id', $farm->id)
            ->whereIn(
                'readings.id',
                DB::table('sensor_readings')
                    ->selectRaw('MAX(id)')
                    ->where('farm_id', $farm->id)
                    ->groupBy('sensor_type_id'),
            )
            ->orderBy('types.code')
            ->get([
                'types.code',
                'readings.value',
                'readings.unit',
                'readings.status_code',
                'readings.recorded_at',
                'devices.id as device_id',
                'devices.name as device_name',
                'device_types.code as device_type_code',
                'device_types.name as device_type_name',
            ])
            ->map(fn (object $row): array => [
                'code' => $row->code,
                'value' => (float) $row->value,
                'unit' => $row->unit,
                'status' => $row->status_code,
                'recorded_at' => $row->recorded_at,
                'device' => [
                    'id' => (int) $row->device_id,
                    'name' => $row->device_name,
                    'type' => [
                        'code' => $row->device_type_code,
                        'name' => $row->device_type_name,
                    ],
                ],
            ])
            ->values();

        $devices = DB::table('farm_devices')
            ->join('devices', 'devices.id', '=', 'farm_devices.device_id')
            ->join('device_types', 'device_types.id', '=', 'devices.device_type_id')
            ->join('device_statuses', 'device_statuses.id', '=', 'devices.device_status_id')
            ->where('farm_devices.farm_id', $farm->id)
            ->orderBy('devices.name')
            ->get([
                'devices.id',
                'devices.name',
                'devices.placement',
                'devices.serial_number',
                'devices.mac_address',
                'devices.firmware_version',
                'devices.last_seen_at',
                'devices.owner_user_id',
                'device_types.code as type_code',
                'device_types.name as type_name',
                'device_statuses.code as status_code',
                'device_statuses.name as status_name',
            ])
            ->map(fn (object $device): array => [
                'id' => (int) $device->id,
                'name' => $device->name,
                'placement' => $device->placement,
                'serial_number' => $device->serial_number,
                'mac_address' => $device->mac_address,
                'firmware_version' => $device->firmware_version,
                'last_seen_at' => $device->last_seen_at,
                'type' => ['code' => $device->type_code, 'name' => $device->type_name],
                'status' => ['code' => $device->status_code, 'name' => $device->status_name],
                'access_role' => (int) $device->owner_user_id === $user->id ? 'owner' : 'shared',
            ])
            ->values();

        $deviceIds = $devices->pluck('id');
        $latestUsage = DB::table('usage_records')
            ->where('farm_id', $farm->id)
            ->latest('recorded_on')
            ->first();
        $activity = DB::table('device_controls as controls')
            ->join('device_control_statuses as statuses', 'statuses.id', '=', 'controls.device_control_status_id')
            ->join('devices', 'devices.id', '=', 'controls.device_id')
            ->join('device_types', 'device_types.id', '=', 'devices.device_type_id')
            ->whereIn('controls.device_id', $deviceIds)
            ->latest('controls.requested_at')
            ->limit(10)
            ->get([
                'controls.id',
                'controls.device_id',
                'controls.control_type',
                'controls.requested_at',
                'controls.completed_at',
                'controls.failure_message',
                'statuses.code as status_code',
                'statuses.name as status_name',
                'devices.name as device_name',
                'device_types.code as device_type_code',
                'device_types.name as device_type_name',
            ])
            ->map(fn (object $control): array => [
                'id' => (int) $control->id,
                'device_id' => (int) $control->device_id,
                'device_name' => $control->device_name,
                'device' => [
                    'id' => (int) $control->device_id,
                    'name' => $control->device_name,
                    'type' => [
                        'code' => $control->device_type_code,
                        'name' => $control->device_type_name,
                    ],
                ],
                'control_type' => $control->control_type,
                'status' => ['code' => $control->status_code, 'name' => $control->status_name],
                'requested_at' => $control->requested_at,
                'completed_at' => $control->completed_at,
                'failure_message' => $control->failure_message,
            ])
            ->values();

        $warnings = $metrics
            ->where('status', '!=', 'normal')
            ->map(fn (array $metric): array => [
                'code' => 'sensor_'.$metric['status'],
                'message' => "{$metric['device']['name']} reported {$metric['code']} as {$metric['status']}.",
                'recorded_at' => $metric['recorded_at'],
            ])
            ->values();

        return [
            'farm' => $this->farm($farm),
            'metrics' => $metrics,
            'devices' => $devices,
            'activity' => $activity,
            'warnings' => $warnings,
            'open_task_count' => DB::table('farm_tasks')
                ->where('farm_id', $farm->id)
                ->where('task_status_id', $this->lookupId('task_statuses', 'open'))
                ->count(),
            'online_device_count' => $devices->where('status.code', 'active')->count(),
            'latest_alert' => $warnings->first(),
            'usage' => $latestUsage ? [
                'recorded_on' => $latestUsage->recorded_on,
                'water_cubic_meters' => (float) $latestUsage->water_cubic_meters,
                'electricity_kwh' => (float) $latestUsage->electricity_kwh,
                'total_cost_usd' => (float) $latestUsage->total_cost_usd,
            ] : null,
            'assistant' => $this->assistantSummary($farm, $user),
        ];
    }

    /** @return array<string, mixed> */
    public function farm(Farm $farm): array
    {
        $cycle = DB::table('crop_cycles')
            ->where('farm_id', $farm->id)
            ->where('crop_cycle_status_id', $this->lookupId('crop_cycle_statuses', 'active'))
            ->latest('started_on')
            ->first();

        return [
            'id' => $farm->id,
            'name' => $farm->name,
            'location' => $farm->location,
            'status' => ['code' => $farm->status?->code ?? 'inactive'],
            'current_crop_cycle' => $cycle ? [
                'id' => $cycle->id,
                'crop_name' => $cycle->crop_name,
                'started_on' => $cycle->started_on,
            ] : null,
        ];
    }

    private function lookupId(string $table, string $code): int
    {
        return (int) DB::table($table)->where('code', $code)->value('id');
    }

    /** @return array<string, mixed>|null */
    private function assistantSummary(Farm $farm, User $user): ?array
    {
        $message = DB::table('assistant_messages')
            ->where('farm_id', $farm->id)
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($message === null) {
            return null;
        }

        return [
            'question' => $message->question,
            'answer' => $message->answer,
            'created_at' => $message->created_at,
        ];
    }
}
