<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        foreach ($permissions as $p) {
            if ($user && $user->can2($p)) {
                return $next($request);
            }
        }
        throw new ApiError('forbidden', 'You do not have permission to do this.', 403, ['required' => $permissions]);
    }
}
