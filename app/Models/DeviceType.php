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
class DeviceType extends Model
{
    public const string SMART_FARM_CONTROLLER = 'smart_farm_controller';

    public const string CAMERA = 'camera';

    public const string WATER_ENERGY_METER = 'water_energy_meter';

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
