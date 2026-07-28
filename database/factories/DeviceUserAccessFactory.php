<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceUserAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeviceUserAccess> */
final class DeviceUserAccessFactory extends Factory
{
    protected $model = DeviceUserAccess::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'user_id' => User::factory(),
            'granted_by_user_id' => User::factory(),
            'device_access_status_id' => DeviceAccessStatus::query()->where('code', DeviceAccessStatus::ACTIVE)->value('id'),
            'granted_at' => now(),
            'revoked_at' => null,
        ];
    }
}
