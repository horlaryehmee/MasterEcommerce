<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Small cache layer for data shown on every storefront page (navigation,
 * footer, social links) and the sitemap. Flushed whenever related records
 * change, so admins always see their edits immediately.
 */
class StorefrontCache
{
    public const KEYS = ['storefront.shared', 'storefront.sitemap', 'storefront.home.reviews'];

    public static function remember(string $key, \Closure $callback): mixed
    {
        return Cache::remember('storefront.'.$key, now()->addDay(), $callback);
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }
}
