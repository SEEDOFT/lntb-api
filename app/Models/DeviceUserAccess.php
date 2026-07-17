<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int $user_id
 * @property int $granted_by_user_id
 * @property int $device_access_status_id
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'user_id',
    'granted_by_user_id',
    'device_access_status_id',
    'granted_at',
    'revoked_at',
])]
class DeviceUserAccess extends Model
{
    use HasFactory;

    protected $table = 'device_user_access';

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'device_id' => 'integer',
            'user_id' => 'integer',
            'granted_by_user_id' => 'integer',
            'device_access_status_id' => 'integer',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return BelongsTo<DeviceAccessStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(DeviceAccessStatus::class, 'device_access_status_id');
    }
}
