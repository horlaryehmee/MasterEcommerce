<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-based shopping cart for guest checkout. Only line keys and
 * quantities are stored; prices are always read fresh from the database,
 * so a changed price or unpublished product is reflected immediately.
 *
 * A line key is the product id ("12") or, for a variable product, the product
 * and variant ids ("12:5"), so two sizes of one shirt are separate lines.
 */
class Cart
{
    public const MAX_QUANTITY = 99;

    protected const KEY = 'cart';

    protected const COUPON_KEY = 'cart_coupon';

    /** Why an applied coupon was taken off the cart; shown once on the cart or checkout page. */
    protected const COUPON_NOTICE_KEY = 'cart_coupon_notice';

    protected ?Collection $lines = null;

    /** Resolved coupon (false = not worked out yet). */
    protected array|null|false $coupon = false;

    protected int $pruned = 0;

    public function __construct(protected Session $session) {}

    /** @return array<string|int, int> line key => quantity */
    public function raw(): array
    {
        return array_filter((array) $this->session->get(self::KEY, []), fn ($q, $key) => is_int($q) && $q > 0 && self::parseKey((string) $key) !== null, ARRAY_FILTER_USE_BOTH);
    }

    public static function key(Product $product, ?ProductVariant $variant = null): string
    {
        return $variant ? "{$product->id}:{$variant->id}" : (string) $product->id;
    }

    /** @return array{0: int, 1: ?int}|null [product id, variant id] */
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^(\d+)(?::(\d+))?$/', $key, $m)) {
            return null;
        }

        return [(int) $m[1], isset($m[2]) ? (int) $m[2] : null];
    }

    public function quantityOf(Product $product, ?ProductVariant $variant = null): int
    {
        return $this->raw()[self::key($product, $variant)] ?? 0;
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $this->setLine(self::key($product, $variant), $this->quantityOf($product, $variant) + $quantity);
    }

    public function set(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $this->setLine(self::key($product, $variant), $quantity);
    }

    /** Change a line's quantity by its key (0 removes it). Unknown keys are ignored. */
    public function setLine(string $key, int $quantity): void
    {
        if (self::parseKey($key) === null) {
            return;
        }
        $items = $this->raw();
        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $items[$key] = min($quantity, self::MAX_QUANTITY);
        }
        $this->session->put(self::KEY, $items);
        $this->lines = null;
        $this->coupon = false;
    }

    public function remove(Product $product, ?ProductVariant $variant = null): void
    {
        $this->set($product, 0, $variant);
    }

    public function clear(): void
    {
        $this->session->forget([self::KEY, self::COUPON_KEY, self::COUPON_NOTICE_KEY]);
        $this->lines = null;
        $this->coupon = false;
    }

    /** The code the customer applied (it may since have become invalid). */
    public function couponCode(): ?string
    {
        $code = $this->session->get(self::COUPON_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Check a coupon against this cart and keep it if it can be used.
     *
     * @throws CouponException
     */
    public function applyCoupon(Coupon $coupon): array
    {
        $result = app(Coupons::class)->apply($coupon, $this->lines());
        $this->session->put(self::COUPON_KEY, $coupon->code);
        $this->session->forget(self::COUPON_NOTICE_KEY);

        return $this->coupon = $result;
    }

    public function removeCoupon(): void
    {
        $this->session->forget([self::COUPON_KEY, self::COUPON_NOTICE_KEY]);
        $this->coupon = false;
    }

    /**
     * The applied coupon and its discount for the current cart, or null. A
     * coupon that no longer applies (expired, cart below the minimum spend,
     * deleted…) is taken off, and `takeCouponNotice()` says why.
     *
     * @return array{coupon: Coupon, discount: float, lines: array<int, float>, free_shipping: bool}|null
     */
    public function coupon(): ?array
    {
        if ($this->coupon !== false) {
            return $this->coupon;
        }
        $code = $this->couponCode();
        if ($code === null || $this->lines()->isEmpty()) {
            return $this->coupon = null;
        }

        $coupon = Coupon::findByCode($code);
        try {
            if (! $coupon) {
                throw new CouponException('This coupon is not valid.');
            }

            return $this->coupon = app(Coupons::class)->apply($coupon, $this->lines());
        } catch (CouponException $e) {
            $this->session->forget(self::COUPON_KEY);
            $this->session->put(self::COUPON_NOTICE_KEY, "Coupon “{$code}” was removed: {$e->getMessage()}");

            return $this->coupon = null;
        }
    }

    public function takeCouponNotice(): ?string
    {
        $notice = $this->session->pull(self::COUPON_NOTICE_KEY);

        return is_string($notice) ? $notice : null;
    }

    public function discount(): float
    {
        return (float) ($this->coupon()['discount'] ?? 0);
    }

    public function hasFreeShippingCoupon(): bool
    {
        return (bool) ($this->coupon()['free_shipping'] ?? false);
    }

    /** Subtotal after the coupon discount (before shipping). */
    public function total(): float
    {
        return round(max(0, $this->subtotal() - $this->discount()), 2);
    }

    /** Number of items (sum of quantities) for the header badge. */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    /**
     * Purchasable items in the cart with their quantities. Items that can no
     * longer be bought (deleted, unpublished, out of stock, a variant switched
     * off, a product that gained or lost options) are dropped from the session,
     * and `pruned()` reports how many.
     *
     * @return Collection<int, array{key: string, product: Product, variant: ?ProductVariant, unit_price: float, quantity: int, line_total: float}>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $raw = $this->raw();
        $parsed = collect($raw)->keys()->mapWithKeys(fn ($key) => [(string) $key => self::parseKey((string) $key)]);
        $products = $raw === [] ? collect() : Product::query()
            ->whereKey($parsed->pluck(0)->unique()->all())
            ->with(['primaryImage', 'category:id,name,is_published'])
            ->get()
            ->keyBy('id');
        $variantIds = $parsed->pluck(1)->filter()->unique()->all();
        $variants = $variantIds === [] ? collect() : ProductVariant::query()->whereKey($variantIds)->with('image')->get()->keyBy('id');

        $lines = collect();
        $kept = [];
        foreach ($raw as $key => $quantity) {
            [$productId, $variantId] = $parsed[(string) $key];
            $product = $products->get($productId);
            $variant = $variantId ? $variants->get($variantId) : null;
            $valid = $product && $product->isPurchasable() && ($product->has_variants
                ? $variant && $variant->product_id === $product->id && $variant->isPurchasable()
                : $variantId === null);
            if (! $valid) {
                continue;
            }
            $kept[$key] = $quantity;
            $unit = (float) ($variant ? $variant->price : $product->price);
            $lines->push([
                'key' => (string) $key,
                'product' => $product,
                'variant' => $variant,
                'unit_price' => $unit,
                'quantity' => $quantity,
                'line_total' => round($unit * $quantity, 2),
            ]);
        }

        $this->pruned = count($raw) - count($kept);
        if ($this->pruned > 0) {
            $this->session->put(self::KEY, $kept);
            $this->coupon = false;
        }

        return $this->lines = $lines;
    }

    public function pruned(): int
    {
        $this->lines();

        return $this->pruned;
    }

    public function subtotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }

    /** Cart lines for the storefront. */
    public function toArray(): array
    {
        return $this->lines()->map(fn (array $l) => [
            'key' => $l['key'],
            'id' => $l['product']->id,
            'name' => $l['product']->name,
            'variant' => $l['variant']?->label(),
            // Back to the product page with this variation chosen.
            'url' => $l['product']->url().($l['variant'] ? '?variant='.$l['variant']->id : ''),
            'image' => Media::image(($l['variant']?->image ?? $l['product']->primaryImage)?->path, $l['product']->name),
            'category' => $l['product']->category?->name,
            'unit_price' => Money::format($l['unit_price']),
            'quantity' => $l['quantity'],
            'line_total' => Money::format($l['line_total']),
            'availability' => $l['variant']->availability ?? $l['product']->availability,
            'availability_label' => Product::AVAILABILITY[$l['variant']->availability ?? $l['product']->availability] ?? null,
        ])->values()->all();
    }

    /**
     * Everything the cart drawer and cart page show. Shared with every storefront
     * page; an empty cart costs no queries.
     */
    public function summary(?BusinessSetting $settings = null): array
    {
        $settings ??= BusinessSetting::current();
        $items = $this->toArray();
        // Free shipping thresholds are compared with the total after discounts (as in WooCommerce).
        $subtotal = $this->total();
        $coupon = $items !== [] ? $this->coupon() : null;
        // Free-shipping hint for the store's own country (only looked up when the cart has items).
        $threshold = $items !== [] ? (float) app(Shipping::class)->freeShippingThreshold($settings) : 0.0;

        return [
            'count' => (int) array_sum(array_column($items, 'quantity')),
            'items' => $items,
            'subtotal' => Money::format($this->subtotal(), $settings),
            'coupon' => $coupon ? self::couponArray($coupon, $settings) : null,
            'total' => Money::format($subtotal, $settings),
            // A free shipping coupon makes the "add X more" hint pointless.
            'freeDelivery' => $threshold > 0 && $items !== [] && ! ($coupon['free_shipping'] ?? false) ? [
                'threshold' => Money::format($threshold, $settings),
                'remaining' => $subtotal < $threshold ? Money::format($threshold - $subtotal, $settings) : null,
                'progress' => min(100, (int) round($subtotal / $threshold * 100)),
            ] : null,
        ];
    }

    /** The applied coupon for the storefront. */
    public static function couponArray(array $applied, ?BusinessSetting $settings = null): array
    {
        return [
            'code' => $applied['coupon']->code,
            'label' => $applied['coupon']->offerLabel($settings),
            'discount' => $applied['discount'],
            'discount_label' => Money::format($applied['discount'], $settings),
            'free_shipping' => $applied['free_shipping'],
        ];
    }
}
