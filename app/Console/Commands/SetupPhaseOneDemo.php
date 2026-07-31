<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceControl;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Models\DeviceUserAccess;
use App\Models\User;
use App\Models\UserStatus;
use App\Services\DeviceActivationService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class SetupPhaseOneDemo extends Command
{
    private const string SERIAL = 'LNTB-DEMO-0001';

    private const string MAC = '02:00:00:00:00:01';

    private const string PASSWORD = 'LntbDemo123!';

    protected $signature = 'phase1:demo {--reset : Reset only the fixed Phase 1 demo device and its access/control records}';

    protected $description = 'Create deterministic local Phase 1 users, a claimable device, and its QR image';

    public function handle(DeviceActivationService $activations): int
    {
        if (app()->isProduction()) {
            $this->error('The Phase 1 demo command is disabled in production.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(fn (): array => $this->createDemoData());
            $prepared = $result['claimed']
                ? null
                : $activations->prepare(
                    self::SERIAL,
                    'owner@demo.lntb.test',
                    'phase1-demo',
                );
            $qrPath = $prepared === null ? null : $this->writeQr($prepared['payload']);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Phase 1 demo data is ready.');
        $this->line('Owner candidate: owner@demo.lntb.test / '.self::PASSWORD);
        $this->line('Shared candidates: shared1@demo.lntb.test through shared6@demo.lntb.test');
        $this->line('Device MAC: '.self::MAC);
        if ($qrPath !== null) {
            $this->line("Owner-bound QR image: {$qrPath}");
        }

        if ($result['claimed']) {
            $this->warn('The demo device is already claimed. Run with --reset to make it claimable again.');
        }

        return self::SUCCESS;
    }

    /** @return array{claimed: bool} */
    private function createDemoData(): array
    {
        $activeUserStatusId = $this->lookupId(UserStatus::class, UserStatus::ACTIVE);
        $availableDeviceStatusId = $this->lookupId(DeviceStatus::class, DeviceStatus::AVAILABLE);
        $deviceTypeId = $this->lookupId(DeviceType::class, DeviceType::FAN);

        $users = [
            ['name' => 'Demo Owner', 'phone_number' => '010000001', 'email' => 'owner@demo.lntb.test'],
            ...array_map(
                fn (int $number): array => [
                    'name' => "Demo Shared User {$number}",
                    'phone_number' => '01000000'.($number + 1),
                    'email' => "shared{$number}@demo.lntb.test",
                ],
                range(1, 6),
            ),
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'country_code' => '+855',
                    'password' => Hash::make(self::PASSWORD),
                    'user_status_id' => $activeUserStatusId,
                    'email_verified_at' => now(),
                ],
            );
        }

        $device = Device::query()->where('serial_number', self::SERIAL)->lockForUpdate()->first();
        if ($device === null) {
            $device = Device::query()->create([
                'device_type_id' => $deviceTypeId,
                'device_status_id' => $availableDeviceStatusId,
                'owner_user_id' => null,
                'name' => 'LNTB Demo Fan',
                'serial_number' => self::SERIAL,
                'mac_address' => self::MAC,
                'claim_code_hash' => hash('sha256', random_bytes(32)),
                'firmware_version' => '1.0.0-demo',
            ]);
        } elseif ($this->option('reset')) {
            DeviceControl::query()->where('device_id', $device->id)->delete();
            DeviceUserAccess::query()->where('device_id', $device->id)->delete();
            $device->forceFill([
                'device_type_id' => $deviceTypeId,
                'device_status_id' => $availableDeviceStatusId,
                'owner_user_id' => null,
                'name' => 'LNTB Demo Fan',
                'mac_address' => self::MAC,
                'claim_code_hash' => hash('sha256', random_bytes(32)),
                'firmware_version' => '1.0.0-demo',
                'claim_code_used_at' => null,
                'claimed_at' => null,
                'last_seen_at' => null,
            ])->save();
        } elseif ($device->owner_user_id === null) {
            $device->forceFill([
                'device_type_id' => $deviceTypeId,
                'device_status_id' => $availableDeviceStatusId,
                'mac_address' => self::MAC,
                'claim_code_hash' => hash('sha256', random_bytes(32)),
                'firmware_version' => '1.0.0-demo',
                'claim_code_used_at' => null,
            ])->save();
        }

        return ['claimed' => $device->owner_user_id !== null];
    }

    private function writeQr(string $payload): string
    {
        $directory = base_path('storage/app/demo');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/lntb-demo-device-qr.svg';
        $options = new QROptions([
            'scale' => 10,
            'outputBase64' => false,
            'addQuietzone' => true,
        ]);
        (new QRCode($options))->render($payload, $path);

        return $path;
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
}
