<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendWelcomePushNotification implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof \App\Models\User || empty($user->fcm_token)) {
            return;
        }

        $notificationService = app(\App\Services\NotificationService::class);
        $notificationService->sendToUser(
            $user,
            'Welcome to LNTB!',
            'Your account has been successfully created. Enjoy the smart farming experience!',
            [],
            \App\Models\NotificationType::WELCOME
        );
    }
}
