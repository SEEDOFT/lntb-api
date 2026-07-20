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

    public const string ACTIVE = 'active';

    public const string SUSPENDED = 'suspended';

    public const string MAINTENANCE = 'maintenance';

    public const string RETIRED = 'retired';

    private static array $idCache = [];

    public static function resolveId(string $code): int
    {
        if (! isset(self::$idCache[$code])) {
            self::$idCache[$code] = static::where('code', $code)->value('id');
        }

        return self::$idCache[$code];
    }

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
