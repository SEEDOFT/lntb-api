<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationPushDelivery;
use App\Notifications\WelcomeNotification;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Throwable;

final class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public ?int $deliveryId = null;

    /**
     * Retained temporarily so jobs serialized before the per-device delivery
     * migration can be converted safely by restarted workers.
     */
    public ?int $notificationId = null;

    public function __construct(int $deliveryId)
    {
        $this->deliveryId = $deliveryId;
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(
        NotificationDeliveryService $deliveries,
        NotificationService $notifications,
    ): void {
        if ($this->deliveryId === null) {
            $this->convertLegacyJob($deliveries);

            return;
        }

        $delivery = NotificationPushDelivery::query()
            ->with(['notification', 'fcmToken.user'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->sent_at !== null) {
            return;
        }

        $storedNotification = $delivery->notification;
        $token = $delivery->fcmToken;

        if (
            $storedNotification === null ||
            $token === null ||
            $token->user_id !== $storedNotification->user_id ||
            $token->routeNotificationForFirebase() === null
        ) {
            $deliveries->markFailed(
                $delivery->id,
                'The FCM registration token is unavailable.',
            );

            return;
        }

        try {
            $token->notifyNow(new WelcomeNotification(
                title: $storedNotification->title,
                body: $storedNotification->body,
                data: [
                    ...($storedNotification->data ?? []),
                    'notification_id' => (string) $storedNotification->id,
                    'type' => 'welcome',
                    'unread_count' => (string) $notifications->unreadCount($storedNotification->user_id),
                ],
            ));
        } catch (NotFound) {
            $token->forceFill([
                'fcm_token' => null,
                'revoked_at' => now(),
            ])->save();
            $deliveries->markFailed(
                $delivery->id,
                'The FCM registration token is no longer valid.',
            );

            Log::warning('FCM registration token was rejected.', [
                'notification_id' => $storedNotification->id,
                'user_id' => $token->user_id,
                'delivery_id' => $delivery->id,
            ]);

            return;
        }

        $deliveries->markSent($delivery);
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->deliveryId !== null) {
            app(NotificationDeliveryService::class)->markFailed(
                $this->deliveryId,
                'FCM delivery failed after all retry attempts.',
            );
        }

        Log::error('FCM notification job failed.', [
            'delivery_id' => $this->deliveryId,
            'legacy_notification_id' => $this->notificationId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    private function convertLegacyJob(NotificationDeliveryService $deliveries): void
    {
        if ($this->notificationId === null) {
            return;
        }

        $notification = Notification::query()->find($this->notificationId);
        if ($notification !== null && $notification->push_sent_at === null) {
            $deliveries->createForNotification($notification);
        }

        Log::info('Converted a legacy FCM notification job.', [
            'notification_id' => $this->notificationId,
        ]);
    }
}
