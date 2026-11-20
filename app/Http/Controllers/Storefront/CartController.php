<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Product;
use App\Services\Cart;
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
            'freeDelivery' => $summary['freeDelivery'],
            'notice' => $pruned > 0 ? ($pruned === 1 ? 'One item is no longer available and was removed from your cart.' : "{$pruned} items are no longer available and were removed from your cart.") : null,
        ], Seo::make()->title('Your cart')->noindex());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'checkout' => ['nullable', 'boolean'],
        ]);

        $product = $this->purchasable($data['product_id']);
        $quantity = (int) ($data['quantity'] ?? 1);
        $this->cart->add($product, $quantity);
        $analytics = [Tracking::addToCart($product, $quantity)];

        if ($request->boolean('checkout')) {
            return redirect()->route('checkout.show')->with('analytics', $analytics);
        }

        // Flashed for the cart drawer.
        return back()->with('analytics', $analytics)->with('cart_added', [
            'key' => uniqid(),
            'id' => $product->id,
            'name' => $product->name,
            'price' => Money::format($product->price),
            'image' => Media::url($product->primaryImage?->path, 'sm'),
            'quantity' => $this->cart->quantityOf($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]]);
        $this->cart->set($product, (int) $data['quantity']);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

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
