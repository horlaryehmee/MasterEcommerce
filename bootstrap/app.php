<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// Fresh upload, first visit: create .env (new APP_KEY) so the setup wizard at /install can run.
if (PHP_SAPI !== 'cli' && ! is_file(dirname(__DIR__).'/.env')) {
    \App\Support\Installer::prepareEnv(dirname(__DIR__));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Not installed yet → everything goes to the setup wizard (/install).
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);
        // Store set up on https:// → always use it (the login cookie is HTTPS-only).
        // Appended so it runs after TrustProxies: behind a proxy, https must be recognised first.
        $middleware->append(\App\Http\Middleware\RedirectToHttps::class);

        // cPanel hosts often handle HTTPS in a proxy in front of PHP (LiteSpeed, Cloudflare).
        // Trust only its "this visit was HTTPS" headers, not client IP/host headers, so IP-based
        // limits such as login throttling can't be bypassed with a forged X-Forwarded-For.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);

        // Global (runs before routing), so redirects also work for URLs that no longer exist.
        $middleware->append(\App\Http\Middleware\HandleRedirects::class);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // During an update the site is in maintenance mode; the admin's update page,
        // its steps and login must keep working so the update can finish.
        $middleware->preventRequestsDuringMaintenance(except: ['admin/updates', 'admin/updates/*', 'admin/login']);

        $middleware->alias(['shop' =>\App\Http\Middleware\EnsureShopEnabled::class]);

        // The stats beacon uses sendBeacon (no CSRF token) and sets a plain, unencrypted visitor cookie.
        $middleware->validateCsrfTokens(except: ['hello']);
        $middleware->encryptCookies(except: ['nx_vid']);

        // A space is a valid thousands separator, so don't trim these fields.
        TrimStrings::except(['thousands_separator', 'decimal_separator', 'password', 'password_confirmation', 'current_password']);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Friendly, branded error pages (the debug page is still shown locally for 500s).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // Before installation there is no database for the branded error page.
            if (! is_file(base_path(\App\Support\Installer::LOCK)) && ! app()->runningUnitTests()) {
                return $response;
            }
            $status = $response->getStatusCode();
            $friendly = in_array($status, [403, 404, 419, 429, 503], true)
                || ($status === 500 && ! config('app.debug'));

            if (! $friendly || $request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
