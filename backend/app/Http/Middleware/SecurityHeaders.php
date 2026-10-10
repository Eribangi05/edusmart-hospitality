<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
        if ($request->is('api/*')) {
            $h->set('Cache-Control', 'no-store');
        } elseif (! app()->environment('local')) {
            // Alpine evaluates expressions at runtime, hence 'unsafe-eval'. Everything else is same-origin.
            $h->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
            if ($request->isSecure()) {
                $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }
}
