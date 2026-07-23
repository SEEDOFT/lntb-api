<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'description'])]
class UserStatus extends Model
{
    public const string ACTIVE = 'active';

    public const int ID_ACTIVE = 1;

    public const string SUSPENDED = 'suspended';

    public const int ID_SUSPENDED = 2;

    public const string CLOSED = 'closed';

    public const int ID_CLOSED = 3;

    /** @return array<string, mixed> */
    #[\Override]
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
