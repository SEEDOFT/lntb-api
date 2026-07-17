<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceControlStatus;
use App\Models\DeviceStatus;
use App\Models\DeviceType;
use App\Models\User;
use App\Models\UserStatus;
use App\Support\MacAddress;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);
});

function authHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function createDevice(): array
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $rawCode = '';
    for ($i = 0; $i < 12; $i++) {
        $rawCode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $claimCode = implode('-', str_split($rawCode, 4));
    $mac = 'AA:BB:CC:DD:EE:01';

    $device = Device::query()->create([
        'device_type_id' => DeviceType::resolveId(DeviceType::SMART_FARM_CONTROLLER),
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::AVAILABLE),
        'serial_number' => 'SN-TEST-001',
        'mac_address' => $mac,
        'claim_code_hash' => Hash::make($claimCode),
        'name' => 'Test Device',
        'firmware_version' => '1.0.0',
    ]);

    return ['device' => $device, 'claim_code' => $claimCode];
}

// ─── AUTH ─────────────────────────────────────────────────────────

it('registers a new user', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone_number' => '+6281234567890',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'data' => ['token', 'token_type', 'expires_at', 'user']]);
});

it('fails registration with weak password', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422);
});

it('logs in with email', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('Str0ng!Passw0rd'),
        'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'Str0ng!Passw0rd',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user']]);
});

it('fails login with wrong password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('Str0ng!Passw0rd'),
        'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

it('retrieves current user', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->getJson('/api/v1/auth/me', authHeaders($token));

    $response->assertStatus(200);
});

it('logs out', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/auth/logout', [], authHeaders($token));

    $response->assertStatus(200);
    expect($user->tokens()->count())->toBe(0);
});

it('rejects unauthenticated requests', function (): void {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
    $this->getJson('/api/v1/devices')->assertStatus(401);
});

// ─── DEVICES ──────────────────────────────────────────────────────

it('lists accessible devices for owner', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $user->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->getJson('/api/v1/devices', authHeaders($token));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('claims a device', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;
    $d = createDevice();

    $response = $this->postJson('/api/v1/devices/claim', [
        'mac_address' => $d['device']->mac_address,
        'claim_code' => $d['claim_code'],
        'name' => 'My Controller',
    ], authHeaders($token));

    $response->assertStatus(200);
    expect($response->json('data.status.code'))->toBe('active');
});

it('fails claiming already claimed device', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update(['owner_user_id' => User::factory()->create()->id]);

    $response = $this->postJson('/api/v1/devices/claim', [
        'mac_address' => $d['device']->mac_address,
        'claim_code' => $d['claim_code'],
    ], authHeaders($token));

    $response->assertStatus(409);
});

it('shows a device', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $user->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->getJson("/api/v1/devices/{$d['device']->id}", authHeaders($token));

    $response->assertStatus(200);
});

// ─── DEVICE USERS ─────────────────────────────────────────────────

it('owner grants access to another user', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $grantee = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $grantee->email,
    ], authHeaders($token));

    $response->assertStatus(201);
});

it('owner cannot grant access to themselves', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $owner->email,
    ], authHeaders($token));

    $response->assertStatus(409);
});

it('enforces 5-user limit', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $u = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
        $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
            'login' => $u->email,
        ], authHeaders($token))->assertStatus(201);
    }

    $extra = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $extra->email,
    ], authHeaders($token));
    $response->assertStatus(409);
});

it('owner lists device users', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $grantee = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);
    $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $grantee->email,
    ], authHeaders($token));

    $response = $this->getJson("/api/v1/devices/{$d['device']->id}/users", authHeaders($token));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('owner revokes user access', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $grantee = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $grant = $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $grantee->email,
    ], authHeaders($token));
    $accessId = $grant->json('data.id');

    $response = $this->deleteJson("/api/v1/devices/{$d['device']->id}/users/{$accessId}", [], authHeaders($token));

    $response->assertStatus(200);
    expect($response->json('data.status.code'))->toBe('revoked');
});

// ─── DEVICE CONTROLS ──────────────────────────────────────────────

it('owner stores a control command', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/controls", [
        'control_type' => 'irrigation.start',
        'control_data' => ['duration' => 30],
    ], authHeaders($token));

    $response->assertStatus(201);
    expect($response->json('data.status.code'))->toBe('pending');
});

it('shared user stores a control command', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $grantee = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $granteeToken = $grantee->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);
    $ownerToken = $owner->createToken('test')->plainTextToken;
    $this->postJson("/api/v1/devices/{$d['device']->id}/users", [
        'login' => $grantee->email,
    ], authHeaders($ownerToken));

    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/controls", [
        'control_type' => 'irrigation.stop',
    ], authHeaders($granteeToken));

    $response->assertStatus(201);
});

it('unauthorized user cannot control device', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $stranger = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $strangerToken = $stranger->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);

    $response = $this->postJson("/api/v1/devices/{$d['device']->id}/controls", [
        'control_type' => 'irrigation.start',
    ], authHeaders($strangerToken));

    $response->assertStatus(403);
});

it('lists control history', function (): void {
    $owner = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $owner->createToken('test')->plainTextToken;
    $d = createDevice();
    $d['device']->update([
        'owner_user_id' => $owner->id,
        'device_status_id' => DeviceStatus::resolveId(DeviceStatus::ACTIVE),
    ]);
    $this->postJson("/api/v1/devices/{$d['device']->id}/controls", [
        'control_type' => 'irrigation.start',
    ], authHeaders($token));

    $response = $this->getJson("/api/v1/devices/{$d['device']->id}/controls", authHeaders($token));

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

// ─── 404 HANDLING ─────────────────────────────────────────────────

it('returns 404 for non-existent device', function (): void {
    $user = User::factory()->create(['user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE)]);
    $token = $user->createToken('test')->plainTextToken;

    $this->getJson('/api/v1/devices/99999', authHeaders($token))->assertStatus(404);
});
