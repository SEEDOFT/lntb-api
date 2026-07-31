<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TestDataSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed();
    app(TestDataSeeder::class)->seed();
});

it('seeds the complete API dataset idempotently', function (): void {
    $this->artisan('app:seed-test-data')->assertSuccessful();
    $this->artisan('app:seed-test-data')->assertSuccessful();

    $owner = User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->firstOrFail();
    $farmId = DB::table('farms')->where('owner_user_id', $owner->id)->value('id');

    expect(DB::table('farms')->where('owner_user_id', $owner->id)->count())->toBe(1)
        ->and(DB::table('farm_devices')->where('farm_id', $farmId)->count())->toBe(4)
        ->and(DB::table('sensor_readings')->where('farm_id', $farmId)->count())->toBe(4)
        ->and(DB::table('usage_records')->where('farm_id', $farmId)->count())->toBe(7)
        ->and(DB::table('device_controls')->where('user_id', $owner->id)->count())->toBe(4);
});

it('can seed the complete dataset through DatabaseSeeder when enabled', function (): void {
    app(TestDataSeeder::class)->reset();
    config()->set('test_data.seed_with_database', true);

    $this->seed();

    expect(User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->exists())->toBeTrue();
});

it('resets only the dedicated test dataset', function (): void {
    $unrelated = User::factory()->create(['email' => 'unrelated@example.com']);
    $this->artisan('app:seed-test-data', ['--reset' => true])->assertSuccessful();

    expect(User::query()->whereKey($unrelated->id)->exists())->toBeTrue()
        ->and(User::query()
            ->where('country_code', TestDataSeeder::COUNTRY_CODE)
            ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
            ->exists())->toBeTrue();
});

it('refuses the test data command in production', function (): void {
    $originalEnvironment = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        $this->artisan('app:seed-test-data')
            ->expectsOutput('Test data seeding is available only in local and testing environments.')
            ->assertFailed();
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
});

it('returns the seeded dashboard through authenticated APIs', function (): void {
    $owner = User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->firstOrFail();
    $farmId = DB::table('farms')->where('owner_user_id', $owner->id)->value('id');
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/farms/{$farmId}/dashboard")
        ->assertOk()
        ->assertJsonPath('data.farm.name', TestDataSeeder::FARM_NAME)
        ->assertJsonCount(4, 'data.metrics')
        ->assertJsonCount(4, 'data.devices')
        ->assertJsonCount(4, 'data.activity')
        ->assertJsonCount(1, 'data.warnings')
        ->assertJsonPath('data.usage.water_cubic_meters', 0.42)
        ->assertJsonPath('data.assistant.question', 'What should I check today?')
        ->assertJsonPath('data.assistant.answer', 'សំណើមក្នុងផ្ទះកញ្ចក់ A ខ្ពស់បន្តិច។ សូមពិនិត្យលំហូរខ្យល់ និងរក្សាកាលវិភាគកង្ហារឱ្យដំណើរការមុនកំដៅពេលរសៀល។')
        ->assertJsonPath('data.online_device_count', 4)
        ->assertJsonMissing(['demo'])
        ->assertJsonMissing(['prototype']);
});

it('filters dashboard metrics, usage, and activity by a requested period', function (): void {
    $owner = User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->firstOrFail();
    $farmId = DB::table('farms')->where('owner_user_id', $owner->id)->value('id');
    Sanctum::actingAs($owner);

    DB::table('sensor_readings')->where('farm_id', $farmId)->update([
        'recorded_at' => now()->subDays(20),
    ]);
    DB::table('device_controls')
        ->whereIn('device_id', DB::table('farm_devices')->where('farm_id', $farmId)->pluck('device_id'))
        ->update(['requested_at' => now()->subDays(20)]);

    $this->getJson("/api/v1/farms/{$farmId}/dashboard?period=today")
        ->assertOk()
        ->assertJsonCount(0, 'data.metrics')
        ->assertJsonCount(0, 'data.activity')
        ->assertJsonPath('data.usage.water_cubic_meters', 0.42);

    $this->getJson("/api/v1/farms/{$farmId}/dashboard?period=30d")
        ->assertOk()
        ->assertJsonCount(4, 'data.metrics')
        ->assertJsonCount(4, 'data.activity')
        ->assertJsonPath('data.usage.water_cubic_meters', 0.42);

    $this->getJson("/api/v1/farms/{$farmId}/dashboard?period=unsupported")
        ->assertOk()
        ->assertJsonCount(4, 'data.metrics');
});

it('includes device type on control history records', function (): void {
    $owner = User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->firstOrFail();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/controls')
        ->assertOk()
        ->assertJsonPath('data.0.device.type.code', 'fan');
});

it('returns assistant unavailable when the assistant service is not configured', function (): void {
    config()->set('services.farm_assistant.endpoint', null);
    $owner = User::query()
        ->where('country_code', TestDataSeeder::COUNTRY_CODE)
        ->where('phone_number', TestDataSeeder::PHONE_NUMBER)
        ->firstOrFail();
    $farmId = DB::table('farms')->where('owner_user_id', $owner->id)->value('id');
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/farms/{$farmId}/assistant/query", [
        'question' => 'What changed?',
    ])->assertStatus(503)
        ->assertJsonPath('status.error_code', 'ASSISTANT_UNAVAILABLE');
});

it('does not disclose the seeded dashboard to another user', function (): void {
    $farmId = DB::table('farms')->where('name', TestDataSeeder::FARM_NAME)->value('id');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/farms/{$farmId}/dashboard")
        ->assertForbidden()
        ->assertJsonPath('status.error_code', 'FARM_ACCESS_DENIED');
});

it('requires authentication for the seeded dashboard', function (): void {
    $farmId = DB::table('farms')->where('name', TestDataSeeder::FARM_NAME)->value('id');

    $this->getJson("/api/v1/farms/{$farmId}/dashboard")->assertUnauthorized();
});
