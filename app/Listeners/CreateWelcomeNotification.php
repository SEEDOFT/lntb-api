<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class CreateWelcomeNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationDeliveryService $deliveries,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $welcome = new WelcomeNotification;
        $storedNotification = $this->notifications->store(
            user: $event->user,
            typeCode: $welcome->typeCode(),
            title: $welcome->title,
            body: $welcome->body,
            data: $welcome->data,
            deduplicationKey: "welcome:user:{$event->user->id}",
        );

        if ($storedNotification->wasRecentlyCreated) {
            $this->deliveries->createForNotification($storedNotification);
        }
    }
}
