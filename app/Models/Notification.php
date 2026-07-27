<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $deduplication_key
 * @property int $notification_type_id
 * @property int $notification_status_id
 * @property string $title
 * @property string $body
 * @property array|null $data
 * @property Carbon|null $push_sent_at
 * @property Carbon|null $push_failed_at
 * @property string|null $push_failure_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read NotificationType $type
 * @property-read NotificationStatus $status
 * @property-read Collection<int, NotificationPushDelivery> $pushDeliveries
 */
#[Fillable([
    'user_id',
    'deduplication_key',
    'notification_type_id',
    'notification_status_id',
    'title',
    'body',
    'data',
    'push_sent_at',
    'push_failed_at',
    'push_failure_message',
])]
class Notification extends Model
{
    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'deduplication_key' => 'string',
            'notification_type_id' => 'integer',
            'notification_status_id' => 'integer',
            'title' => 'string',
            'body' => 'string',
            'data' => 'array',
            'push_sent_at' => 'datetime',
            'push_failed_at' => 'datetime',
            'push_failure_message' => 'string',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NotificationType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    /** @return BelongsTo<NotificationStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(NotificationStatus::class, 'notification_status_id');
    }

    /** @return HasMany<NotificationPushDelivery, $this> */
    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(NotificationPushDelivery::class);
    }
}
