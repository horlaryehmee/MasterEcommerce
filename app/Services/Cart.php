<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-based shopping cart for guest checkout. Only product IDs and
 * quantities are stored; prices are always read fresh from the database,
 * so a changed price or unpublished product is reflected immediately.
 */
class Cart
{
    public const MAX_QUANTITY = 99;

    protected const KEY = 'cart';

    protected ?Collection $lines = null;

    protected int $pruned = 0;

    public function __construct(protected Session $session) {}

    /** @return array<int, int> product id => quantity */
    public function raw(): array
    {
        return array_filter((array) $this->session->get(self::KEY, []), fn ($q) => is_int($q) && $q > 0);
    }

    public function quantityOf(Product $product): int
    {
        return $this->raw()[$product->id] ?? 0;
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $this->set($product, $this->quantityOf($product) + $quantity);
    }

    public function set(Product $product, int $quantity): void
    {
        $items = $this->raw();
        if ($quantity <= 0) {
            unset($items[$product->id]);
        } else {
            $items[$product->id] = min($quantity, self::MAX_QUANTITY);
        }
        $this->session->put(self::KEY, $items);
        $this->lines = null;
    }

    public function remove(Product $product): void
    {
        $this->set($product, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->lines = null;
    }

    /** Number of items (sum of quantities) for the header badge. */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    /**
     * Purchasable products in the cart with their quantities. Items that can no
     * longer be bought (deleted, unpublished, out of stock) are dropped from the
     * session, and `pruned()` reports how many.
     *
     * @return Collection<int, array{product: Product, quantity: int, line_total: float}>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $raw = $this->raw();
        $products = $raw === [] ? collect() : Product::query()
            ->whereKey(array_keys($raw))
            ->with(['primaryImage', 'category:id,name,is_published'])
            ->get()
            ->keyBy('id');

        $lines = collect();
        $kept = [];
        foreach ($raw as $id => $quantity) {
            $product = $products->get($id);
            if (! $product || ! $product->isPurchasable()) {
                continue;
            }
            $kept[$id] = $quantity;
            $lines->push([
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => round((float) $product->price * $quantity, 2),
            ]);
        }

        $this->pruned = count($raw) - count($kept);
        if ($this->pruned > 0) {
            $this->session->put(self::KEY, $kept);
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
            'id' => $l['product']->id,
            'name' => $l['product']->name,
            'url' => $l['product']->url(),
            'image' => Media::image($l['product']->primaryImage?->path, $l['product']->name),
            'category' => $l['product']->category?->name,
            'unit_price' => Money::format($l['product']->price),
            'quantity' => $l['quantity'],
            'line_total' => Money::format($l['line_total']),
            'availability' => $l['product']->availability,
            'availability_label' => Product::AVAILABILITY[$l['product']->availability] ?? null,
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
        $subtotal = $this->subtotal();
        // Free-shipping hint for the store's own country (only looked up when the cart has items).
        $threshold = $items !== [] ? (float) app(Shipping::class)->freeShippingThreshold($settings) : 0.0;

        return [
            'count' => (int) array_sum(array_column($items, 'quantity')),
            'items' => $items,
            'subtotal' => Money::format($subtotal, $settings),
            'freeDelivery' => $threshold > 0 && $items !== [] ? [
                'threshold' => Money::format($threshold, $settings),
                'remaining' => $subtotal < $threshold ? Money::format($threshold - $subtotal, $settings) : null,
                'progress' => min(100, (int) round($subtotal / $threshold * 100)),
            ] : null,
        ];
    }
}
