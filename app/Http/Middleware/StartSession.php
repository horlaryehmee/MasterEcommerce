<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's session middleware, remembering only real pages as "the previous page".
 *
 * back() after a form uses that memory whenever the browser sends no Referer. Laravel
 * remembers every plain GET, including things the browser fetches by itself or that open
 * as downloads: the service worker, manifests, CSV exports, receipt files, database
 * backups, email previews… A save could then "go back" to /sw.js or start a download.
 * Here only successful HTML pages count; everything else leaves the memory unchanged.
 */
class StartSession extends Middleware
{
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        return parent::handleStatefulRequest($request, $session, function (Request $request) use ($next) {
            $response = $next($request);
            if (! self::isPage($response)) {
                $request->attributes->set('not_a_page', true);
            }

            return $response;
        });
    }

    protected function storeCurrentUrl(Request $request, $session)
    {
        if (! $request->attributes->get('not_a_page')) {
            parent::storeCurrentUrl($request, $session);
        }
    }

    /** A successful HTML page (not a file, download, JSON, redirect or error page). */
    public static function isPage(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && ! $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            && ! $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            && ! $response->headers->has('Content-Disposition')
            && str_starts_with(strtolower((string) $response->headers->get('Content-Type', 'text/html')), 'text/html');
    }
}
