<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_type_id
 * @property int $device_status_id
 * @property int|null $owner_user_id
 * @property string|null $name
 * @property string $serial_number
 * @property string $mac_address
 * @property string $claim_code_hash
 * @property string|null $firmware_version
 * @property Carbon|null $claim_code_used_at
 * @property Carbon|null $claimed_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_type_id',
    'device_status_id',
    'owner_user_id',
    'name',
    'serial_number',
    'mac_address',
    'claim_code_hash',
    'firmware_version',
    'claim_code_used_at',
    'claimed_at',
    'last_seen_at',
])]
#[Hidden(['claim_code_hash'])]
class Device extends Model
{
    use HasFactory;

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'device_type_id' => 'integer',
            'device_status_id' => 'integer',
            'owner_user_id' => 'integer',
            'claim_code_used_at' => 'datetime',
            'claimed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'id');
    }

    /** @return BelongsTo<DeviceStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(DeviceStatus::class, 'device_status_id', 'id');
    }

    /** @return BelongsTo<DeviceType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class, 'device_type_id', 'id');
    }

    /** @return HasMany<DeviceUserAccess, $this> */
    public function accessRecords(): HasMany
    {
        return $this->hasMany(DeviceUserAccess::class, 'device_id');
    }

    /** @return HasMany<DeviceControl, $this> */
    public function controls(): HasMany
    {
        return $this->hasMany(DeviceControl::class, 'device_id');
    }
}
