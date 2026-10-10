<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

/** Platform console guard: platform admins only, and only with two-factor enabled. */
class PlatformAdmin
{
    public function handle(Request $request, Closure $next, string $mode = 'full')
    {
        $u = $request->user();
        if (! $u || ! $u->is_platform_admin || ! $u->is_active) {
            throw new ApiError('forbidden', 'Platform administrators only.', 403);
        }
        if ($mode !== 'basic' && ! $u->hasTwoFactor()) {
            throw new ApiError('two_factor_required', 'Enable two-factor authentication to use the platform console.', 403);
        }

        return $next($request);
    }
}
