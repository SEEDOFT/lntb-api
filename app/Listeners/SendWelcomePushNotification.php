<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\NotificationType;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendWelcomePushNotification implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || empty($user->fcm_token)) {
            return;
        }

        $notificationService = app(NotificationService::class);
        $notificationService->sendToUser(
            user: $user,
            title: 'Welcome to LNTB!',
            body: 'Your account has been successfully created. Enjoy the smart farming experience!',
            typeCode: NotificationType::WELCOME
        );
    }
}
