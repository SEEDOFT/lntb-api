<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\FirebaseChannel;
use App\Models\NotificationType;
use Illuminate\Notifications\Notification;

final class WelcomeNotification extends Notification
{
    public const string TITLE = 'Welcome to LNTB!';

    public const string BODY = 'Your account has been successfully created. Enjoy the smart farming experience!';

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title = self::TITLE,
        public readonly string $body = self::BODY,
        public readonly array $data = [],
    ) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [FirebaseChannel::class];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    public function typeCode(): string
    {
        return NotificationType::WELCOME;
    }
}
