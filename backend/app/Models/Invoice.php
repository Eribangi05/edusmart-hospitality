<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lines' => 'array', 'paid_at' => 'datetime', 'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d'];
    }
}
