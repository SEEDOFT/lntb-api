<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendFcmNotification;
use App\Models\Notification;
use App\Models\NotificationPushDelivery;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\DB;

final class NotificationDeliveryService
{
    public function createForNotification(Notification $notification): void
    {
        UserFcmToken::query()
            ->where('user_id', $notification->user_id)
            ->whereNull('revoked_at')
            ->whereNotNull('fcm_token')
            ->each(fn (UserFcmToken $token) => $this->createForToken($notification, $token));
    }

    public function createForToken(
        Notification $notification,
        UserFcmToken $token,
        bool $retryFailed = false,
    ): NotificationPushDelivery {
        $delivery = NotificationPushDelivery::query()->firstOrCreate([
            'notification_id' => $notification->id,
            'user_fcm_token_id' => $token->id,
        ]);

        if ($retryFailed && $delivery->sent_at === null && $delivery->failed_at !== null) {
            $delivery->forceFill([
                'queued_at' => null,
                'failed_at' => null,
                'failure_message' => null,
            ])->save();
        }

        $this->queue($delivery);

        return $delivery;
    }

    public function markSent(NotificationPushDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $locked = NotificationPushDelivery::query()
                ->lockForUpdate()
                ->find($delivery->id);

            if ($locked === null || $locked->sent_at !== null) {
                return;
            }

            $sentAt = now();
            $locked->forceFill([
                'sent_at' => $sentAt,
                'failed_at' => null,
                'failure_message' => null,
            ])->save();

            Notification::query()
                ->whereKey($locked->notification_id)
                ->whereNull('push_sent_at')
                ->update([
                    'push_sent_at' => $sentAt,
                    'push_failed_at' => null,
                    'push_failure_message' => null,
                ]);
        });
    }

    public function markFailed(
        int $deliveryId,
        string $message,
    ): void {
        DB::transaction(function () use ($deliveryId, $message): void {
            $delivery = NotificationPushDelivery::query()
                ->lockForUpdate()
                ->find($deliveryId);

            if ($delivery === null || $delivery->sent_at !== null) {
                return;
            }

            $delivery->forceFill([
                'failed_at' => now(),
                'failure_message' => $message,
            ])->save();

            $hasSuccessfulDelivery = NotificationPushDelivery::query()
                ->where('notification_id', $delivery->notification_id)
                ->whereNotNull('sent_at')
                ->exists();
            $hasPendingDelivery = NotificationPushDelivery::query()
                ->where('notification_id', $delivery->notification_id)
                ->whereNull('sent_at')
                ->whereNull('failed_at')
                ->exists();

            if (! $hasSuccessfulDelivery && ! $hasPendingDelivery) {
                Notification::query()
                    ->whereKey($delivery->notification_id)
                    ->update([
                        'push_failed_at' => now(),
                        'push_failure_message' => $message,
                    ]);
            }
        });
    }

    private function queue(NotificationPushDelivery $delivery): void
    {
        $queued = NotificationPushDelivery::query()
            ->whereKey($delivery->id)
            ->whereNull('queued_at')
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->update(['queued_at' => now()]);

        if ($queued === 1) {
            SendFcmNotification::dispatch($delivery->id);
        }
    }
}
