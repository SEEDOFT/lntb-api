<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $notification_type_id
 * @property int $notification_status_id
 * @property string $title
 * @property string $body
 * @property array|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property-read User $user
 * @property-read NotificationType $type
 * @property-read NotificationStatus $status
 */
#[Fillable(['user_id', 'notification_type_id', 'notification_status_id', 'title', 'body', 'data'])]
class Notification extends Model
{
    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'notification_type_id' => 'integer',
            'notification_status_id' => 'integer',
            'title' => 'string',
            'body' => 'string',
            'data' => 'array',
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
}
