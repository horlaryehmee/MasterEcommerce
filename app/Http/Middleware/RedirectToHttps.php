<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the store's address (APP_URL) is https://, send plain http:// page visits there.
 * The login cookie is HTTPS-only, so on http:// the browser drops it and signing in
 * silently fails. GET and HEAD are redirected. Other methods require a secure connection.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = rtrim((string) config('app.url'), '/');
        if (! $request->secure() && str_starts_with($url, 'https://')) {
            if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
                abort(403, 'Use HTTPS for this request.');
            }

            return redirect()->to($url.'/'.ltrim($request->getPathInfo(), '/').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        return $next($request);
    }
}
