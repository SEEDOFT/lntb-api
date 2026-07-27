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
class DeviceStatus extends Model
{
    public const string AVAILABLE = 'available';

    public const int ID_AVAILABLE = 1;

    public const string ACTIVE = 'active';

    public const int ID_ACTIVE = 2;

    public const string SUSPENDED = 'suspended';

    public const int ID_SUSPENDED = 3;

    public const string MAINTENANCE = 'maintenance';

    public const int ID_MAINTENANCE = 4;

    public const string RETIRED = 'retired';

    public const int ID_RETIRED = 5;

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
