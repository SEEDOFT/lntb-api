<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceActivation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('creates the deterministic demo device users and scanner QR idempotently', function (): void {
    $this->artisan('phase1:demo')->assertSuccessful();
    $this->artisan('phase1:demo')->assertSuccessful();

    $device = Device::query()->where('serial_number', 'LNTB-DEMO-0001')->sole();

    expect(Device::query()->where('serial_number', 'LNTB-DEMO-0001')->count())->toBe(1)
        ->and(User::query()->where('email', 'like', '%@demo.lntb.test')->count())->toBe(7)
        ->and($device->mac_address)->toBe('02:00:00:00:00:01')
        ->and($device->owner_user_id)->toBeNull()
        ->and(DeviceActivation::query()->where('device_id', $device->id)->count())->toBe(2)
        ->and(File::exists(storage_path('app/demo/lntb-demo-device-qr.svg')))->toBeTrue();
});

it('resets only the fixed demo device claim', function (): void {
    $this->artisan('phase1:demo')->assertSuccessful();
    $device = Device::query()->where('serial_number', 'LNTB-DEMO-0001')->sole();
    $owner = User::query()->where('email', 'owner@demo.lntb.test')->sole();

    $device->forceFill([
        'owner_user_id' => $owner->id,
        'claimed_at' => now(),
        'claim_code_used_at' => now(),
    ])->save();

    $this->artisan('phase1:demo --reset')->assertSuccessful();

    $device->refresh();
    expect($device->owner_user_id)->toBeNull()
        ->and($device->claimed_at)->toBeNull()
        ->and($device->claim_code_used_at)->toBeNull()
        ->and(DeviceActivation::query()->where('device_id', $device->id)->count())->toBeGreaterThan(0);
});
