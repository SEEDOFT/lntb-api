<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeviceAccessStatus;
use App\Models\DeviceControlStatus;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Models\UserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLookup(UserStatus::class, [
            UserStatus::ACTIVE => 'Active',
            UserStatus::SUSPENDED => 'Suspended',
            UserStatus::CLOSED => 'Closed',
        ]);
        $this->seedLookup(DeviceType::class, [
            DeviceType::SMART_FARM_CONTROLLER => 'Smart Farm Controller',
            DeviceType::CAMERA => 'Camera',
            DeviceType::WATER_ENERGY_METER => 'Water & Energy Meter',
        ]);
        $this->seedLookup(DeviceStatus::class, [
            DeviceStatus::AVAILABLE => 'Available',
            DeviceStatus::ACTIVE => 'Active',
            DeviceStatus::SUSPENDED => 'Suspended',
            DeviceStatus::MAINTENANCE => 'Maintenance',
            DeviceStatus::RETIRED => 'Retired',
        ]);
        $this->seedLookup(DeviceAccessStatus::class, [
            DeviceAccessStatus::ACTIVE => 'Active',
            DeviceAccessStatus::REVOKED => 'Revoked',
        ]);
        $this->seedLookup(DeviceControlStatus::class, [
            DeviceControlStatus::PENDING => 'Pending',
            DeviceControlStatus::COMPLETED => 'Completed',
            DeviceControlStatus::FAILED => 'Failed',
        ]);
    }

    /** @param class-string<Model> $model */
    private function seedLookup(string $model, array $values): void
    {
        foreach ($values as $code => $name) {
            $model::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
