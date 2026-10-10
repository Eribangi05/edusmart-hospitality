<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['modules' => 'array', 'limits' => 'array', 'is_active' => 'boolean'];
    }
}
