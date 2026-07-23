<?php

use App\Models\Notification;
use App\Models\User;
use App\Models\UserStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->activeStatusId = UserStatus::ID_ACTIVE;
    config(['services.google.client_id' => 'test-web-client-id']);
    config(['services.google.ios_client_id' => 'test-ios-client-id']);
});

test('google login creates new user from valid token', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'test-web-client-id',
            'sub' => 'google-123',
            'email' => 'googleuser@example.com',
            'name' => 'Google User',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/auth/google', [
        'token' => 'valid-google-token',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => ['user', 'token', 'expires_at']]);

    expect($response['data']['user']['name'])->toBe('Google User');
    expect(User::where('google_id', 'google-123')->exists())->toBeTrue();
    $user = User::query()->where('google_id', 'google-123')->firstOrFail();
    $this->assertDatabaseHas('notifications', [
        'deduplication_key' => "welcome:user:{$user->id}",
    ]);
});

test('google login does not resend welcome notification for an existing user', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'test-web-client-id',
            'sub' => 'google-existing',
            'name' => 'Existing Google User',
        ], 200),
    ]);

    $this->postJson('/api/v1/auth/google', [
        'token' => 'valid-google-token',
    ])->assertOk();

    $this->postJson('/api/v1/auth/google', [
        'token' => 'valid-google-token',
    ])->assertOk();

    $user = User::query()->where('google_id', 'google-existing')->firstOrFail();
    expect(Notification::query()
        ->where('deduplication_key', "welcome:user:{$user->id}")
        ->count())->toBe(1);
});

test('google login allows ios client id', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'test-ios-client-id',
            'sub' => 'google-456',
            'name' => 'iOS User',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/auth/google', [
        'token' => 'valid-ios-token',
    ]);

    $response->assertStatus(200);
    expect($response['data']['user']['name'])->toBe('iOS User');
});

test('google login fails on invalid audience', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'invalid-client-id',
            'sub' => 'google-789',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/auth/google', [
        'token' => 'invalid-aud-token',
    ]);

    $response->assertStatus(401);
});
