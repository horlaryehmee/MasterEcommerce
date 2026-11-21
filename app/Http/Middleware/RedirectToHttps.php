<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the store's address (APP_URL) is https://, send plain http:// page visits there.
 * The login cookie is HTTPS-only, so on http:// the browser drops it and signing in
 * silently fails. Only GET/HEAD are redirected (a POST would lose its data).
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure()
            && str_starts_with((string) config('app.url'), 'https://')
            && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
