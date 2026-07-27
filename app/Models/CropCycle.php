<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CropCycle extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date'];
    }
}
