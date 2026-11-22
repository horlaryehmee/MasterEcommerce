<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use App\Support\LaunchPage;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While "Coming soon" or "Maintenance" is on (Website Settings → Coming soon & maintenance),
 * visitors get the branded page instead of the shop:
 *  - HTTP 503 + Retry-After, so search engines know it's temporary and keep the rankings;
 *  - signed-in admins see the shop as usual (with a reminder bar);
 *  - ?preview=<secret> lets someone else see the shop (remembered for 30 days);
 *  - order pages, Paystack callbacks/webhooks, the admin and background files keep working.
 */
class ShowLaunchPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(LaunchPage::ALWAYS_OPEN)) {
            return $next($request);
        }

        $s = BusinessSetting::current();

        // Admin's "Preview" button: see the page exactly as visitors will, even while it's off.
        if ($request->user() && $request->query('launch_preview') === '1') {
            return self::render($s, 200);
        }

        if (! LaunchPage::active($s)) {
            return $next($request);
        }

        // Secret preview link: remember it, then show the same page without the code in the address.
        $token = (string) $s->launch_token;
        if ($request->isMethod('GET') && strlen($token) >= 20 && hash_equals($token, (string) $request->query('preview'))) {
            $clean = $request->fullUrlWithoutQuery('preview');

            return redirect()->to($clean)->withCookie(cookie(LaunchPage::PREVIEW_COOKIE, $token, 60 * 24 * 30, httpOnly: true));
        }

        if (LaunchPage::canBypass($request, $s)) {
            return $next($request);
        }

        // An open page navigating inside the app: reload fully so the page below is shown.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return self::render($s);
    }

    public static function render(BusinessSetting $s, int $status = 503): Response
    {
        $retry = $s->launch_at && $s->launch_at->isFuture() ? max(60, min(86400, now()->diffInSeconds($s->launch_at))) : 3600;

        $response = response()->view('launch', LaunchPage::viewData($s), $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');

        return $status === 503 ? $response->header('Retry-After', (string) (int) $retry) : $response;
    }
}
