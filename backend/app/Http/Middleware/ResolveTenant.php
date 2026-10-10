<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Device;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

/** Derives the tenant from the authenticated token. Rejects revoked devices and suspended tenants. */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            throw new ApiError('unauthenticated', 'Unauthenticated.', 401);
        }
        $tenant = $user->tenant;
        if (! $tenant) {
            throw new ApiError('forbidden', 'This account has no company workspace.', 403);
        }
        app(Tenancy::class)->set($tenant);

        $deviceId = $user->currentAccessToken()?->device_id;
        if ($deviceId) {
            $device = Device::find($deviceId);
            if (! $device || $device->revoked_at) {
                $user->currentAccessToken()->delete();
                throw new ApiError('device_revoked', 'This device has been revoked.', 401);
            }
            if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinutes(5))) {
                $device->forceFill(['last_seen_at' => now()])->save();
            }
        }

        if (! $tenant->isOperational() && ! $request->isMethod('GET') && ! $request->is('api/v1/billing/*')) {
            throw new ApiError('subscription_suspended', 'Subscription suspended. Read-only access.', 402);
        }

        return $next($request);
    }
}
