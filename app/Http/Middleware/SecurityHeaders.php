<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Basic hardening headers suitable for any shared host. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // Safe for every page: no <base> hijacking, no plugins, no framing by other sites.
        if (! $response->headers->has('Content-Security-Policy')) {
            $policy = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";
            if (\App\Support\Tracking::privatePage($request) && ! is_file(public_path('hot'))) {
                $policy .= "; script-src 'self'; form-action 'self'";
            }
            $response->headers->set('Content-Security-Policy', $policy);
        }
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // HTTPS sites: browsers refuse plain-http connections for a year (stops SSL-stripping).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            // Customer details and orders must not be kept in browser or proxy caches.
            if (! $response->headers->has('Content-Disposition')) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }
        }

        // Order pages hold personal details: never cache them in shared caches.
        if (\App\Support\Tracking::privatePage($request)) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
