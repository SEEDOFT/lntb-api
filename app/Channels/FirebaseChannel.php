<?php

declare(strict_types=1);

namespace App\Channels;

use App\Models\UserFcmToken;
use App\Notifications\WelcomeNotification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

final class FirebaseChannel
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function send(UserFcmToken $notifiable, WelcomeNotification $notification): void
    {
        $token = $notifiable->routeNotificationForFirebase();

        if ($token === null) {
            return;
        }

        $payload = $notification->toFirebase($notifiable);
        $message = CloudMessage::new()
            ->withToken($token)
            ->withNotification(FirebaseNotification::create(
                $payload['title'],
                $payload['body'],
            ))
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'lntb_notifications',
                    'sound' => 'default',
                    'notification_count' => max(1, (int) ($payload['data']['unread_count'] ?? 1)),
                ],
            ]))
            ->withApnsConfig(
                ApnsConfig::new()
                    ->withImmediatePriority()
                    ->withDefaultSound()
                    ->withBadge(max(1, (int) ($payload['data']['unread_count'] ?? 1))),
            );

        if ($payload['data'] !== []) {
            $message = $message->withData($payload['data']);
        }

        $this->messaging->send($message);
    }
}
