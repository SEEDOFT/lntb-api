<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property string|null $google_id
 * @property string $password
 * @property string|null $fcm_token
 * @property int $user_status_id
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'country_code', 'phone_number', 'google_id', 'password', 'user_status_id', 'fcm_token'])]
#[Hidden(['password', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'user_status_id' => 'integer',
            'phone_verified_at' => 'datetime',
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
}
