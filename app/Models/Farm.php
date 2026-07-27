<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_user_id', 'farm_status_id', 'name', 'location'])]
final class Farm extends Model
{
    protected function casts(): array
    {
        return ['owner_user_id' => 'integer', 'farm_status_id' => 'integer'];
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
