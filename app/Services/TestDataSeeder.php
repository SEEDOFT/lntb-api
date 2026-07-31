<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceControl;
use App\Models\DeviceControlStatus;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Models\Notification;
use App\Models\NotificationStatus;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class TestDataSeeder
{
    public const string COUNTRY_CODE = '+855';

    public const string PHONE_NUMBER = '010000099';

    public const string PASSWORD = 'LntbTest123!';

    public const string FARM_NAME = 'Sokha Tomato Farm';

    /** @return array{user: User, farm_id: int, devices: array<int, Device>} */
    public function seed(): array
    {
        return DB::transaction(function (): array {
            $user = $this->seedUser();
            $devices = $this->seedDevices($user);
            $farmId = $this->seedFarm($user, $devices);
            $this->seedControls($user, $devices);
            $this->seedNotifications($user, $farmId);

            return ['user' => $user, 'farm_id' => $farmId, 'devices' => $devices];
        });
    }

    public function reset(): void
    {
        DB::transaction(function (): void {
            $user = User::query()
                ->where('country_code', self::COUNTRY_CODE)
                ->where('phone_number', self::PHONE_NUMBER)
                ->first();
            if ($user === null) {
                return;
            }

            $farmIds = DB::table('farms')->where('owner_user_id', $user->id)->pluck('id');
            $deviceIds = Device::query()
                ->whereIn('serial_number', array_keys($this->deviceDefinitions()))
                ->pluck('id');

            foreach ([
                'assistant_messages',
                'harvest_records',
                'farm_logs',
                'ripeness_results',
                'usage_records',
                'irrigation_settings',
                'farm_tasks',
                'sensor_readings',
                'crop_cycles',
                'farm_devices',
            ] as $table) {
                DB::table($table)->whereIn('farm_id', $farmIds)->delete();
            }

            DeviceControl::query()->whereIn('device_id', $deviceIds)->delete();
            Notification::query()->where('user_id', $user->id)->delete();
            DB::table('farms')->whereIn('id', $farmIds)->delete();
            Device::query()->whereIn('id', $deviceIds)->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }

    private function seedUser(): User
    {
        return User::query()->updateOrCreate(
            [
                'country_code' => self::COUNTRY_CODE,
                'phone_number' => self::PHONE_NUMBER,
            ],
            [
                'name' => 'Sokha',
                'password' => Hash::make(self::PASSWORD),
                'user_status_id' => $this->lookupId(UserStatus::class, UserStatus::ACTIVE),
            ],
        );
    }

    /** @return array<int, Device> */
    private function seedDevices(User $user): array
    {
        $activeStatusId = $this->lookupId(DeviceStatus::class, DeviceStatus::ACTIVE);
        $devices = [];

        foreach ($this->deviceDefinitions() as $serial => $definition) {
            $devices[] = Device::query()->updateOrCreate(
                ['serial_number' => $serial],
                [
                    'device_type_id' => $this->lookupId(DeviceType::class, $definition['type']),
                    'device_status_id' => $activeStatusId,
                    'owner_user_id' => $user->id,
                    'name' => $definition['name'],
                    'placement' => 'Greenhouse A',
                    'mac_address' => $definition['mac'],
                    'claim_code_hash' => hash('sha256', $serial),
                    'firmware_version' => $definition['firmware'],
                    'claim_code_used_at' => now()->subDays(14),
                    'claimed_at' => now()->subDays(14),
                    'last_seen_at' => now()->subMinutes($definition['last_seen_minutes']),
                ],
            );
        }

        return $devices;
    }

    /**
     * @param  array<int, Device>  $devices
     */
    private function seedFarm(User $user, array $devices): int
    {
        DB::table('farms')->updateOrInsert(
            ['owner_user_id' => $user->id, 'name' => self::FARM_NAME],
            [
                'farm_status_id' => $this->tableLookupId('farm_statuses', 'active'),
                'location' => 'Kandal Province, Cambodia',
                'created_at' => now()->subMonths(3),
                'updated_at' => now(),
            ],
        );
        $farmId = (int) DB::table('farms')
            ->where('owner_user_id', $user->id)
            ->where('name', self::FARM_NAME)
            ->value('id');

        $this->replaceFarmDetails($farmId, $user, $devices);

        return $farmId;
    }

    /**
     * @param  array<int, Device>  $devices
     */
    private function replaceFarmDetails(int $farmId, User $user, array $devices): void
    {
        foreach ([
            'harvest_records',
            'assistant_messages',
            'farm_logs',
            'ripeness_results',
            'usage_records',
            'irrigation_settings',
            'farm_tasks',
            'sensor_readings',
            'crop_cycles',
            'farm_devices',
        ] as $table) {
            DB::table($table)->where('farm_id', $farmId)->delete();
        }

        foreach ($devices as $device) {
            DB::table('farm_devices')->insert([
                'farm_id' => $farmId,
                'device_id' => $device->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('crop_cycles')->insert([
            'farm_id' => $farmId,
            'crop_cycle_status_id' => $this->tableLookupId('crop_cycle_statuses', 'active'),
            'crop_name' => 'Cherry Tomato',
            'started_on' => now()->subDays(45)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fan = $devices[0];
        $camera = $devices[2];
        $readings = [
            ['soil_moisture', 54.0, '%', 'normal', $fan->id],
            ['temperature', 28.4, '°C', 'normal', $fan->id],
            ['humidity', 72.0, '%', 'attention', $fan->id],
            ['light', 18200.0, 'lux', 'normal', $camera->id],
        ];
        foreach ($readings as [$code, $value, $unit, $status, $deviceId]) {
            DB::table('sensor_readings')->insert([
                'farm_id' => $farmId,
                'device_id' => $deviceId,
                'sensor_type_id' => $this->tableLookupId('sensor_types', $code),
                'value' => $value,
                'unit' => $unit,
                'status_code' => $status,
                'recorded_at' => now()->subMinutes(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('irrigation_settings')->insert([
            'farm_id' => $farmId,
            'moisture_threshold' => 35,
            'mode_code' => 'manual',
            'last_triggered_at' => now()->subHours(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(0, 6) as $daysAgo) {
            DB::table('usage_records')->insert([
                'farm_id' => $farmId,
                'recorded_on' => now()->subDays($daysAgo)->toDateString(),
                'water_cubic_meters' => 0.42 + ($daysAgo * 0.02),
                'electricity_kwh' => 1.18 + ($daysAgo * 0.04),
                'water_rate_usd' => 0.35,
                'electricity_rate_usd' => 0.18,
                'total_cost_usd' => 0.36,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('farm_logs')->insert([
            'farm_id' => $farmId,
            'user_id' => $user->id,
            'farm_log_type_id' => $this->tableLookupId('farm_log_types', 'irrigation'),
            'title' => 'Morning irrigation completed',
            'notes' => 'Greenhouse A received twelve minutes of irrigation.',
            'recorded_at' => now()->subHours(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('assistant_messages')->insert([
            'farm_id' => $farmId,
            'user_id' => $user->id,
            'question' => 'What should I check today?',
            'answer' => 'Humidity is slightly high in Greenhouse A. Check airflow and keep the fan schedule active before the afternoon heat.',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);
    }

    /** @param array<int, Device> $devices */
    private function seedControls(User $user, array $devices): void
    {
        DeviceControl::query()->whereIn('device_id', collect($devices)->pluck('id'))->delete();
        $fan = $devices[0];
        $roof = $devices[1];
        $statuses = [
            ['fan.start', DeviceControlStatus::COMPLETED, $fan->id, now()->subHours(3), now()->subHours(3)->addSeconds(8), null],
            ['fan.stop', DeviceControlStatus::PENDING, $fan->id, now()->subMinutes(8), null, null],
            ['roof.open', DeviceControlStatus::COMPLETED, $roof->id, now()->subHours(1), now()->subHours(1)->addSeconds(6), null],
            ['roof.close', DeviceControlStatus::FAILED, $roof->id, now()->subDay(), null, 'Safety interlock prevented the roof command.'],
        ];
        foreach ($statuses as [$type, $status, $deviceId, $requestedAt, $completedAt, $failure]) {
            DeviceControl::query()->create([
                'device_id' => $deviceId,
                'user_id' => $user->id,
                'device_control_status_id' => $this->lookupId(DeviceControlStatus::class, $status),
                'control_type' => $type,
                'control_data' => $type === 'fan.start' ? ['duration_minutes' => 20] : [],
                'requested_at' => $requestedAt,
                'completed_at' => $completedAt,
                'failure_message' => $failure,
            ]);
        }
    }

    private function seedNotifications(User $user, int $farmId): void
    {
        Notification::query()->where('user_id', $user->id)->delete();
        Notification::query()->create([
            'user_id' => $user->id,
            'deduplication_key' => 'test-data:farm-status',
            'notification_type_id' => $this->lookupId(NotificationType::class, NotificationType::SYSTEM),
            'notification_status_id' => $this->lookupId(NotificationStatus::class, NotificationStatus::UNREAD),
            'title' => 'Farm systems are online',
            'body' => 'Greenhouse A devices reported normally two minutes ago.',
            'data' => ['farm_id' => $farmId],
        ]);
    }

    /** @return array<string, array{name: string, type: string, mac: string, firmware: string, last_seen_minutes: int}> */
    private function deviceDefinitions(): array
    {
        return [
            'LNTB-TEST-FAN-0001' => [
                'name' => 'Exhaust Fan',
                'type' => DeviceType::FAN,
                'mac' => '02:00:00:00:10:01',
                'firmware' => '1.4.2',
                'last_seen_minutes' => 2,
            ],
            'LNTB-TEST-ROOF-0001' => [
                'name' => 'Roof Actuator',
                'type' => DeviceType::ROOF,
                'mac' => '02:00:00:00:10:02',
                'firmware' => '2.1.0',
                'last_seen_minutes' => 4,
            ],
            'LNTB-TEST-CAM-0001' => [
                'name' => 'Surveillance Camera',
                'type' => DeviceType::CAMERA,
                'mac' => '02:00:00:00:10:03',
                'firmware' => '2.1.0',
                'last_seen_minutes' => 5,
            ],
            'LNTB-TEST-METER-0001' => [
                'name' => 'Water Meter',
                'type' => DeviceType::WATER_ENERGY_METER,
                'mac' => '02:00:00:00:10:04',
                'firmware' => '1.2.5',
                'last_seen_minutes' => 3,
            ],
        ];
    }

    /** @param class-string<Model> $model */
    private function lookupId(string $model, string $code): int
    {
        $id = $model::query()->where('code', $code)->value('id');
        if ($id === null) {
            throw new RuntimeException("Required lookup code [{$code}] is not seeded.");
        }

        return (int) $id;
    }

    private function tableLookupId(string $table, string $code): int
    {
        $id = DB::table($table)->where('code', $code)->value('id');
        if ($id === null) {
            throw new RuntimeException("Required lookup code [{$code}] is not seeded.");
        }

        return (int) $id;
    }
}
