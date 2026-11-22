<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\Page;
use App\Models\SocialLink;
use App\Services\Cart;
use App\Services\Stats\Collector;
use App\Services\Updater\Updater;
use App\Support\Media;
use App\Support\StorefrontCache;
use App\Support\Themes;
use App\Support\Tracking;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** The setup wizard is plain HTML and runs before there is a database. */
    public function handle(Request $request, \Closure $next)
    {
        if ($request->is('install', 'install/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return (parent::version($request) ?? 'app').(Tracking::privatePage($request) ? '-private' : '-public');
    }

    public function share(Request $request): array
    {
        $isAdmin = $request->is('admin', 'admin/*');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'cart_added' => fn () => $request->session()->get('cart_added'),
            ],
            // Pixel IDs for this visitor (none in the admin or for the logged-in owner) and
            // one-off analytics events flashed by the server (add to cart, purchase).
            'tracking' => $isAdmin ? null : fn () => Tracking::config($request) ?: null,
            'analytics' => $isAdmin ? null : fn () => $request->session()->get('analytics'),
            // Built-in visitor statistics on/off for this visitor.
            'stats' => ! $isAdmin && Collector::shouldTrack($request),
            'brand' => fn () => $this->brand(),
            'author' => config('catalog.author'),
            // Storefront chrome (header, footer, WhatsApp) — not needed in the admin.
            'store' => $isAdmin ? null : fn () => [
                ...StorefrontCache::remember('shared', fn () => $this->storefront()),
                // Depends on a template preview, so outside the cache.
                ...$this->cardOptions(Themes::effectiveSettings(null, $request)),
            ],
            // Storefront template layout (Admin → Templates), and the preview bar while previewing one.
            'theme' => $isAdmin ? null : fn () => Themes::shared($request),
            // Per visitor, so never cached.
            'cart' => $isAdmin ? null : fn () => BusinessSetting::current()->shop_enabled ? app(Cart::class)->summary() : null,
            'admin' => $isAdmin && $request->user() ? fn () => [
                'shop_enabled' => BusinessSetting::current()->shop_enabled,
                'new_orders' => Order::query()->where('status', 'pending')->count(),
                'pending_reviews' => \App\Models\Review::query()->where('status', 'pending')->count(),
                // Bank-transfer orders the customer says are paid (button or receipt), waiting for the owner to check.
                'payments_to_check' => Order::query()->paymentToCheck()->count(),
                // From the last cached check only; never contacts GitHub here.
                'update_available' => (bool) (app(Updater::class)->cachedStatus()['available'] ?? false),
                'version' => app(Updater::class)->currentVersion(),
                // Shown on every admin page until Paystack is switched to live keys.
                'paystack_test_mode' => app(\App\Services\Paystack::class)->inTestMode(),
                // Notifications: lets each device turn itself on (or prompt) on any admin page.
                'push' => [
                    'publicKey' => app(\App\Services\WebPush::class)->publicKey(),
                    'on' => $request->user()->pushSubscriptions()->active()->pluck('endpoint_hash'),
                    'off' => $request->user()->pushSubscriptions()->whereNotNull('disabled_at')->pluck('endpoint_hash'),
                ],
            ] : null,
        ];
    }

    /** Product card design, plus the image shape and corners chosen on top of it. */
    protected function cardOptions(BusinessSetting $s): array
    {
        return [
            'card_style' => array_key_exists($s->product_card_style, BusinessSetting::CARD_STYLES) ? $s->product_card_style : 'modern',
            'card_shape' => array_key_exists((string) $s->product_card_shape, BusinessSetting::CARD_SHAPES) ? $s->product_card_shape : 'auto',
            'card_corners' => array_key_exists((string) $s->product_card_corners, BusinessSetting::CARD_CORNERS) ? $s->product_card_corners : 'auto',
        ];
    }

    protected function brand(): array
    {
        $s = BusinessSetting::current();

        return [
            'name' => $s->business_name,
            'logo' => Media::url($s->logo_path),
        ];
    }

    protected function storefront(): array
    {
        $s = BusinessSetting::current();
        $home = HomepageSetting::current();

        $socials = SocialLink::query()->orderBy('sort_order')->get()
            ->filter(fn (SocialLink $l) => isset(SocialLink::PLATFORMS[$l->platform]))
            ->map(fn (SocialLink $l) => [
                'platform' => $l->platform,
                'label' => SocialLink::PLATFORMS[$l->platform]['label'],
                'url' => $l->url,
            ])->values();

        return [
            'description' => $s->business_description,
            'email' => $s->email,
            'phone' => $s->phone,
            'address' => $s->address,
            'opening_hours' => $s->opening_hours,
            'whatsapp_url' => WhatsApp::generalLink($s),
            'show_floating_whatsapp' => $s->show_floating_whatsapp,
            'shop_enabled' => $s->shop_enabled,
            'payment_methods' => $s->shop_enabled ? array_values($s->paymentMethods()) : [],
            'footer_description' => $s->footer_description ?: $s->business_description,
            'copyright' => $s->copyright_text
                ? str_replace('{year}', date('Y'), $s->copyright_text)
                : '© '.date('Y').' '.$s->business_name.'. All rights reserved.',
            'announcement' => $home->announcement_enabled && filled($home->announcement_text) ? [
                'text' => $home->announcement_text,
                'link' => $home->announcement_link,
                'bg' => $home->announcement_bg_color,
                'color' => $home->announcement_text_color,
            ] : null,
            'categories' => Category::query()->published()->ordered()
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['name' => $c->name, 'url' => route('categories.show', $c->slug)]),
            'pages' => Page::query()->published()->get(['slug', 'title'])
                ->sortBy(fn (Page $p) => array_search($p->slug, array_keys(Page::DEFAULTS), true))
                ->map(fn (Page $p) => ['slug' => $p->slug, 'title' => $p->title, 'url' => $p->url()])
                ->values(),
            'socials' => $socials,
        ];
    }
}
