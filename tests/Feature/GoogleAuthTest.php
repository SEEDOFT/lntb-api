<?php

use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
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
        ], 200)
    ]);

    $response = $this->postJson('/api/v1/auth/google', [
        'token' => 'valid-google-token',
        'device_name' => 'Test Device'
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['data' => ['user', 'token', 'expires_at']]);
    
    expect($response['data']['user']['name'])->toBe('Google User');
    expect(User::where('google_id', 'google-123')->exists())->toBeTrue();
});

test('google login allows ios client id', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'aud' => 'test-ios-client-id',
            'sub' => 'google-456',
            'name' => 'iOS User',
        ], 200)
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
        ], 200)
    ]);

    $response = $this->postJson('/api/v1/auth/google', [
        'token' => 'invalid-aud-token',
    ]);

    $response->assertStatus(401);
});
