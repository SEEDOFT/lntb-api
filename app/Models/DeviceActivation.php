<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int $device_id
 * @property int $intended_user_id
 * @property string $public_reference
 * @property string $token_hash
 * @property string $prepared_by_identifier
 * @property int $failed_attempts
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $consumed_at
 */
#[Table('device_activations', key: 'id', keyType: 'int')]
#[Fillable([
    'device_id',
    'intended_user_id',
    'public_reference',
    'token_hash',
    'prepared_by_identifier',
    'failed_attempts',
    'issued_at',
    'expires_at',
    'revoked_at',
    'consumed_at',
])]
#[Hidden(['token_hash'])]
final class DeviceActivation extends Model
{
    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'intended_user_id' => 'integer',
            'failed_attempts' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /** @return BelongsTo<User, $this> */
    public function intendedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intended_user_id');
    }
}
