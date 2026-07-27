<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $user_fcm_token_id
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_message
 * @property-read Notification $notification
 * @property-read UserFcmToken $fcmToken
 */
#[Fillable([
    'notification_id',
    'user_fcm_token_id',
    'queued_at',
    'sent_at',
    'failed_at',
    'failure_message',
])]
final class NotificationPushDelivery extends Model
{
    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'notification_id' => 'integer',
            'user_fcm_token_id' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /** @return BelongsTo<UserFcmToken, $this> */
    public function fcmToken(): BelongsTo
    {
        return $this->belongsTo(UserFcmToken::class, 'user_fcm_token_id');
    }
}
