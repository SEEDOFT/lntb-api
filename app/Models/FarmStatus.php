<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('farm_statuses', key: 'id', keyType: 'int')]
final class FarmStatus extends Model {}
