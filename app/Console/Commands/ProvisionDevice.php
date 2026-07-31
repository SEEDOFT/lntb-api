<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Support\MacAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('device:provision {serial} {mac} {--type=fan} {--name=} {--firmware=} {--power=}')]
#[Description('Provision an unowned device for later seller-assisted activation')]
final class ProvisionDevice extends Command
{
    public function handle(): int
    {
        try {
            $mac = MacAddress::normalize((string) $this->argument('mac'));
            $typeCode = (string) $this->option('type');
            $typeId = DeviceType::query()->where('code', $typeCode)->value('id');
            if ($typeId === null) {
                throw new \Exception("Invalid device type code: {$typeCode}");
            }
            $availableStatusId = DeviceStatus::query()
                ->where('code', DeviceStatus::AVAILABLE)
                ->valueOrFail('id');

            $power = $this->option('power');
            if ($power !== null && ((int) $power) < 1) {
                throw new \Exception('Power rating must be at least 1 watt.');
            }

            $device = Device::query()->create([
                'device_type_id' => $typeId,
                'device_status_id' => $availableStatusId,
                'serial_number' => (string) $this->argument('serial'),
                'mac_address' => $mac,
                'claim_code_hash' => hash('sha256', random_bytes(32)),
                'name' => $this->option('name') ?: null,
                'firmware_version' => $this->option('firmware') ?: null,
                'rated_power_watts' => $power !== null ? (int) $power : null,
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Device {$device->serial_number} provisioned.");

        return self::SUCCESS;
    }
}
