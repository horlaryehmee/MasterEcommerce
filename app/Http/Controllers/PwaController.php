<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Support\AppIcon;
use App\Support\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Installable apps ("Add to Home Screen"): one for the shop, one for the admin.
 * Each has its own manifest, so they install as two separate apps with their
 * own name, icon and start page. One service worker serves both.
 */
class PwaController extends Controller
{
    /**
     * Middleware left out for files the browser fetches in the background (service worker,
     * manifests, icons, offline page, sitemap, robots.txt). Without a session they can't become
     * the "previous page": otherwise a back() redirect after a form could land on /sw.js.
     */
    public const STATELESS = [
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \App\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ];

    public function shopManifest(): JsonResponse
    {
        $s = BusinessSetting::current();
        $color = Color::sanitize($s->primary_color, '#111827');

        return $this->manifest([
            'id' => url('/'),
            'name' => $s->business_name,
            'short_name' => Str::limit($s->business_name, 12, ''),
            'description' => Str::limit(strip_tags((string) ($s->seo_description ?: $s->business_description)), 280) ?: null,
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $color,
            'background_color' => '#FFFFFF',
            'icons' => AppIcon::manifestIcons('shop'),
            'categories' => ['shopping'],
            'shortcuts' => array_values(array_filter([
                ['name' => 'Shop all products', 'url' => route('products.index')],
                ['name' => 'Categories', 'url' => route('categories.index')],
                $s->shop_enabled ? ['name' => 'My cart', 'url' => route('cart.show')] : null,
            ])),
        ]);
    }

    public function adminManifest(): JsonResponse
    {
        $s = BusinessSetting::current();

        return $this->manifest([
            'id' => url('/admin'),
            'name' => $s->business_name.' Admin',
            'short_name' => 'Admin',
            'description' => 'Manage orders, products and settings for '.$s->business_name.'.',
            'start_url' => url('/admin'),
            'scope' => url('/admin'),
            'display' => 'standalone',
            'theme_color' => '#FFFFFF',
            'background_color' => '#FAFAFA',
            'icons' => AppIcon::manifestIcons('admin'),
            'categories' => ['business', 'productivity'],
            'shortcuts' => [
                ['name' => 'Orders', 'url' => url('/admin/orders')],
                ['name' => 'Add product', 'url' => url('/admin/products/create')],
                ['name' => 'Products', 'url' => url('/admin/products')],
            ],
        ]);
    }

    /**
     * The service worker. It caches only the build's static files (they never
     * change: their names contain a hash) and an offline page. Pages and data
     * are never cached, so no customer or admin information is stored on the device.
     */
    public function serviceWorker(): Response
    {
        $build = public_path('build/manifest.json');
        $version = substr(sha1((is_file($build) ? (string) filemtime($build) : '0').'|'.config('app.key')), 0, 12);
        $assets = rtrim(parse_url(asset('build/assets'), PHP_URL_PATH) ?: '/build/assets', '/').'/';
        $offline = parse_url(route('pwa.offline'), PHP_URL_PATH);

        $js = <<<JS
        // Service worker: static files cached, pages always from the network.
        const VERSION = '{$version}';
        const CACHE = 'static-' + VERSION;
        const OFFLINE = '{$offline}';
        const ASSETS = '{$assets}';

        self.addEventListener('install', (event) => {
            event.waitUntil(caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE, { cache: 'reload' }))).then(() => self.skipWaiting()));
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys()
                    .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
                    .then(() => self.clients.claim())
            );
        });

        self.addEventListener('fetch', (event) => {
            const request = event.request;
            if (request.method !== 'GET') return;
            const url = new URL(request.url);
            if (url.origin !== self.location.origin) return;

            // Hashed build files never change: serve from cache, fetch once.
            if (url.pathname.startsWith(ASSETS)) {
                event.respondWith(
                    caches.open(CACHE).then((cache) =>
                        cache.match(request).then((hit) => hit || fetch(request).then((response) => {
                            if (response.ok) cache.put(request, response.clone());
                            return response;
                        }))
                    )
                );
                return;
            }

            // Pages: never cached (they can hold personal details). Offline page when there's no connection.
            if (request.mode === 'navigate') {
                event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
            }
        });

        // Admin notifications (new orders…), even when the app is closed.
        self.addEventListener('push', (event) => {
            let data = {};
            try {
                data = event.data ? event.data.json() : {};
            } catch (e) {
                data = { title: event.data ? event.data.text() : 'Notification' };
            }
            const jobs = [
                self.registration.showNotification(data.title || 'Notification', {
                    body: data.body || '',
                    icon: data.icon,
                    tag: data.tag,
                    renotify: Boolean(data.tag),
                    data: { url: data.url || '/admin' },
                }),
            ];
            // Number on the app icon: new orders waiting.
            if (typeof data.badge === 'number' && 'setAppBadge' in self.navigator) {
                jobs.push(data.badge > 0 ? self.navigator.setAppBadge(data.badge) : self.navigator.clearAppBadge());
            }
            event.waitUntil(Promise.all(jobs).catch(() => {}));
        });

        // Tap: open that order in the admin app (same site only).
        self.addEventListener('notificationclick', (event) => {
            event.notification.close();
            const target = new URL((event.notification.data && event.notification.data.url) || '/admin', self.location.origin);
            if (target.origin !== self.location.origin) return;
            event.waitUntil(
                self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
                    const open = windows.find((w) => new URL(w.url).pathname.startsWith('/admin'));
                    if (open) {
                        return open.focus().then((w) => (w && 'navigate' in w ? w.navigate(target.href) : null));
                    }
                    return self.clients.openWindow(target.href);
                })
            );
        });
        JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): Response
    {
        $s = BusinessSetting::current();

        return response()->view('pwa.offline', [
            'name' => $s->business_name,
            'color' => Color::sanitize($s->primary_color, '#111827'),
        ])->header('Cache-Control', 'no-cache');
    }

    public function icon(string $app): Response
    {
        return response(AppIcon::svg($app), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    protected function manifest(array $data): JsonResponse
    {
        return response()->json(array_filter($data, fn ($v) => $v !== null), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
