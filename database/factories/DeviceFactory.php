<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Device> */
final class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_type_id' => DeviceType::query()->where('code', DeviceType::SMART_FARM_CONTROLLER)->value('id'),
            'device_status_id' => DeviceStatus::query()->where('code', DeviceStatus::AVAILABLE)->value('id'),
            'serial_number' => fake()->unique()->bothify('LNTB-########'),
            'mac_address' => implode(':', array_map(fn () => strtoupper(fake()->regexify('[0-9A-F]{2}')), range(1, 6))),
            'claim_code_hash' => Hash::make('ABCD-EFGH-1234'),
        ];
    }
}
