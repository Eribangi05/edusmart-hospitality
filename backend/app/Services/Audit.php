<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\Tenancy;

class Audit
{
    public static function log(string $action, ?string $entity = null, ?string $entityId = null, $before = null, $after = null, ?string $tenantId = null, ?string $userId = null): void
    {
        $req = request();
        $token = $req?->user()?->currentAccessToken();
        AuditLog::create([
            'tenant_id' => $tenantId ?? app(Tenancy::class)->id(),
            'user_id' => $userId ?? $req?->user()?->id,
            'device_id' => $token?->device_id ?? null,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip' => $req?->ip(),
        ]);
    }
}
