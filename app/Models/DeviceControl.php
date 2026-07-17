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
 * @property int $device_control_status_id
 * @property string $control_type
 * @property array|null $control_data
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property string|null $failure_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'user_id',
    'device_control_status_id',
    'control_type',
    'control_data',
    'requested_at',
    'completed_at',
    'failure_message',
])]
class DeviceControl extends Model
{
    use HasFactory;

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'device_id' => 'integer',
            'user_id' => 'integer',
            'device_control_status_id' => 'integer',
            'control_data' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
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

    /** @return BelongsTo<DeviceControlStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(DeviceControlStatus::class, 'device_control_status_id');
    }
}
