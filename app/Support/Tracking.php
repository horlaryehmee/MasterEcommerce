<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Analytics and ad pixels (GA4, Google Tag Manager, Google Ads, Meta Pixel,
 * TikTok Pixel) configured in Website Settings → Tracking & ads.
 *
 * Events use GA4's e-commerce format and are translated per platform in
 * resources/js/lib/analytics.js.
 */
class Tracking
{
    /** Accepted ID formats (also used to validate admin input). */
    public const FORMATS = [
        'ga4_id' => '/^G-[A-Z0-9]{4,20}$/',
        'gtm_id' => '/^GTM-[A-Z0-9]{4,12}$/',
        'google_ads_id' => '/^AW-\d{6,15}$/',
        'google_ads_purchase_label' => '/^[A-Za-z0-9_\-]{1,80}$/',
        'meta_pixel_id' => '/^\d{6,20}$/',
        'tiktok_pixel_id' => '/^[A-Z0-9]{10,30}$/',
    ];

    public static function containsPrivateUrl(string $value): bool
    {
        return preg_match('~/(?:order/|payments/|admin/reset-password/)[^?#\s]*~i', rawurldecode($value)) === 1;
    }

    /**
     * Pages whose address carries a credential (order token, reset token) or that belong to the admin
     * or installer: no third-party pixels. /order/thanks shows the order just placed WITHOUT a token
     * in its address, so purchases can still be measured; checkout has no credential in its address.
     */
    public static function privatePage(Request $request): bool
    {
        if ($request->is('order/thanks')) {
            return false;
        }

        return $request->is('admin', 'admin/*', 'order/*', 'payments/*', 'install', 'install/*');
    }

    /** Built-in pixels load on storefront pages, not in the admin, and not for the logged-in owner unless allowed. */
    public static function enabled(Request $request, ?BusinessSetting $s = null): bool
    {
        $s ??= BusinessSetting::current();
        if (self::privatePage($request)) {
            return false;
        }

        return ! $request->user() || $s->track_admins;
    }

    /** Validated IDs for the page; empty when tracking is off. */
    public static function config(Request $request, ?BusinessSetting $s = null): array
    {
        $s ??= BusinessSetting::current();
        if (! self::enabled($request, $s)) {
            return [];
        }

        $ids = [];
        foreach (self::FORMATS as $key => $pattern) {
            $value = trim((string) $s->{$key});
            if ($value !== '' && preg_match($pattern, $value)) {
                $ids[$key] = $value;
            }
        }
        if ($ids === []) {
            return [];
        }

        return [
            'ga4' => $ids['ga4_id'] ?? null,
            'gtm' => $ids['gtm_id'] ?? null,
            'ads' => $ids['google_ads_id'] ?? null,
            'adsPurchaseLabel' => isset($ids['google_ads_id'], $ids['google_ads_purchase_label']) ? $ids['google_ads_id'].'/'.$ids['google_ads_purchase_label'] : null,
            'meta' => $ids['meta_pixel_id'] ?? null,
            'tiktok' => $ids['tiktok_pixel_id'] ?? null,
            'currency' => $s->currency_code,
        ];
    }

    public static function productItem(Product $product, int $quantity = 1, ?ProductVariant $variant = null): array
    {
        return array_filter([
            // A variation's SKU when it has one, so ad platforms can tell sizes apart.
            'item_id' => $variant?->sku ?: (string) $product->id,
            'item_name' => $product->name,
            'item_variant' => $variant?->label(),
            'item_category' => $product->relationLoaded('category') ? $product->category?->name : null,
            'price' => (float) ($variant ? $variant->price : $product->price),
            'quantity' => $quantity,
        ], fn ($v) => $v !== null);
    }

    public static function viewItem(Product $product): array
    {
        return self::event('view_item', (float) $product->price, [self::productItem($product)]);
    }

    public static function addToCart(Product $product, int $quantity, ?ProductVariant $variant = null): array
    {
        return self::event('add_to_cart', (float) ($variant ? $variant->price : $product->price) * $quantity, [self::productItem($product, $quantity, $variant)]);
    }

    /** @param array<int, array{product: Product, quantity: int}> $lines */
    public static function beginCheckout(iterable $lines, float $subtotal, ?string $coupon = null): array
    {
        $items = collect($lines)->map(fn ($l) => self::productItem($l['product'], $l['quantity'], $l['variant'] ?? null))->values()->all();

        return self::event('begin_checkout', $subtotal, $items, $coupon ? ['coupon' => $coupon] : []);
    }

    public static function purchase(Order $order): array
    {
        $items = $order->items->map(fn (OrderItem $i) => [
            'item_id' => (string) ($i->product_id ?? $i->id),
            'item_name' => $i->product_name,
            ...($i->variant_label ? ['item_variant' => $i->variant_label] : []),
            'price' => (float) $i->unit_price,
            'quantity' => $i->quantity,
            ...((float) $i->discount > 0 ? ['discount' => round((float) $i->discount / max(1, $i->quantity), 2)] : []),
        ])->values()->all();

        return self::event('purchase', (float) $order->total, $items, [
            'transaction_id' => $order->number,
            'shipping' => (float) $order->delivery_fee,
            ...($order->coupon_code ? ['coupon' => $order->coupon_code] : []),
        ]);
    }

    public static function search(string $term): array
    {
        return ['event' => 'search', 'params' => ['search_term' => $term]];
    }

    protected static function event(string $name, float $value, array $items, array $extra = []): array
    {
        return ['event' => $name, 'params' => [
            'currency' => BusinessSetting::current()->currency_code,
            'value' => round($value, 2),
            'items' => $items,
            ...$extra,
        ]];
    }
}
