<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Support\MacAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

final class ProvisionDevice extends Command
{
    protected $signature = 'device:provision {serial} {mac} {--type=smart_farm_controller} {--name=} {--firmware=}';

    protected $description = 'Provision an unowned device and display its one-time claim code';

    public function handle(): int
    {
        try {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $rawCode = '';
            for ($i = 0; $i < 12; $i++) {
                $rawCode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $claimCode = implode('-', str_split($rawCode, 4));

            $mac = MacAddress::normalize((string) $this->argument('mac'));
            $typeCode = (string) $this->option('type');
            $typeId = DeviceType::resolveId($typeCode);

            $device = Device::query()->create([
                'device_type_id' => $typeId,
                'device_status_id' => DeviceStatus::resolveId(DeviceStatus::AVAILABLE),
                'serial_number' => (string) $this->argument('serial'),
                'mac_address' => $mac,
                'claim_code_hash' => Hash::make($claimCode),
                'name' => $this->option('name') ?: null,
                'firmware_version' => $this->option('firmware') ?: null,
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Device {$device->serial_number} provisioned.");
        $this->warn("Claim code (displayed once): {$claimCode}");

        return self::SUCCESS;
    }
}
