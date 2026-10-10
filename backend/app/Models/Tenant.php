<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'trial_ends_at' => 'datetime'];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class)->withoutGlobalScopes();
    }

    public function enabledModules(): array
    {
        return TenantModule::withoutGlobalScopes()->where('tenant_id', $this->id)->where('enabled', true)
            ->pluck('module_key')->all();
    }

    public function isOperational(): bool
    {
        return in_array($this->status, ['trial', 'active', 'grace'], true);
    }
}
