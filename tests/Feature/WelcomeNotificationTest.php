<?php

declare(strict_types=1);

use App\Jobs\SendFcmNotification;
use App\Listeners\CreateWelcomeNotification;
use App\Models\Notification;
use App\Models\NotificationPushDelivery;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserStatus;
use App\Services\FcmTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function welcomeNotificationUser(int $tokenCount = 1): User
{
    $user = User::factory()->create([
        'user_status_id' => UserStatus::ID_ACTIVE,
    ]);

    for ($number = 1; $number <= $tokenCount; $number++) {
        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'device_key' => "device-{$user->id}-{$number}",
            'fcm_token' => "valid-fcm-token-{$user->id}-{$number}",
            'platform' => 'android',
        ]);
    }

    return $user;
}

function createWelcomeFor(User $user): Notification
{
    app(CreateWelcomeNotification::class)->handle(new Registered($user));

    return Notification::query()
        ->where('deduplication_key', "welcome:user:{$user->id}")
        ->firstOrFail();
}

it('stores one welcome and queues one delivery for every active device', function (): void {
    Queue::fake();
    $user = welcomeNotificationUser(2);
    $listener = app(CreateWelcomeNotification::class);

    $listener->handle(new Registered($user));
    $listener->handle(new Registered($user));

    expect(Notification::query()
        ->where('deduplication_key', "welcome:user:{$user->id}")
        ->count())->toBe(1)
        ->and(NotificationPushDelivery::query()->count())->toBe(2);
    Queue::assertPushed(SendFcmNotification::class, 2);
});

it('queues a previously unsent welcome when the first token arrives late', function (): void {
    Queue::fake();
    $user = welcomeNotificationUser(0);
    $welcome = createWelcomeFor($user);

    Queue::assertNothingPushed();

    app(FcmTokenService::class)->sync(
        user: $user,
        deviceKey: 'late-device',
        fcmToken: 'late-token',
        platform: 'android',
    );

    expect($welcome->pushDeliveries()->count())->toBe(1);
    Queue::assertPushed(SendFcmNotification::class, 1);
});

it('converts a legacy notification-id job into per-device deliveries', function (): void {
    Queue::fake();
    $storedNotification = createWelcomeFor(welcomeNotificationUser());
    NotificationPushDelivery::query()->delete();
    Queue::fake();

    $legacyJob = (new ReflectionClass(SendFcmNotification::class))
        ->newInstanceWithoutConstructor();
    $legacyJob->notificationId = $storedNotification->id;

    app()->call([$legacyJob, 'handle']);

    expect($storedNotification->pushDeliveries()->count())->toBe(1);
    Queue::assertPushed(SendFcmNotification::class, 1);
});

it('sends one device delivery and records aggregate success', function (): void {
    Queue::fake();
    $storedNotification = createWelcomeFor(welcomeNotificationUser());
    $delivery = $storedNotification->pushDeliveries()->firstOrFail();

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')
        ->once()
        ->with(Mockery::type(CloudMessage::class))
        ->andReturn(['name' => 'projects/test/messages/1']);
    app()->instance(Messaging::class, $messaging);

    app()->call([new SendFcmNotification($delivery->id), 'handle']);

    expect($delivery->refresh()->sent_at)->not->toBeNull()
        ->and($storedNotification->refresh()->push_sent_at)->not->toBeNull()
        ->and($storedNotification->push_failed_at)->toBeNull();
});

it('records exhausted delivery without failing a successful sibling device', function (): void {
    Queue::fake();
    $storedNotification = createWelcomeFor(welcomeNotificationUser(2));
    $deliveries = $storedNotification->pushDeliveries()->orderBy('id')->get();
    $deliveries->first()->forceFill(['sent_at' => now()])->save();
    $storedNotification->forceFill(['push_sent_at' => now()])->save();

    $job = new SendFcmNotification($deliveries->last()->id);
    $job->failed(new RuntimeException('Temporary Firebase failure.'));

    expect($deliveries->last()->refresh()->failed_at)->not->toBeNull()
        ->and($storedNotification->refresh()->push_sent_at)->not->toBeNull()
        ->and($storedNotification->push_failed_at)->toBeNull();
});

it('revokes only the rejected device token without retrying', function (): void {
    Queue::fake();
    $storedNotification = createWelcomeFor(welcomeNotificationUser(2));
    $delivery = $storedNotification->pushDeliveries()->orderBy('id')->firstOrFail();
    $token = $delivery->fcmToken;

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')
        ->once()
        ->andThrow(NotFound::becauseTokenNotFound((string) $token->fcm_token));
    app()->instance(Messaging::class, $messaging);

    app()->call([new SendFcmNotification($delivery->id), 'handle']);

    expect($token->refresh()->fcm_token)->toBeNull()
        ->and($token->revoked_at)->not->toBeNull()
        ->and(UserFcmToken::query()
            ->where('user_id', $storedNotification->user_id)
            ->whereNotNull('fcm_token')
            ->count())->toBe(1)
        ->and($delivery->refresh()->failed_at)->not->toBeNull();
});
