<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Storefront;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/*
| Setup wizard for a fresh upload (closed once installed; see EnsureInstalled).
*/
Route::post('install/unlock', [\App\Http\Controllers\InstallController::class, 'unlock'])->middleware(['throttle:5,1', \App\Http\Middleware\RequireSetupOwner::class])->name('install.unlock');
Route::prefix('install')->middleware(['throttle:30,1', \App\Http\Middleware\RequireSetupOwner::class])->group(function () {
    Route::get('/', [\App\Http\Controllers\InstallController::class, 'welcome'])->name('install.welcome');
    Route::get('database', [\App\Http\Controllers\InstallController::class, 'database']);
    Route::post('database', [\App\Http\Controllers\InstallController::class, 'saveDatabase']);
    Route::get('site', [\App\Http\Controllers\InstallController::class, 'site']);
    Route::post('finish', [\App\Http\Controllers\InstallController::class, 'finish']);
});

/*
|--------------------------------------------------------------------------
| Admin dashboard (/admin)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    // Installable admin app (before login too, so the login page can be installed).
    Route::get('manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'adminManifest'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('manifest');

    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.store');
        Route::get('two-factor-challenge', [AuthController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:10,1')->name('two-factor.verify');
        Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.store');
    });

    Route::middleware(['auth', 'auth.session'])->group(function () {
        Route::get('confirm-password', fn () => \Inertia\Inertia::render('Admin/Auth/ConfirmPassword'))->name('password.confirm');
        Route::post('confirm-password', [AuthController::class, 'confirmPassword'])->middleware('throttle:5,1');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::patch('products/{product}/toggle', [Admin\ProductController::class, 'toggle'])->name('products.toggle');
        // Bulk actions on ticked rows (throttled: each request can touch up to 100 rows).
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('products/bulk', [Admin\ProductController::class, 'bulk'])->name('products.bulk');
            Route::post('categories/bulk', [Admin\CategoryController::class, 'bulk'])->name('categories.bulk');
            Route::post('coupons/bulk', [Admin\CouponController::class, 'bulk'])->name('coupons.bulk');
            Route::post('orders/bulk', [Admin\OrderController::class, 'bulk'])->name('orders.bulk');
            Route::post('analytics/visitors/bulk', [Admin\AnalyticsController::class, 'visitorsBulk'])->name('analytics.visitors.bulk');
        });
        Route::get('products/export', [Admin\ProductController::class, 'export'])->name('products.export');
        Route::get('categories/export', [Admin\CategoryController::class, 'export'])->name('categories.export');
        Route::resource('products', Admin\ProductController::class)->except('show');
        Route::resource('categories', Admin\CategoryController::class)->except('show');

        Route::get('content', [Admin\ContentController::class, 'edit'])->name('content.edit');
        Route::put('content/{section}', [Admin\ContentController::class, 'update'])->name('content.update');
        Route::post('hero-slides/reorder', [Admin\HeroSlideController::class, 'reorder'])->name('hero-slides.reorder');
        Route::post('hero-slides', [Admin\HeroSlideController::class, 'store'])->name('hero-slides.store');
        Route::put('hero-slides/{slide}', [Admin\HeroSlideController::class, 'update'])->name('hero-slides.update');
        Route::delete('hero-slides/{slide}', [Admin\HeroSlideController::class, 'destroy'])->name('hero-slides.destroy');
        Route::get('pages/{page}/edit', [Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');

        Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/realtime', [Admin\AnalyticsController::class, 'realtime'])->name('analytics.realtime');
        Route::get('analytics/visitors', [Admin\AnalyticsController::class, 'visitors'])->name('analytics.visitors');
        Route::get('analytics/visitors/export', [Admin\AnalyticsController::class, 'visitorsExport'])->name('analytics.visitors.export');
        Route::get('analytics/visitors/{id}', [Admin\AnalyticsController::class, 'visitor'])->whereNumber('id')->name('analytics.visitor');

        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', [Admin\OrderController::class, 'export'])->name('orders.export');
        Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/bulk', [Admin\ReviewController::class, 'bulk'])->middleware('throttle:30,1')->name('reviews.bulk');
        Route::put('reviews/settings', [Admin\ReviewController::class, 'settings'])->name('reviews.settings');
        Route::patch('reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
        Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export', [Admin\CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/{key}', [Admin\CustomerController::class, 'show'])->where('key', '[pe][0-9a-f]{4,39}')->name('customers.show');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/email/{type}', [Admin\OrderController::class, 'emailPreview'])->whereIn('type', ['placed', 'owner', 'updated'])->name('orders.email');
        Route::patch('orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');
        // Bank-transfer receipts (private files, admins only).
        Route::get('receipts/{receipt}/file', [Admin\ReceiptController::class, 'file'])->name('receipts.file');
        Route::patch('receipts/{receipt}', [Admin\ReceiptController::class, 'update'])->name('receipts.update');

        Route::patch('coupons/{coupon}/toggle', [Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::get('coupons/export', [Admin\CouponController::class, 'export'])->name('coupons.export');
        Route::resource('coupons', Admin\CouponController::class)->except('show');

        Route::get('templates', [Admin\TemplateController::class, 'index'])->name('templates.index');
        Route::put('templates', [Admin\TemplateController::class, 'update'])->name('templates.update');

        Route::post('shipping-zones/reorder', [Admin\ShippingZoneController::class, 'reorder'])->name('shipping-zones.reorder');
        Route::resource('shipping-zones', Admin\ShippingZoneController::class)->except(['index', 'show']);

        Route::post('redirects', [Admin\RedirectController::class, 'store'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [Admin\RedirectController::class, 'destroy'])->name('redirects.destroy');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings/email/test', [Admin\SettingsController::class, 'testEmail'])->middleware('throttle:5,1')->name('settings.test-email');
        Route::put('settings/email', [Admin\SettingsController::class, 'update'])->defaults('section', 'email')->middleware('password.confirm:admin.password.confirm,600')->name('settings.email');
        Route::put('settings/code', [Admin\SettingsController::class, 'update'])->defaults('section', 'code')->middleware('password.confirm:admin.password.confirm,600')->name('settings.code');
        Route::put('settings/{section}', [Admin\SettingsController::class, 'update'])->name('settings.update');

        // One-click updates. Reachable during maintenance mode (bootstrap/app.php).
        Route::get('updates', [Admin\UpdateController::class, 'index'])->name('updates.index');
        Route::post('updates/step/{step}', [Admin\UpdateController::class, 'step'])->name('updates.step');
        Route::post('updates/cancel', [Admin\UpdateController::class, 'cancel'])->name('updates.cancel');
        Route::post('updates/online', [Admin\UpdateController::class, 'online'])->name('updates.online');
        Route::put('updates/source', [Admin\UpdateController::class, 'source'])->middleware('password.confirm:admin.password.confirm,600')->name('updates.source');
        Route::post('updates/backups/{backup}/restore', [Admin\UpdateController::class, 'restore'])->name('updates.restore');
        Route::post('updates/backups/{backup}/rollback', [Admin\UpdateController::class, 'rollback'])->name('updates.rollback');
        Route::get('updates/backups/{backup}/database', [Admin\UpdateController::class, 'database'])->middleware('password.confirm:admin.password.confirm,600')->name('updates.database');
        Route::post('updates/agent/connect', [Admin\AgentController::class, 'connect'])->middleware('throttle:10,1')->middleware('password.confirm:admin.password.confirm,600')->name('updates.agent.connect');
        Route::post('updates/agent/disconnect', [Admin\AgentController::class, 'disconnect'])->name('updates.agent.disconnect');
        Route::post('updates/agent/check', [Admin\AgentController::class, 'check'])->middleware('throttle:20,1')->name('updates.agent.check');
        Route::post('updates/agent/run', [Admin\AgentController::class, 'run'])->name('updates.agent.run');
        Route::post('updates/agent/finish', [Admin\AgentController::class, 'finish'])->name('updates.agent.finish');

        // Phone/desktop notifications, per device.
        Route::middleware('throttle:20,1')->group(function () {
            Route::post('push-subscriptions', [Admin\PushController::class, 'store'])->name('push.store');
            Route::delete('push-subscriptions', [Admin\PushController::class, 'destroy'])->name('push.destroy');
            Route::post('push-subscriptions/test', [Admin\PushController::class, 'test'])->name('push.test');
        });

        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
        // Help Center: step-by-step guides with screenshots (content in resources/js/Pages/Admin/Help/guides.js).
        Route::inertia('help', 'Admin/Help/Index')->name('help');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
        Route::post('profile/verify-email', [Admin\ProfileController::class, 'verifyEmail'])->middleware('throttle:5,1')->name('profile.verify-email');
        Route::put('profile/password', [Admin\ProfileController::class, 'updatePassword'])->name('profile.password');
        // Two-factor sign-in (each change asks for the current password).
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('profile/two-factor', [Admin\ProfileController::class, 'enableTwoFactor'])->name('profile.two-factor.enable');
            Route::post('profile/two-factor/confirm', [Admin\ProfileController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
            Route::post('profile/two-factor/recovery-codes', [Admin\ProfileController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery');
            Route::delete('profile/two-factor', [Admin\ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Public storefront
|--------------------------------------------------------------------------
*/

Route::get('/', Storefront\HomeController::class)->name('home');

// Template previews (Admin → Templates): a signed, shareable link starts a preview in this browser.
Route::get('preview/template/{template}', [Storefront\TemplatePreviewController::class, 'start'])->middleware('throttle:30,1')->name('template.preview');
Route::get('preview/exit', [Storefront\TemplatePreviewController::class, 'exit'])->name('template.exit');
Route::get('products', [Storefront\ProductController::class, 'index'])->name('products.index');
Route::get('products/{slug}', [Storefront\ProductController::class, 'show'])->name('products.show');
// Reviews (anti-spam in App\Services\ReviewGuard; also throttled here).
Route::get('products/{slug}/reviews', [Storefront\ReviewController::class, 'index'])->middleware('throttle:60,1')->name('reviews.index');
Route::post('products/{slug}/reviews', [Storefront\ReviewController::class, 'store'])->middleware('throttle:6,1')->name('reviews.store');
Route::get('categories', [Storefront\CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{slug}', [Storefront\CategoryController::class, 'show'])->name('categories.show');
Route::get('search', [Storefront\ProductController::class, 'search'])->middleware('throttle:60,1')->name('search');

// Built-in visitor statistics beacon (CSRF-exempt; see bootstrap/app.php). A neutral
// address, because ad blockers drop requests to paths like /stats/collect and undercount visitors.
Route::post('hello', [Storefront\StatsController::class, 'collect'])->middleware('throttle:240,1')->name('stats.collect');

// Online ordering (only when switched on in Admin → Website Settings → Shop & checkout).
Route::middleware('shop')->group(function () {
    Route::get('cart', [Storefront\CartController::class, 'show'])->name('cart.show');
    Route::post('cart', [Storefront\CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
    // Throttled tightly: guessing codes is the obvious abuse. (Before cart/{product}, which would match "coupon".)
    Route::post('cart/coupon', [Storefront\CartController::class, 'applyCoupon'])->middleware('throttle:15,1')->name('cart.coupon.store');
    Route::delete('cart/coupon', [Storefront\CartController::class, 'removeCoupon'])->name('cart.coupon.destroy');
    Route::patch('cart/{line}', [Storefront\CartController::class, 'update'])->where('line', '\d+(:\d+)?')->name('cart.update');
    Route::delete('cart/{line}', [Storefront\CartController::class, 'destroy'])->where('line', '\d+(:\d+)?')->name('cart.destroy');
    Route::get('checkout', [Storefront\CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('checkout/rates', [Storefront\CheckoutController::class, 'rates'])->middleware('throttle:120,1')->name('checkout.rates');
    Route::post('checkout', [Storefront\CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
});
// Customers' order links keep working even if online ordering is later switched off.
// Straight after checkout: the order just placed in this browser, with no private token in the address (see OrderController).
Route::get('order/thanks', [Storefront\OrderController::class, 'thanks'])->name('orders.thanks');
Route::get('order/{token}', [Storefront\OrderController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('orders.show');
// Bank-transfer receipt upload from the order page.
Route::post('order/{token}/receipt', [Storefront\ReceiptController::class, 'store'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:6,1')->name('orders.receipt');
Route::post('order/{token}/payment-sent', [Storefront\ReceiptController::class, 'reported'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('orders.payment-sent');

// Paystack online payments. Nothing is marked paid until the server has verified it with Paystack.
Route::post('order/{token}/pay', [Storefront\PaystackController::class, 'pay'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('orders.pay');
Route::get('payments/paystack/callback', [Storefront\PaystackController::class, 'callback'])->middleware('throttle:30,1')->name('payments.paystack.callback');
// CSRF-exempt (bootstrap/app.php): authenticated by its HMAC signature instead.
Route::post('payments/paystack/webhook', [Storefront\PaystackController::class, 'webhook'])->middleware('throttle:120,1')->name('payments.paystack.webhook');

// Installable shop app: manifest, service worker (must be at the root), offline page, fallback icon.
// Stateless (PwaController::STATELESS): the browser fetches these in the background.
Route::get('manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'shopManifest'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('pwa.manifest');
Route::get('sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('pwa.sw');
Route::get('offline', [\App\Http\Controllers\PwaController::class, 'offline'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('pwa.offline');
Route::get('pwa/icon/{app}.svg', [\App\Http\Controllers\PwaController::class, 'icon'])->whereIn('app', \App\Support\AppIcon::APPS)->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('pwa.icon');

Route::get('sitemap.xml', [Storefront\SitemapController::class, 'sitemap'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('sitemap');
Route::get('robots.txt', [Storefront\SitemapController::class, 'robots'])->withoutMiddleware(\App\Http\Controllers\PwaController::STATELESS)->name('robots');

// Informational pages: /about, /contact, /faq, /privacy-policy, ...
Route::get('{slug}', [Storefront\PageController::class, 'show'])
    ->whereIn('slug', array_keys(Page::DEFAULTS))
    ->name('pages.show');
