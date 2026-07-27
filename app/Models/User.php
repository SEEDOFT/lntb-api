<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $country_code
 * @property string $phone_number
 * @property string|null $email
 * @property string|null $google_id
 * @property string $password
 * @property int $user_status_id
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, UserFcmToken> $fcmTokens
 */
#[Fillable(['name', 'country_code', 'phone_number', 'email', 'google_id', 'password', 'user_status_id', 'email_verified_at'])]
#[Hidden(['password', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @return HasMany<UserFcmToken, $this> */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'user_status_id' => 'integer',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<UserStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'user_status_id', 'id');
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'owner_user_id', 'id');
    }

    /** @return HasMany<DeviceUserAccess, $this> */
    public function sharedDeviceAccess(): HasMany
    {
        return $this->hasMany(DeviceUserAccess::class, 'user_id');
    }

    /**
     * Scope a query to only include active users.
     *
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_status_id', UserStatus::ID_ACTIVE);
    }

    /**
     * Scope a query to filter by user status ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param int $statusId
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeStatus(\Illuminate\Database\Eloquent\Builder $query, int $statusId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_status_id', $statusId);
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return $this->user_status_id === UserStatus::ID_ACTIVE;
    }
}
