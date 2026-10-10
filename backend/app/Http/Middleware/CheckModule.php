<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

class CheckModule
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (! in_array($module, app(Tenancy::class)->get()->enabledModules(), true)) {
            throw new ApiError('module_disabled', "The $module module is not enabled for this company.", 403);
        }

        return $next($request);
    }
}
