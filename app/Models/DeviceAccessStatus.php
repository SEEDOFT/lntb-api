<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('device_access_statuses', key: 'id', keyType: 'int')]
#[Fillable(['code', 'name', 'description'])]
class DeviceAccessStatus extends Model
{
    public const string ACTIVE = 'active';

    public const int ID_ACTIVE = 1;

    public const string REVOKED = 'revoked';

    public const int ID_REVOKED = 2;

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'code' => 'string',
            'name' => 'string',
            'description' => 'string',
        ];
    }
}
