<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every tenant-owned model uses this. Queries are scoped to the current tenant
 * automatically; with no tenant in context nothing is returned (fail closed).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $id = app(Tenancy::class)->id();
            $table = $builder->getModel()->getTable();
            $id === null
                ? $builder->whereRaw('1 = 0')
                : $builder->where("$table.tenant_id", $id);
        });

        static::creating(function ($model) {
            $id = app(Tenancy::class)->id();
            if ($id !== null) {
                $model->tenant_id = $id; // always overrides client-supplied value
            } elseif (! $model->tenant_id) {
                throw new \LogicException('No tenant in context');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
