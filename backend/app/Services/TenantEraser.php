<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/** Permanently removes a company and everything it owns (right to erasure / contract end). */
class TenantEraser
{
    public function erase(Tenant $tenant): array
    {
        return DB::transaction(function () use ($tenant) {
            $userIds = DB::table('users')->where('tenant_id', $tenant->id)->pluck('id');
            DB::table('personal_access_tokens')->whereIn('tokenable_id', $userIds)->delete();
            $audit = DB::table('audit_logs')->where('tenant_id', $tenant->id)->delete();
            $slug = $tenant->slug;
            $tenant->forceDelete(); // FKs cascade to every tenant-owned table
            // keep proof of the erasure itself without any customer data
            AuditLog::create(['tenant_id' => null, 'user_id' => null, 'action' => 'platform.tenant_erased', 'entity' => 'tenant', 'entity_id' => $tenant->id, 'after' => ['slug' => $slug]]);

            return ['users' => $userIds->count(), 'audit_rows' => $audit];
        });
    }
}
