<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        BusinessSetting::current()->update(['shop_enabled' => true, 'whatsapp_number' => '2348012345678']);
        BusinessSetting::forgetResolved();
    }

    /**
     * Real requests start with a fresh cart. Tests share one app, where the
     * cart (scoped) and the controllers holding it (cached on their routes)
     * would otherwise carry over from the previous request.
     */
    protected function freshRequest(): void
    {
        $this->app->forgetScopedInstances();
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
    }

    protected function asOwner(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** Form payload for a T-shirt in Size (S, M) × Colour (Black, Red). */
    protected function payload(array $overrides = []): array
    {
        $v = fn ($size, $colour, $price, $extra = []) => $extra + ['id' => '', 'values' => [$size, $colour], 'price' => $price, 'compare_at_price' => '', 'sku' => '', 'availability' => 'in_stock', 'image' => '', 'is_enabled' => '1'];

        return $overrides + [
            'name' => 'Classic Tee',
            'status' => 'published',
            'category_id' => Category::factory()->create()->id,
            'options' => [
                ['name' => 'Size', 'values' => [['label' => 'S'], ['label' => 'M']]],
                ['name' => 'Colour', 'values' => [['label' => 'Black', 'color' => '#111111'], ['label' => 'Red', 'color' => '#dc2626']]],
            ],
            'variants' => [
                $v('S', 'Black', 5000, ['sku' => 'TEE-S-BLK']),
                $v('S', 'Red', 5000),
                $v('M', 'Black', 6000, ['compare_at_price' => 8000]),
                $v('M', 'Red', 6000, ['availability' => 'out_of_stock']),
            ],
        ];
    }

    protected function tee(): Product
    {
        $this->asOwner()->post('/admin/products', $this->payload())->assertSessionHasNoErrors();
        auth()->logout();

        return Product::query()->where('name', 'Classic Tee')->sole();
    }

    protected function variant(Product $p, string $label): ?ProductVariant
    {
        return $p->variants()->get()->first(fn ($v) => $v->label() === $label);
    }

    public function test_admin_creates_a_variable_product_and_prices_follow_the_variants(): void
    {
        $p = $this->tee();

        $this->assertTrue($p->has_variants);
        $this->assertCount(4, $p->variants);
        $this->assertSame(['S', 'Black'], $p->variants[0]->values());
        $this->assertSame('TEE-S-BLK', $p->variants[0]->sku);
        $this->assertSame('#DC2626', $p->options[1]['values'][1]['color']);
        // Lowest price, highest price, best availability.
        $this->assertSame('5000.00', $p->price);
        $this->assertSame('6000.00', $p->price_max);
        $this->assertSame('in_stock', $p->availability);

        $this->get('/products')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.price_from', true)
            ->where('products.data.0.has_variants', true));
    }

    public function test_editing_keeps_existing_variants_and_removing_options_makes_it_simple_again(): void
    {
        $p = $this->tee();
        $ids = $p->variants->pluck('id', fn ($v) => $v->label());

        $payload = $this->payload();
        $payload['variants'][0]['id'] = $ids['S / Black'];
        $payload['variants'][0]['price'] = 4500;
        $payload['variants'][1]['is_enabled'] = '0';
        array_pop($payload['variants']); // M / Red no longer offered: deleted
        $this->asOwner()->put("/admin/products/{$p->id}", $payload)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame($ids['S / Black'], $this->variant($p, 'S / Black')->id, 'Same row, so past orders still link to it.');
        $this->assertSame('4500.00', $p->price);
        $this->assertFalse($this->variant($p, 'S / Red')->is_enabled);
        $this->assertNull($this->variant($p, 'M / Red'));
        $this->assertSame(3, $p->variants()->count());

        // Removing every option turns it back into a simple product.
        $this->asOwner()->put("/admin/products/{$p->id}", ['name' => 'Classic Tee', 'status' => 'published', 'price' => 5500, 'availability' => 'in_stock'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertFalse($p->has_variants);
        $this->assertSame(0, $p->variants()->count());
        $this->assertSame('5500.00', $p->price);
    }

    public function test_variant_validation(): void
    {
        $bad = $this->payload();
        $bad['variants'][1]['values'] = ['S', 'Green'];
        $this->asOwner()->post('/admin/products', $bad)->assertSessionHasErrors('variants.1.values');

        $bad = $this->payload();
        $bad['variants'][1]['values'] = ['S', 'Black'];
        $this->asOwner()->post('/admin/products', $bad)->assertSessionHasErrors('variants.1.values');

        $bad = $this->payload();
        $bad['variants'][0]['compare_at_price'] = 4000;
        $this->asOwner()->post('/admin/products', $bad)->assertSessionHasErrors('variants.0.compare_at_price');

        $bad = $this->payload();
        $bad['options'][0]['values'][] = ['label' => 's'];
        $this->asOwner()->post('/admin/products', $bad)->assertSessionHasErrors('options.0.values');

        $bad = $this->payload();
        foreach ($bad['variants'] as &$row) {
            $row['is_enabled'] = '0';
        }
        $this->asOwner()->post('/admin/products', $bad)->assertSessionHasErrors('variants');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_product_page_gets_options_and_enabled_variants(): void
    {
        $p = $this->tee();
        $this->variant($p, 'S / Red')->update(['is_enabled' => false]);

        $this->get($p->url())->assertInertia(fn (Assert $page) => $page
            ->where('product.options.0.name', 'Size')
            ->where('product.options.1.values.0', ['label' => 'Black', 'color' => '#111111'])
            ->has('product.variants', 3)
            ->where('product.variants.1.label', 'M / Black')
            ->where('product.variants.1.compare_at_price', '₦8,000')
            ->where('product.variants.2.purchasable', false));
    }

    public function test_cart_needs_a_variant_and_keeps_each_variant_on_its_own_line(): void
    {
        $p = $this->tee();
        $sBlack = $this->variant($p, 'S / Black');
        $mBlack = $this->variant($p, 'M / Black');

        $this->from($p->url())->post('/cart', ['product_id' => $p->id])->assertSessionHas('error', 'Please choose size and colour first.');
        $this->from($p->url())->post('/cart', ['product_id' => $p->id, 'variant_id' => $this->variant($p, 'M / Red')->id])->assertSessionHas('error');
        $other = Product::factory()->create();
        $this->from($p->url())->post('/cart', ['product_id' => $p->id, 'variant_id' => ProductVariant::query()->create(['product_id' => $other->id, 'option1' => 'X', 'price' => 1])->id])->assertSessionHas('error');

        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $sBlack->id, 'quantity' => 2])->assertSessionHas('cart_added.variant', 'S / Black');
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $mBlack->id]);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.key', "{$p->id}:{$sBlack->id}")
            ->where('items.0.variant', 'S / Black')
            ->where('items.0.unit_price', '₦5,000')
            ->where('items.1.line_total', '₦6,000')
            ->where('subtotal', '₦16,000'));

        $this->patch("/cart/{$p->id}:{$sBlack->id}", ['quantity' => 1]);
        $this->delete("/cart/{$p->id}:{$mBlack->id}");
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('subtotal', '₦5,000'));

        // A variation switched off later drops out of the cart.
        $sBlack->update(['is_enabled' => false]);
        $this->freshRequest();
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 0)->whereNot('notice', null));
    }

    public function test_checkout_saves_the_variant_on_the_order(): void
    {
        $p = $this->tee();
        $mBlack = $this->variant($p, 'M / Black');
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $mBlack->id, 'quantity' => 2]);

        $this->post('/checkout', [
            'customer_name' => 'Ada', 'customer_phone' => '08012345678', 'customer_email' => 'ada@example.com',
            'country' => 'NG', 'state' => 'LA', 'address' => '1 Road', 'city' => 'Ikeja', 'payment_method' => 'delivery',
        ])->assertSessionHasNoErrors();

        $order = Order::query()->with('items')->sole();
        $item = $order->items[0];
        $this->assertSame($mBlack->id, $item->product_variant_id);
        $this->assertSame('M / Black', $item->variant_label);
        $this->assertSame('6000.00', $item->unit_price);
        $this->assertSame('12000.00', $order->subtotal);
        $this->assertStringContainsString('2 × Classic Tee (M / Black)', WhatsApp::orderMessage($order));
        $this->get($order->url())->assertInertia(fn (Assert $page) => $page->where('order.items.0.variant', 'M / Black'));
    }

    public function test_coupons_use_variant_prices_and_sale_status(): void
    {
        $p = $this->tee();
        $sBlack = $this->variant($p, 'S / Black');
        $mBlack = $this->variant($p, 'M / Black'); // on sale (compare at 8,000)
        Coupon::query()->create(['code' => 'TEN', 'discount_type' => 'percent', 'amount' => 10, 'exclude_sale_items' => true]);
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $sBlack->id]);
        $this->post('/cart', ['product_id' => $p->id, 'variant_id' => $mBlack->id]);

        $this->post('/cart/coupon', ['coupon' => 'TEN'])->assertSessionHasNoErrors();
        // 10% of the S / Black only (5,000); the sale variation is excluded.
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('coupon.discount', 500));
    }

    public function test_simple_products_and_old_carts_still_work(): void
    {
        $simple = Product::factory()->create(['price' => 2000]);
        $this->post('/cart', ['product_id' => $simple->id, 'quantity' => 3]);
        $this->patch("/cart/{$simple->id}", ['quantity' => 2]);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.key', (string) $simple->id)
            ->where('items.0.variant', null)
            ->where('subtotal', '₦4,000'));

        // A variable product can't sneak in without a variant (e.g. an old session).
        $p = $this->tee();
        session()->put('cart', [$simple->id => 1, $p->id => 1]);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 1));
    }
}
