<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireSetupOwner
{
    public function handle(Request $request, Closure $next)
    {
        $loopback = in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true);
        abort_unless($request->isSecure() || $loopback, 403, 'Use HTTPS to access the installer.');
        if ($request->routeIs('install.unlock')) {
            return $next($request);
        }

        $secret = (string) config('catalog.setup_token');
        if (strlen($secret) >= 32 && hash_equals(hash('sha256', $secret), (string) $request->session()->get('install.owner'))) {
            return $next($request);
        }

        return response()->view('install.unlock', [], 403)->header('Cache-Control', 'no-store');
    }
}
