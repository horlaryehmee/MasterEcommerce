<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Coupons;
use App\Support\Media;
use App\Support\Money;
use App\Support\Seo;
use App\Support\Tracking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CartController extends StorefrontController
{
    public function __construct(protected Cart $cart) {}

    public function show(): Response
    {
        $pruned = $this->cart->pruned();
        $summary = $this->cart->summary();

        return $this->page('Storefront/Cart', [
            'items' => $summary['items'],
            'subtotal' => $summary['subtotal'],
            'total' => $summary['total'],
            'coupon' => $summary['coupon'],
            'couponsEnabled' => $summary['coupon'] !== null || Coupons::enabled(),
            'couponNotice' => $this->cart->takeCouponNotice(),
            'freeDelivery' => $summary['freeDelivery'],
            'notice' => $pruned > 0 ? ($pruned === 1 ? 'One item is no longer available and was removed from your cart.' : "{$pruned} items are no longer available and were removed from your cart.") : null,
        ], Seo::make()->title('Your cart')->noindex());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'checkout' => ['nullable', 'boolean'],
        ]);

        $product = $this->purchasable($data['product_id']);
        $variant = $this->variant($product, $data['variant_id'] ?? null);
        $quantity = (int) ($data['quantity'] ?? 1);
        $this->cart->add($product, $quantity, $variant);
        $analytics = [Tracking::addToCart($product, $quantity, $variant)];

        if ($request->boolean('checkout')) {
            return redirect()->route('checkout.show')->with('analytics', $analytics);
        }

        // Flashed for the cart drawer.
        return back()->with('analytics', $analytics)->with('cart_added', [
            'key' => uniqid(),
            'id' => $product->id,
            'line' => Cart::key($product, $variant),
            'name' => $product->name,
            'variant' => $variant?->label(),
            'price' => Money::format($variant ? $variant->price : $product->price),
            'image' => Media::url(($variant?->image ?? $product->primaryImage)?->path, 'sm'),
            'quantity' => $this->cart->quantityOf($product, $variant),
        ]);
    }

    /** `$line` is a cart line key: "12" or, for a variation, "12:5". */
    public function update(Request $request, string $line): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]]);
        $this->cart->setLine($line, (int) $data['quantity']);

        return back();
    }

    public function destroy(string $line): RedirectResponse
    {
        $this->cart->remove($line);

        return back();
    }

    /** The chosen variation of a variable product (required), or null for a simple product. */
    protected function variant(Product $product, ?int $variantId): ?ProductVariant
    {
        if (! $product->has_variants) {
            return null;
        }
        $variant = $variantId ? $product->variants()->with('image')->find($variantId) : null;

        if (! $variant) {
            abort(redirect()->back()->with('error', 'Please choose '.collect($product->optionList())->pluck('name')->map(fn ($n) => mb_strtolower($n))->implode(' and ').' first.'));
        }
        if (! $variant->isPurchasable()) {
            abort(redirect()->back()->with('error', "Sorry, {$product->name} ({$variant->label()}) is not available to order online. Send us a message to ask about it."));
        }

        return $variant;
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['coupon' => ['required', 'string', 'max:40']], ['coupon.required' => 'Enter a coupon code.']);

        if ($this->cart->lines()->isEmpty()) {
            return back()->withErrors(['coupon' => 'Add something to your cart first.']);
        }

        $coupon = Coupon::findByCode($data['coupon']);
        try {
            if (! $coupon) {
                throw new CouponException('Coupon “'.Coupon::normalizeCode($data['coupon']).'” is not valid.');
            }
            $this->cart->applyCoupon($coupon);
        } catch (CouponException $e) {
            return back()->withErrors(['coupon' => $e->getMessage()]);
        }

        return back();
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back();
    }

    protected function purchasable(int $id): Product
    {
        $product = Product::query()->with(['primaryImage', 'category:id,name,is_published'])->find($id);

        if (! $product || ! $product->isPurchasable()) {
            abort(redirect()->back()->with('error', 'Sorry, this product is not available to order online. Send us a message to ask about it.'));
        }

        return $product;
    }
}
