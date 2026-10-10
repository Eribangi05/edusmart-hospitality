<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for all tenant business records: uuid keys (client-generatable for offline),
 * tenant scoping, soft deletes, a version counter for sync and a JSON meta column
 * that stores tenant-defined custom fields.
 */
abstract class TenantModel extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $guarded = ['tenant_id', 'version', 'created_at', 'updated_at', 'deleted_at'];

    /** Columns cast to array. */
    protected array $json = [];

    /** Columns cast to date (Y-m-d). */
    protected array $dates = [];

    protected function casts(): array
    {
        $c = ['meta' => 'array'];
        foreach ($this->json as $col) {
            $c[$col] = 'array';
        }
        foreach ($this->dates as $col) {
            $c[$col] = 'date:Y-m-d';
        }

        return $c;
    }

    /** Allow clients to supply the primary key (offline-created records). */
    public function getIncrementing()
    {
        return false;
    }

    protected static function booted(): void
    {
        static::updating(function (Model $m) {
            $m->version = ($m->getOriginal('version') ?? 1) + 1;
        });
    }
}
