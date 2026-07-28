<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceStatus;
use App\Models\DeviceUserAccess;
use App\Models\User;
use App\Models\UserStatus;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function batchAuthHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function makeControlledDevice(User $owner, string $placement = 'Greenhouse A'): Device
{
    return Device::factory()->create([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::query()->where('code', DeviceStatus::ACTIVE)->valueOrFail('id'),
        'placement' => $placement,
    ]);
}

it('creates commands for accessible devices and returns privacy safe partial failures', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::ID_ACTIVE]);
    $otherOwner = User::factory()->create(['user_status_id' => UserStatus::ID_ACTIVE]);
    $token = $owner->createToken('batch-test')->plainTextToken;
    $first = makeControlledDevice($owner);
    $second = makeControlledDevice($owner, 'Field B');
    $inaccessible = makeControlledDevice($otherOwner);

    $response = $this->postJson('/api/v1/devices/controls/batch', [
        'device_ids' => [$first->id, $inaccessible->id, $second->id, 999999],
        'control_type' => 'irrigation.start',
        'control_data' => [],
    ], batchAuthHeaders($token));

    $response->assertOk()
        ->assertJsonPath('data.accepted_count', 2)
        ->assertJsonPath('data.failed_count', 2)
        ->assertJsonPath('data.results.1.error_code', 'DEVICE_ACCESS_DENIED')
        ->assertJsonPath('data.results.3.error_code', 'DEVICE_ACCESS_DENIED');

    expect($response->json('data.results.1'))->not->toHaveKey('control')
        ->and($response->json('data.results.3'))->not->toHaveKey('control');
    $this->assertDatabaseCount('device_controls', 2);
    $this->assertDatabaseHas('device_controls', ['device_id' => $first->id, 'user_id' => $owner->id]);
    $this->assertDatabaseHas('device_controls', ['device_id' => $second->id, 'user_id' => $owner->id]);
});

it('allows an active shared user to use batch control', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::ID_ACTIVE]);
    $shared = User::factory()->create(['user_status_id' => UserStatus::ID_ACTIVE]);
    $sharedToken = $shared->createToken('shared')->plainTextToken;
    $device = makeControlledDevice($owner);
    DeviceUserAccess::query()->create([
        'device_id' => $device->id,
        'user_id' => $shared->id,
        'granted_by_user_id' => $owner->id,
        'device_access_status_id' => DeviceAccessStatus::query()
            ->where('code', DeviceAccessStatus::ACTIVE)
            ->valueOrFail('id'),
        'granted_at' => now(),
    ]);

    $this->postJson('/api/v1/devices/controls/batch', [
        'device_ids' => [$device->id],
        'control_type' => 'fan.start',
    ], batchAuthHeaders($sharedToken))
        ->assertOk()
        ->assertJsonPath('data.accepted_count', 1);

    $this->assertDatabaseHas('device_controls', [
        'device_id' => $device->id,
        'user_id' => $shared->id,
        'control_type' => 'fan.start',
    ]);
});

it('validates batch device ids and control type', function (array $payload): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::ID_ACTIVE]);
    $token = $user->createToken('batch-validation')->plainTextToken;

    $this->postJson('/api/v1/devices/controls/batch', $payload, batchAuthHeaders($token))
        ->assertUnprocessable();
})->with([
    'empty list' => [['device_ids' => [], 'control_type' => 'fan.start']],
    'duplicate ids' => [['device_ids' => [1, 1], 'control_type' => 'fan.start']],
    'more than twenty' => [['device_ids' => range(1, 21), 'control_type' => 'fan.start']],
    'unsupported command' => [['device_ids' => [1], 'control_type' => 'pump.explode']],
]);
