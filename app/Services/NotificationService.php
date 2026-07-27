<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationStatus;
use App\Models\NotificationType;
use App\Models\User;

final class NotificationService
{
    public function unreadCount(int $userId): int
    {
        $unreadStatusId = NotificationStatus::query()
            ->where('code', NotificationStatus::UNREAD)
            ->valueOrFail('id');

        return Notification::query()
            ->where('user_id', $userId)
            ->where('notification_status_id', $unreadStatusId)
            ->count();
    }

    /**
     * Persist an in-app notification without coupling storage to delivery.
     *
     * @param  array<string, string>  $data
     */
    public function store(
        User $user,
        string $typeCode,
        string $title,
        string $body,
        array $data = [],
        ?string $deduplicationKey = null,
    ): Notification {
        $typeId = NotificationType::query()
            ->where('code', $typeCode)
            ->valueOrFail('id');
        $unreadStatusId = NotificationStatus::query()
            ->where('code', NotificationStatus::UNREAD)
            ->valueOrFail('id');

        $attributes = [
            'user_id' => $user->id,
            'deduplication_key' => $deduplicationKey,
            'notification_type_id' => $typeId,
            'notification_status_id' => $unreadStatusId,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
        ];

        if ($deduplicationKey === null) {
            return Notification::query()->create($attributes);
        }

        return Notification::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            $attributes,
        );
    }
}
