<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Storefront;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/*
| Setup wizard for a fresh upload (closed once installed; see EnsureInstalled).
*/
Route::prefix('install')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [\App\Http\Controllers\InstallController::class, 'welcome']);
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
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.store');
        Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.store');
    });

    Route::middleware(['auth', 'auth.session'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::patch('products/{product}/toggle', [Admin\ProductController::class, 'toggle'])->name('products.toggle');
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
        Route::get('analytics/visitors/{id}', [Admin\AnalyticsController::class, 'visitor'])->whereNumber('id')->name('analytics.visitor');

        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

        Route::patch('coupons/{coupon}/toggle', [Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::resource('coupons', Admin\CouponController::class)->except('show');

        Route::get('templates', [Admin\TemplateController::class, 'index'])->name('templates.index');
        Route::put('templates', [Admin\TemplateController::class, 'update'])->name('templates.update');

        Route::post('shipping-zones/reorder', [Admin\ShippingZoneController::class, 'reorder'])->name('shipping-zones.reorder');
        Route::resource('shipping-zones', Admin\ShippingZoneController::class)->except(['index', 'show']);

        Route::post('redirects', [Admin\RedirectController::class, 'store'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [Admin\RedirectController::class, 'destroy'])->name('redirects.destroy');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings/email/test', [Admin\SettingsController::class, 'testEmail'])->middleware('throttle:5,1')->name('settings.test-email');
        Route::put('settings/{section}', [Admin\SettingsController::class, 'update'])->name('settings.update');

        // One-click updates. Reachable during maintenance mode (bootstrap/app.php).
        Route::get('updates', [Admin\UpdateController::class, 'index'])->name('updates.index');
        Route::post('updates/step/{step}', [Admin\UpdateController::class, 'step'])->name('updates.step');
        Route::post('updates/cancel', [Admin\UpdateController::class, 'cancel'])->name('updates.cancel');
        Route::post('updates/online', [Admin\UpdateController::class, 'online'])->name('updates.online');
        Route::put('updates/source', [Admin\UpdateController::class, 'source'])->name('updates.source');
        Route::post('updates/backups/{backup}/restore', [Admin\UpdateController::class, 'restore'])->name('updates.restore');
        Route::post('updates/backups/{backup}/rollback', [Admin\UpdateController::class, 'rollback'])->name('updates.rollback');
        Route::get('updates/backups/{backup}/database', [Admin\UpdateController::class, 'database'])->name('updates.database');
        Route::post('updates/agent/connect', [Admin\AgentController::class, 'connect'])->middleware('throttle:10,1')->name('updates.agent.connect');
        Route::post('updates/agent/disconnect', [Admin\AgentController::class, 'disconnect'])->name('updates.agent.disconnect');
        Route::post('updates/agent/check', [Admin\AgentController::class, 'check'])->middleware('throttle:20,1')->name('updates.agent.check');
        Route::post('updates/agent/run', [Admin\AgentController::class, 'run'])->name('updates.agent.run');
        Route::post('updates/agent/finish', [Admin\AgentController::class, 'finish'])->name('updates.agent.finish');

        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [Admin\ProfileController::class, 'updatePassword'])->name('profile.password');
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
Route::get('categories', [Storefront\CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{slug}', [Storefront\CategoryController::class, 'show'])->name('categories.show');
Route::get('search', [Storefront\ProductController::class, 'search'])->name('search');

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
Route::get('order/{token}', [Storefront\OrderController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('orders.show');

Route::get('sitemap.xml', [Storefront\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [Storefront\SitemapController::class, 'robots'])->name('robots');

// Informational pages: /about, /contact, /faq, /privacy-policy, ...
Route::get('{slug}', [Storefront\PageController::class, 'show'])
    ->whereIn('slug', array_keys(Page::DEFAULTS))
    ->name('pages.show');
