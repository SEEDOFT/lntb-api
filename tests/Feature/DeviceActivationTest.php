<?php

declare(strict_types=1);

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\DeviceActivation;
use App\Models\DeviceStatus;
use App\Models\User;
use App\Models\UserStatus;
use App\Services\DeviceActivationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function activationDevice(array $attributes = []): Device
{
    return Device::factory()->create($attributes);
}

function activationUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

it('prepares an owner-bound activation QR without exposing the token in storage', function (): void {
    $device = activationDevice(['serial_number' => 'LNTB-ACT-001']);
    $customer = activationUser([
        'email' => 'owner@example.com',
        'email_verified_at' => now(),
    ]);

    $this->artisan('device:prepare-activation', [
        'serial' => $device->serial_number,
        'customer_login' => $customer->email,
        '--operator' => 'seller-01',
    ])->assertSuccessful()->expectsOutputToContain('Device activation prepared.');

    $activation = DeviceActivation::query()->sole();
    expect($activation->intended_user_id)->toBe($customer->id)
        ->and($activation->prepared_by_identifier)->toBe('seller-01')
        ->and($activation->token_hash)->toHaveLength(64)
        ->and(File::exists(storage_path("app/activations/{$activation->public_reference}.svg")))->toBeTrue();
});

it('rejects unknown and inactive customers during preparation', function (): void {
    $device = activationDevice(['serial_number' => 'LNTB-ACT-002']);
    activationUser([
        'email' => 'inactive@example.com',
        'user_status_id' => UserStatus::ID_SUSPENDED,
    ]);
    $service = app(DeviceActivationService::class);

    expect(fn () => $service->prepare($device->serial_number, 'missing@example.com', 'seller'))
        ->toThrow(BusinessException::class, 'One active customer account could not be resolved.')
        ->and(fn () => $service->prepare($device->serial_number, 'inactive@example.com', 'seller'))
        ->toThrow(BusinessException::class, 'One active customer account could not be resolved.');
});

it('revokes the previous activation when preparing a replacement', function (): void {
    $device = activationDevice(['serial_number' => 'LNTB-ACT-003']);
    $customer = activationUser(['email' => 'rotate@example.com']);
    $service = app(DeviceActivationService::class);

    $first = $service->prepare($device->serial_number, $customer->email, 'seller');
    $second = $service->prepare($device->serial_number, $customer->email, 'seller');

    expect($first['activation']->fresh()->revoked_at)->not->toBeNull()
        ->and($second['activation']->revoked_at)->toBeNull()
        ->and($second['activation']->public_reference)
        ->not->toBe($first['activation']->public_reference);
});

it('allows only the intended customer to activate and prevents replay', function (): void {
    $device = activationDevice(['serial_number' => 'LNTB-ACT-004']);
    $customer = activationUser(['email' => 'intended@example.com']);
    $other = activationUser(['email' => 'other@example.com']);
    $prepared = app(DeviceActivationService::class)
        ->prepare($device->serial_number, $customer->email, 'seller');
    $payload = json_decode($prepared['payload'], true, flags: JSON_THROW_ON_ERROR);

    $this->actingAs($other)
        ->postJson('/api/v1/devices/claim', [
            'device_ref' => $payload['device_ref'],
            'activation_token' => $payload['activation_token'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('status.error_code', 'INVALID_DEVICE_ACTIVATION');

    $this->actingAs($customer)
        ->postJson('/api/v1/devices/claim', [
            'device_ref' => $payload['device_ref'],
            'activation_token' => $payload['activation_token'],
            'name' => 'My Controller',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'My Controller');

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'owner_user_id' => $customer->id,
        'device_status_id' => DeviceStatus::ID_ACTIVE,
    ]);

    $this->actingAs($customer)
        ->postJson('/api/v1/devices/claim', [
            'device_ref' => $payload['device_ref'],
            'activation_token' => $payload['activation_token'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('status.error_code', 'INVALID_DEVICE_ACTIVATION');
});

it('rejects invalid expired and revoked activation tokens without changing ownership', function (): void {
    $device = activationDevice(['serial_number' => 'LNTB-ACT-005']);
    $customer = activationUser(['email' => 'failure@example.com']);
    $prepared = app(DeviceActivationService::class)
        ->prepare($device->serial_number, $customer->email, 'seller');
    $payload = json_decode($prepared['payload'], true, flags: JSON_THROW_ON_ERROR);

    foreach ([
        ['activation_token' => str_repeat('A', 43)],
        ['activation_token' => $payload['activation_token'], 'expired' => true],
        ['activation_token' => $payload['activation_token'], 'revoked' => true],
    ] as $case) {
        $activation = $prepared['activation'];
        $activation->forceFill([
            'expires_at' => isset($case['expired']) ? now()->subMinute() : now()->addDay(),
            'revoked_at' => isset($case['revoked']) ? now() : null,
        ])->save();

        $this->actingAs($customer)
            ->postJson('/api/v1/devices/claim', [
                'device_ref' => $payload['device_ref'],
                'activation_token' => $case['activation_token'],
            ])
            ->assertUnprocessable()
            ->assertJsonMissing(['activation_token' => $case['activation_token']]);
    }

    expect($device->fresh()->owner_user_id)->toBeNull();
});
