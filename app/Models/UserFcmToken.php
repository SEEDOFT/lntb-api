<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_key
 * @property string|null $fcm_token
 * @property string|null $platform
 * @property string|null $device_name
 * @property string|null $app_version
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'device_key',
    'fcm_token',
    'platform',
    'device_name',
    'app_version',
    'last_used_at',
    'revoked_at',
])]
final class UserFcmToken extends Model
{
    use Notifiable;

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function routeNotificationForFirebase(): ?string
    {
        $token = trim((string) $this->fcm_token);

        return $this->revoked_at === null && $token !== '' ? $token : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<NotificationPushDelivery, $this> */
    public function pushDeliveries(): HasMany
    {
        return $this->hasMany(NotificationPushDelivery::class);
    }
}
