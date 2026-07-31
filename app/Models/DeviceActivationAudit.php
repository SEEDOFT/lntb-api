<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

#[Table('device_activation_audits', key: 'id', keyType: 'int')]
#[Fillable([
    'device_activation_id',
    'device_id',
    'user_id',
    'event_code',
    'actor_identifier',
    'metadata',
    'occurred_at',
])]
final class DeviceActivationAudit extends Model
{
    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'device_activation_id' => 'integer',
            'device_id' => 'integer',
            'user_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
