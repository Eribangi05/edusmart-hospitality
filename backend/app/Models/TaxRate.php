<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['inclusive' => 'boolean', 'is_active' => 'boolean'];
    }
}
