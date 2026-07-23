<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserStatus;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->user = User::factory()->create([
        'user_status_id' => UserStatus::ID_ACTIVE,
    ]);
    Sanctum::actingAs($this->user);
});

it('registers and rotates the current installation token', function (): void {
    $payload = [
        'fcm_device_key' => 'device-key-one',
        'fcm_token' => 'first-token',
        'platform' => 'android',
        'device_name' => 'Pixel',
        'app_version' => '1.0.0+1',
    ];

    $this->postJson('/api/v1/auth/fcm-token', $payload)->assertOk();
    $this->postJson('/api/v1/auth/fcm-token', [
        ...$payload,
        'fcm_token' => 'rotated-token',
    ])->assertOk();

    expect(UserFcmToken::query()->count())->toBe(1);
    $this->assertDatabaseHas('user_fcm_tokens', [
        'user_id' => $this->user->id,
        'device_key' => 'device-key-one',
        'fcm_token' => 'rotated-token',
        'revoked_at' => null,
    ]);
});

it('stores multiple installations and revokes only the requested phone', function (): void {
    foreach ([1, 2] as $number) {
        $this->postJson('/api/v1/auth/fcm-token', [
            'fcm_device_key' => "device-key-{$number}",
            'fcm_token' => "token-{$number}",
            'platform' => 'android',
        ])->assertOk();
    }

    $this->deleteJson('/api/v1/auth/fcm-token', [
        'fcm_device_key' => 'device-key-1',
    ])->assertOk();

    $this->assertDatabaseHas('user_fcm_tokens', [
        'device_key' => 'device-key-1',
        'fcm_token' => null,
    ]);
    $this->assertDatabaseHas('user_fcm_tokens', [
        'device_key' => 'device-key-2',
        'fcm_token' => 'token-2',
        'revoked_at' => null,
    ]);
});
