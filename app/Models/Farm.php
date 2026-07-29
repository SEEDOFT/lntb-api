<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

#[Table('farms', key: 'id', keyType: 'int')]
#[Fillable(['owner_user_id', 'farm_status_id', 'name', 'location'])]
final class Farm extends Model
{
    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'farm_status_id' => 'integer',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(FarmStatus::class, 'farm_status_id');
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }
}
