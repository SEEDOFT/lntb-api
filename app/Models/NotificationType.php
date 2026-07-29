<?php

declare(strict_types=1);

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
#[Table('notification_types', key: 'id', keyType: 'int')]
#[Fillable(['code', 'name', 'description'])]
class NotificationType extends Model
{
    public const string WELCOME = 'welcome';

    public const int ID_WELCOME = 1;

    public const string SYSTEM = 'system';

    public const int ID_SYSTEM = 2;

    public const string ALERT = 'alert';

    public const int ID_ALERT = 3;

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
