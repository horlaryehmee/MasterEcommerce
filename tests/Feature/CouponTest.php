<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        BusinessSetting::current()->update(['shop_enabled' => true, 'whatsapp_number' => '2348012345678']);
        BusinessSetting::forgetResolved();
    }

    protected function product(array $attrs = []): Product
    {
        return Product::factory()->create($attrs + ['price' => 10000]);
    }

    protected function coupon(array $attrs = []): Coupon
    {
        return Coupon::query()->create($attrs + ['code' => 'SAVE10', 'discount_type' => 'percent', 'amount' => 10]);
    }

    protected function zone(array $methods): ShippingZone
    {
        $zone = ShippingZone::query()->create(['name' => 'Lagos', 'sort_order' => 0]);
        $zone->locations()->create(['type' => 'state', 'code' => 'NG:LA']);
        foreach ($methods as $i => [$type, $title, $settings]) {
            $zone->methods()->create(['type' => $type, 'title' => $title, 'settings' => $settings, 'sort_order' => $i]);
        }

        return $zone->load('methods');
    }

    protected function checkoutData(array $overrides = []): array
    {
        return $overrides + [
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'customer_email' => 'ada@example.com',
            'country' => 'NG',
            'state' => 'LA',
            'address' => '12 Allen Avenue',
            'city' => 'Ikeja',
            'payment_method' => 'delivery',
        ];
    }

    protected function addToCart(Product $product, int $quantity = 1): void
    {
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_percentage_coupon_is_applied_in_cart_and_saved_on_the_order(): void
    {
        $zone = $this->zone([['flat_rate', 'Standard delivery', ['cost' => 3000, 'per_item' => 0]]]);
        $product = $this->product();
        $this->coupon();
        $this->addToCart($product, 2);

        $this->from('/cart')->post('/cart/coupon', ['coupon' => ' save10 '])->assertRedirect('/cart')->assertSessionHasNoErrors();

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('coupon.code', 'SAVE10')
            ->where('coupon.discount', 2000)
            ->where('total', '₦18,000')
            ->where('couponsEnabled', true));
        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page->where('discount', 2000)->where('coupon.code', 'SAVE10'));

        $this->post('/checkout', $this->checkoutData(['shipping_method' => $zone->methods[0]->id]))->assertRedirect();

        $order = Order::query()->with('items')->sole();
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame('2000.00', $order->discount_total);
        $this->assertSame('20000.00', $order->subtotal);
        $this->assertSame('21000.00', $order->total); // 20,000 − 2,000 + 3,000 delivery
        $this->assertSame('2000.00', $order->items[0]->discount);
        $this->assertStringContainsString('Coupon (SAVE10): −₦2,000', WhatsApp::orderMessage($order));
        // The coupon is used up with the cart.
        $this->assertNull(session('cart_coupon'));
    }

    public function test_fixed_cart_discount_is_shared_between_items_and_capped(): void
    {
        $a = $this->product(['price' => 3000]);
        $b = $this->product(['price' => 1000]);
        $this->coupon(['code' => 'OFF1000', 'discount_type' => 'fixed_cart', 'amount' => 1000]);
        $this->addToCart($a);
        $this->addToCart($b);
        $this->post('/cart/coupon', ['coupon' => 'OFF1000']);
        $this->post('/checkout', $this->checkoutData());

        $order = Order::query()->with('items')->sole();
        $this->assertSame('1000.00', $order->discount_total);
        $this->assertEquals([750, 250], $order->items->pluck('discount')->map(fn ($d) => (float) $d)->all());

        // Never more than the cart is worth.
        Coupon::query()->create(['code' => 'BIG', 'discount_type' => 'fixed_cart', 'amount' => 50000]);
        $this->addToCart($b);
        $this->post('/cart/coupon', ['coupon' => 'BIG']);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('coupon.discount', 1000)->where('total', '₦0'));
    }

    public function test_fixed_product_discount_respects_restrictions_and_item_limit(): void
    {
        $phones = $this->product(['price' => 5000]);
        $sale = $this->product(['price' => 4000, 'compare_at_price' => 6000]);
        $other = $this->product(['price' => 2000]);
        $this->coupon([
            'code' => 'PERITEM', 'discount_type' => 'fixed_product', 'amount' => 500,
            'product_ids' => [$phones->id, $sale->id], 'exclude_sale_items' => true, 'limit_usage_to_items' => 2,
        ]);
        $this->addToCart($phones, 3);
        $this->addToCart($sale);
        $this->addToCart($other);
        $this->post('/cart/coupon', ['coupon' => 'PERITEM'])->assertSessionHasNoErrors();

        // Only the eligible phone, and only 2 of its 3 units.
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('coupon.discount', 1000));
    }

    public function test_coupon_that_matches_no_cart_items_is_refused(): void
    {
        $in = $this->product();
        $out = $this->product();
        $this->coupon(['product_ids' => [$in->id]]);
        $this->addToCart($out);

        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasErrors(['coupon' => 'Sorry, this coupon doesn’t apply to the items in your cart.']);
    }

    public function test_invalid_codes_and_rules_give_clear_errors(): void
    {
        $this->addToCart($this->product(['price' => 5000]));

        $this->post('/cart/coupon', ['coupon' => 'NOPE'])->assertSessionHasErrors(['coupon' => 'Coupon “NOPE” is not valid.']);

        $this->coupon(['code' => 'OFF', 'is_active' => false]);
        $this->post('/cart/coupon', ['coupon' => 'OFF'])->assertSessionHasErrors(['coupon' => 'This coupon is not valid.']);

        $this->coupon(['code' => 'OLD', 'expires_on' => Carbon::now('Africa/Lagos')->subDay()->toDateString()]);
        $this->post('/cart/coupon', ['coupon' => 'OLD'])->assertSessionHasErrors(['coupon' => 'This coupon has expired.']);

        $this->coupon(['code' => 'SOON', 'starts_on' => Carbon::now('Africa/Lagos')->addDays(2)->toDateString()]);
        $this->post('/cart/coupon', ['coupon' => 'SOON'])->assertSessionHasErrors(['coupon' => 'This coupon is not valid yet.']);

        $this->coupon(['code' => 'MIN', 'minimum_spend' => 10000]);
        $this->post('/cart/coupon', ['coupon' => 'MIN'])->assertSessionHasErrors(['coupon' => 'The minimum spend for this coupon is ₦10,000.']);

        $this->coupon(['code' => 'MAX', 'maximum_spend' => 1000]);
        $this->post('/cart/coupon', ['coupon' => 'MAX'])->assertSessionHasErrors(['coupon' => 'The maximum spend for this coupon is ₦1,000.']);

        // Today's expiry is still valid.
        $this->coupon(['code' => 'TODAY', 'expires_on' => Carbon::now('Africa/Lagos')->toDateString()]);
        $this->post('/cart/coupon', ['coupon' => 'TODAY'])->assertSessionHasNoErrors();
    }

    public function test_coupon_is_removed_when_the_cart_no_longer_qualifies(): void
    {
        $product = $this->product(['price' => 6000]);
        $this->coupon(['minimum_spend' => 10000]);
        $this->addToCart($product, 2);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasNoErrors();

        $this->patch("/cart/{$product->id}", ['quantity' => 1]);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('coupon', null)
            ->where('couponNotice', 'Coupon “SAVE10” was removed: The minimum spend for this coupon is ₦10,000.'));
    }

    public function test_order_is_not_placed_at_full_price_when_the_coupon_stopped_working(): void
    {
        $this->addToCart($this->product());
        $coupon = $this->coupon();
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
        $coupon->update(['is_active' => false]);

        $this->post('/checkout', $this->checkoutData())->assertSessionHasErrors('coupon');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_usage_limits_are_enforced_and_cancelled_orders_give_uses_back(): void
    {
        $product = $this->product();
        $coupon = $this->coupon(['usage_limit' => 1]);

        $this->addToCart($product);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
        $this->post('/checkout', $this->checkoutData())->assertSessionHasNoErrors();
        $this->assertSame(1, $coupon->usageCount());

        $this->addToCart($product);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasErrors(['coupon' => 'This coupon has reached its usage limit.']);

        Order::query()->sole()->update(['status' => 'cancelled']);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasNoErrors();
    }

    public function test_per_customer_limit_matches_email_or_phone(): void
    {
        $product = $this->product();
        $this->coupon(['usage_limit_per_customer' => 1]);

        $this->addToCart($product);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
        $this->post('/checkout', $this->checkoutData())->assertSessionHasNoErrors();

        // Same phone in international format, different email.
        $this->addToCart($product);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasNoErrors();
        $this->post('/checkout', $this->checkoutData(['customer_email' => 'other@example.com', 'customer_phone' => '+234 801 234 5678']))
            ->assertSessionHasErrors(['coupon' => 'You have already used this coupon.']);

        // Someone else can still use it.
        $this->post('/checkout', $this->checkoutData(['customer_email' => 'bola@example.com', 'customer_phone' => '0809 999 0000']))->assertSessionHasNoErrors();
        $this->assertSame(2, Order::query()->whereNotNull('coupon_id')->count());
    }

    public function test_allowed_emails_are_checked_at_checkout(): void
    {
        $this->addToCart($this->product());
        $this->coupon(['allowed_emails' => ['*@company.com']]);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasNoErrors();

        $this->post('/checkout', $this->checkoutData())->assertSessionHasErrors(['coupon' => 'Sorry, this coupon is not valid for your email address.']);
        $this->post('/checkout', $this->checkoutData(['customer_email' => '']))->assertSessionHasErrors(['coupon' => 'Enter the email address this coupon was issued to.']);
        $this->post('/checkout', $this->checkoutData(['customer_email' => 'Ada@Company.com']))->assertSessionHasNoErrors();
    }

    public function test_free_shipping_coupon_unlocks_coupon_only_free_shipping(): void
    {
        $zone = $this->zone([
            ['flat_rate', 'Standard delivery', ['cost' => 3000, 'per_item' => 0]],
            ['free_shipping', 'Free delivery', ['requires' => 'coupon', 'min_amount' => 0]],
        ]);
        $this->addToCart($this->product());

        $this->getJson('/checkout/rates?country=NG&state=LA')->assertJsonCount(1, 'rates');

        $this->coupon(['code' => 'SHIPFREE', 'amount' => 0, 'free_shipping' => true]);
        $this->post('/cart/coupon', ['coupon' => 'SHIPFREE'])->assertSessionHasNoErrors();

        $this->getJson('/checkout/rates?country=NG&state=LA')->assertJsonCount(2, 'rates')->assertJsonPath('rates.1.title', 'Free delivery');
        $this->post('/checkout', $this->checkoutData(['shipping_method' => $zone->methods[1]->id]))->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('10000.00', $order->total);
        $this->assertSame('SHIPFREE', $order->coupon_code);
    }

    public function test_free_shipping_minimum_uses_the_total_after_discount(): void
    {
        $this->zone([['free_shipping', 'Free delivery', ['requires' => 'min_amount', 'min_amount' => 10000]]]);
        $this->addToCart($this->product());
        $this->getJson('/checkout/rates?country=NG&state=LA')->assertJsonCount(1, 'rates');

        $this->coupon();
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);

        $this->getJson('/checkout/rates?country=NG&state=LA')->assertJsonCount(0, 'rates');
    }

    public function test_concurrent_last_use_is_not_given_twice(): void
    {
        // Simulates the second of two simultaneous orders: the use was taken after this cart applied it.
        $product = $this->product();
        $coupon = $this->coupon(['usage_limit' => 1]);
        $this->addToCart($product);
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
        Order::query()->create([
            'customer_name' => 'X', 'customer_phone' => '1', 'payment_method' => 'delivery',
            'subtotal' => 1, 'total' => 1, 'currency_code' => 'NGN', 'coupon_id' => $coupon->id,
        ]);

        $this->post('/checkout', $this->checkoutData())->assertSessionHasErrors('coupon');
        $this->assertSame(1, Order::query()->count());
    }

    public function test_coupon_field_is_hidden_until_a_coupon_exists(): void
    {
        $this->addToCart($this->product());
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('couponsEnabled', false));
        $this->coupon();
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('couponsEnabled', true));
    }

    public function test_coupon_routes_are_throttled(): void
    {
        $this->addToCart($this->product());
        for ($i = 0; $i < 15; $i++) {
            $this->post('/cart/coupon', ['coupon' => "GUESS{$i}"]);
        }
        $this->post('/cart/coupon', ['coupon' => 'GUESS'])->assertStatus(429);
    }

    public function test_admin_can_create_edit_toggle_and_delete_coupons(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();

        $this->post('/admin/coupons', [
            'code' => 'welcome 10', 'discount_type' => 'percent', 'amount' => 10, 'is_active' => true,
            'product_ids' => [(string) $product->id], 'allowed_emails' => 'a@b.com, *@shop.com',
            'usage_limit' => '', 'minimum_spend' => null,
        ])->assertRedirect('/admin/coupons')->assertSessionHasNoErrors();

        $coupon = Coupon::query()->sole();
        $this->assertSame('WELCOME10', $coupon->code);
        $this->assertSame([$product->id], $coupon->product_ids);
        $this->assertSame(['a@b.com', '*@shop.com'], $coupon->allowed_emails);
        $this->assertNull($coupon->usage_limit);

        $this->get('/admin/coupons')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Coupons/Index')
            ->where('coupons.0.code', 'WELCOME10')
            ->where('coupons.0.status', 'active')
            ->where('coupons.0.offer', '10% off'));
        $this->get("/admin/coupons/{$coupon->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Coupons/Form')->where('coupon.used', 0));

        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'WELCOME10', 'discount_type' => 'percent', 'amount' => 150])
            ->assertSessionHasErrors('amount');
        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'WELCOME10', 'discount_type' => 'fixed_cart', 'amount' => 0])
            ->assertSessionHasErrors('amount');
        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'WELCOME10', 'discount_type' => 'fixed_cart', 'amount' => 500, 'starts_on' => '2026-05-10', 'expires_on' => '2026-05-01'])
            ->assertSessionHasErrors('expires_on');
        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'WELCOME10', 'discount_type' => 'fixed_cart', 'amount' => 500])
            ->assertSessionHasNoErrors();
        $this->assertSame('fixed_cart', $coupon->fresh()->discount_type);
        $this->assertNull($coupon->fresh()->product_ids);

        Coupon::query()->create(['code' => 'TAKEN', 'amount' => 5]);
        $this->put("/admin/coupons/{$coupon->id}", ['code' => 'taken', 'discount_type' => 'percent', 'amount' => 5])
            ->assertSessionHasErrors(['code' => 'Another coupon already uses this code.']);

        $this->patch("/admin/coupons/{$coupon->id}/toggle");
        $this->assertFalse($coupon->fresh()->is_active);

        $this->delete("/admin/coupons/{$coupon->id}")->assertRedirect('/admin/coupons');
        $this->assertNull($coupon->fresh());
    }

    public function test_deleting_a_used_coupon_keeps_the_order_snapshot(): void
    {
        $this->addToCart($this->product());
        $coupon = $this->coupon();
        $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
        $this->post('/checkout', $this->checkoutData());

        $this->actingAs(User::factory()->create())->delete("/admin/coupons/{$coupon->id}");

        $order = Order::query()->sole();
        $this->assertNull($order->coupon_id);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame('1000.00', $order->discount_total);
        $this->get("/admin/orders/{$order->id}")->assertInertia(fn (Assert $page) => $page->where('order.coupon_code', 'SAVE10')->where('order.discount', '₦1,000'));
    }
}
