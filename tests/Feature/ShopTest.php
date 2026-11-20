<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\BusinessSetting;
use App\Models\ShippingZone;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function enableShop(array $settings = []): BusinessSetting
    {
        $s = BusinessSetting::current();
        $s->update($settings + [
            'shop_enabled' => true,
            'whatsapp_number' => '2348012345678',
            'email' => 'owner@shop.test',
        ]);
        BusinessSetting::forgetResolved();

        return $s;
    }

    protected function product(array $attrs = []): Product
    {
        return Product::factory()->create($attrs + ['price' => 10000]);
    }

    /** A zone with regions (["NG:LA"]) and methods ([type, title, settings]). */
    protected function zone(string $name, array $regions, array $methods = [], int $order = 0): ShippingZone
    {
        $zone = ShippingZone::query()->create(['name' => $name, 'sort_order' => $order]);
        foreach ($regions as $code) {
            $zone->locations()->create(['type' => str_contains($code, ':') ? 'state' : 'country', 'code' => $code]);
        }
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

    public function test_cart_and_checkout_are_hidden_when_the_shop_is_off(): void
    {
        $product = $this->product();

        $this->get('/cart')->assertNotFound();
        $this->get('/checkout')->assertNotFound();
        $this->post('/cart', ['product_id' => $product->id])->assertNotFound();
        $this->get($product->url())->assertInertia(fn (Assert $page) => $page->where('cart', null));
    }

    public function test_customer_can_add_update_and_remove_cart_items(): void
    {
        $this->enableShop();
        $a = $this->product(['price' => 2500]);
        $b = $this->product(['price' => 1000]);

        $this->from($a->url())->post('/cart', ['product_id' => $a->id, 'quantity' => 2])
            ->assertRedirect($a->url())
            ->assertSessionHas('cart_added.quantity', 2);
        $this->post('/cart', ['product_id' => $a->id])->assertSessionHas('cart_added.quantity', 3);
        $this->post('/cart', ['product_id' => $b->id]);

        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Cart')
            ->has('items', 2)
            ->where('subtotal', '₦8,500')
            ->where('cart.count', 4));

        $this->patch("/cart/{$a->id}", ['quantity' => 1]);
        $this->delete("/cart/{$b->id}");

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('subtotal', '₦2,500'));
    }

    public function test_buy_now_goes_straight_to_checkout(): void
    {
        $this->enableShop();
        $product = $this->product();

        $this->post('/cart', ['product_id' => $product->id, 'checkout' => true])->assertRedirect('/checkout');
    }

    public function test_enquiry_only_and_hidden_products_cannot_be_added(): void
    {
        $this->enableShop();

        foreach ([$this->product(['availability' => 'out_of_stock']), $this->product(['availability' => 'on_request']), Product::factory()->draft()->create()] as $product) {
            $this->from('/products')->post('/cart', ['product_id' => $product->id])
                ->assertRedirect('/products')
                ->assertSessionHas('error');
        }

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 0));
    }

    public function test_items_that_become_unavailable_are_dropped_from_the_cart(): void
    {
        $this->enableShop();
        $product = $this->product();
        $this->post('/cart', ['product_id' => $product->id]);

        $product->update(['availability' => 'out_of_stock']);

        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 0)->whereNot('notice', null));
        $this->get('/checkout')->assertRedirect('/cart');
    }

    public function test_checkout_creates_an_order_with_server_side_totals(): void
    {
        Mail::fake();
        $this->enableShop();
        $lagos = $this->zone('Lagos', ['NG:LA'], [['flat_rate', 'Standard delivery', ['cost' => 3000, 'per_item' => 0]]]);
        $product = $this->product(['price' => 10000]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->get('/checkout')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Checkout')
            ->where('subtotal', 20000)
            ->where('defaultCountry', 'NG')
            ->has('countries', 1)
            ->has('states.NG', 37)
            ->where('shippingConfigured', true)
            ->has('paymentMethods', 2)); // WhatsApp + pay on delivery (bank transfer is off)

        $response = $this->post('/checkout', $this->checkoutData(['shipping_method' => $lagos->methods->first()->id, 'postcode' => '100271']));

        $order = Order::query()->with('items')->sole();
        $response->assertRedirect($order->url());
        $this->assertSame('1001', $order->number);
        $this->assertSame('20000.00', $order->subtotal);
        $this->assertSame('3000.00', $order->delivery_fee);
        $this->assertSame('23000.00', $order->total);
        $this->assertSame(['Lagos', 'Standard delivery', 'flat_rate'], [$order->shipping_zone_name, $order->shipping_method, $order->shipping_method_type]);
        $this->assertSame(['NG', 'Lagos', '100271'], [$order->shipping_country, $order->shipping_state, $order->postcode]);
        $this->assertSame('12 Allen Avenue, Ikeja, Lagos, 100271, Nigeria', $order->addressLine());
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame($product->name, $order->items->first()->product_name);

        // Cart is emptied, and the customer and owner are emailed.
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 0));
        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $m) => $m->hasTo('ada@example.com') && ! $m->forOwner);
        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $m) => $m->hasTo('owner@shop.test') && $m->forOwner);

        $this->get($order->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/OrderConfirmation')
            ->where('order.number', '1001')
            ->where('order.total', '₦23,000')
            ->where('order.shipping_cost', '₦3,000')
            ->where('justPlaced', true)
            ->whereNot('whatsappUrl', null));
    }

    public function test_free_shipping_minimum_and_local_pickup(): void
    {
        $this->enableShop();
        $zone = $this->zone('Nigeria', ['NG'], [
            ['flat_rate', 'Standard', ['cost' => 5000, 'per_item' => 0]],
            ['free_shipping', 'Free delivery', ['requires' => 'min_amount', 'min_amount' => 15000]],
            ['local_pickup', 'Pickup in store', ['cost' => 0, 'address' => 'Shop 4, Main Market']],
        ]);
        [$flat, $free, $pickup] = $zone->methods->all();

        // Under the minimum, free shipping isn't offered.
        $this->post('/cart', ['product_id' => $this->product(['price' => 10000])->id]);
        $this->getJson('/checkout/rates?country=NG&state=FC')->assertOk()->assertJsonCount(2, 'rates')->assertJsonPath('rates.0.cost_label', '₦5,000');
        $this->post('/checkout', $this->checkoutData(['shipping_method' => $free->id]))->assertSessionHasErrors('shipping_method');

        $this->post('/cart', ['product_id' => $this->product(['price' => 10000])->id]);
        $this->getJson('/checkout/rates?country=NG&state=FC')->assertJsonCount(3, 'rates')->assertJsonPath('rates.1.cost_label', 'Free');
        $this->post('/checkout', $this->checkoutData(['shipping_method' => $free->id]))->assertSessionHasNoErrors();
        $this->assertSame('20000.00', Order::query()->latest('id')->first()->total);

        // Pickup needs no street address; the pickup point is stored on the order.
        $this->post('/cart', ['product_id' => $this->product()->id]);
        $this->post('/checkout', $this->checkoutData(['shipping_method' => $pickup->id, 'address' => '', 'city' => '']))->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->first();
        $this->assertSame(['pickup', 'Shop 4, Main Market', null, '0.00'], [$order->fulfilment, $order->address, $order->city, $order->delivery_fee]);
        $this->assertSame('Shop 4, Main Market', $order->addressLine());
    }

    public function test_checkout_validates_the_form(): void
    {
        $this->enableShop();
        $this->zone('Lagos', ['NG:LA'], [['flat_rate', 'Standard', ['cost' => 3000]]]);
        $this->post('/cart', ['product_id' => $this->product()->id]);

        $this->post('/checkout', [])->assertSessionHasErrors(['customer_name', 'customer_phone', 'country', 'address', 'city', 'shipping_method', 'payment_method']);
        // Nigeria has a state list, so the state is required and must be real.
        $this->post('/checkout', $this->checkoutData(['state' => '']))->assertSessionHasErrors('state');
        $this->post('/checkout', $this->checkoutData(['state' => 'XX']))->assertSessionHasErrors('state');
        // Not a country the store sells to.
        $this->post('/checkout', $this->checkoutData(['country' => 'GH']))->assertSessionHasErrors('country');
        // Abuja isn't covered by any zone (and the fallback has no methods).
        $this->post('/checkout', $this->checkoutData(['state' => 'FC']))->assertSessionHasErrors('shipping_method');
        // Bank transfer is switched off.
        $this->post('/checkout', $this->checkoutData(['payment_method' => 'bank_transfer']))->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_without_any_shipping_methods_the_fee_is_confirmed_later(): void
    {
        $this->enableShop();
        $this->post('/cart', ['product_id' => $this->product(['price' => 7000])->id]);
        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page->where('shippingConfigured', false));

        $this->post('/checkout', $this->checkoutData())->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->assertNull($order->shipping_method);
        $this->assertSame('To be confirmed', $order->shippingCostLabel());
        $this->assertSame('7000.00', $order->total);
    }

    public function test_bank_transfer_order_shows_account_details(): void
    {
        $this->enableShop(['pay_bank_transfer_enabled' => true, 'bank_name' => 'GTBank', 'bank_account_name' => 'My Shop Ltd', 'bank_account_number' => '0123456789']);
        $this->post('/cart', ['product_id' => $this->product()->id]);
        $this->post('/checkout', $this->checkoutData(['payment_method' => 'bank_transfer']));

        $order = Order::query()->sole();
        $this->get($order->url())->assertInertia(fn (Assert $page) => $page->where('bank.account_number', '0123456789'));
    }

    public function test_order_pages_need_the_private_token(): void
    {
        $this->get('/order/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/order/1001')->assertNotFound();
    }

    public function test_admin_can_manage_orders_and_shop_settings(): void
    {
        $this->enableShop();
        $this->post('/cart', ['product_id' => $this->product()->id]);
        $this->post('/checkout', $this->checkoutData());
        $order = Order::query()->sole();

        $this->actingAsAdmin();
        $this->get('/admin/orders')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('admin.new_orders', 1));
        $this->get('/admin/orders?search=1001')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get("/admin/orders/{$order->id}")->assertOk();

        $this->patch("/admin/orders/{$order->id}", ['status' => 'confirmed'])->assertSessionHas('success');
        $this->patch("/admin/orders/{$order->id}", ['payment_status' => 'paid']);
        $this->patch("/admin/orders/{$order->id}", ['status' => 'teleported'])->assertSessionHasErrors('status');
        $this->assertSame(['confirmed', 'paid'], [$order->fresh()->status, $order->fresh()->payment_status]);

        // Shop settings: online ordering and payment options.
        $this->put('/admin/settings/shop', [
            'shop_enabled' => true,
            'pay_whatsapp_enabled' => false,
            'pay_on_delivery_enabled' => false,
            'pay_bank_transfer_enabled' => true,
            'bank_name' => 'GTBank',
            'bank_account_name' => 'My Shop',
            'bank_account_number' => '0123456789',
        ])->assertRedirect('/admin/settings?tab=shop');

        $s = BusinessSetting::query()->first();
        $this->assertSame(['bank_transfer' => 'Bank transfer'], $s->paymentMethods());

        // Bank details are required for bank transfer; at least one method must stay on.
        $this->put('/admin/settings/shop', ['shop_enabled' => true, 'pay_bank_transfer_enabled' => true, 'bank_account_number' => ''])->assertSessionHasErrors('bank_account_number');
        $this->put('/admin/settings/shop', ['shop_enabled' => true])->assertSessionHasErrors('pay_on_delivery_enabled');
    }

    public function test_orders_link_is_hidden_from_the_admin_when_the_shop_is_off(): void
    {
        $this->actingAsAdmin()->get('/admin')->assertInertia(fn (Assert $page) => $page->where('admin.shop_enabled', false));
    }

    public function test_order_emails_render(): void
    {
        $this->enableShop(['pay_bank_transfer_enabled' => true, 'bank_name' => 'GTBank', 'bank_account_name' => 'My Shop', 'bank_account_number' => '0123456789']);
        $this->post('/cart', ['product_id' => $this->product(['name' => 'Blue Kettle'])->id]);
        $this->post('/checkout', $this->checkoutData(['payment_method' => 'bank_transfer']));
        $order = Order::query()->with('items')->sole();

        $customer = (new OrderPlaced($order))->render();
        $this->assertStringContainsString('Blue Kettle', $customer);
        $this->assertStringContainsString('0123456789', $customer);
        $this->assertStringContainsString($order->url(), $customer);

        $owner = (new OrderPlaced($order, true))->render();
        $this->assertStringContainsString('0801 234 5678', $owner);
        $this->assertStringContainsString(route('admin.orders.show', $order), $owner);
        $this->assertStringNotContainsString('0123456789', $owner);
    }

    public function test_every_page_shares_the_cart_for_the_drawer_and_cards_flag_quick_add(): void
    {
        $this->enableShop();
        $this->zone('Nigeria', ['NG'], [['free_shipping', 'Free delivery', ['requires' => 'min_amount', 'min_amount' => 30000]]]);
        $buy = $this->product(['price' => 12000, 'name' => 'Desk Fan']);
        $this->product(['availability' => 'on_request']);

        $this->get('/products')->assertInertia(fn (Assert $page) => $page
            ->where('cart.count', 0)
            ->where('cart.items', [])
            ->where('cart.freeDelivery', null)
            ->where('products.data', fn ($cards) => collect($cards)->pluck('purchasable', 'availability')->all() == ['in_stock' => true, 'on_request' => false]));

        $this->from('/products')->post('/cart', ['product_id' => $buy->id, 'quantity' => 2])->assertSessionHas('cart_added.id', $buy->id);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('cart.count', 2)
            ->where('cart.items.0.name', 'Desk Fan')
            ->where('cart.items.0.line_total', '₦24,000')
            ->where('cart.subtotal', '₦24,000')
            ->where('cart.freeDelivery.remaining', '₦6,000')
            ->where('cart.freeDelivery.progress', 80));
    }

    public function test_admin_can_choose_the_product_card_design(): void
    {
        $this->assertSame('modern', BusinessSetting::current()->product_card_style);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('store.card_style', 'modern'));

        $this->actingAsAdmin();
        $this->product();
        $this->get('/admin/settings?tab=branding')->assertInertia(fn (Assert $page) => $page
            ->has('cardStyles', 3)
            ->whereNot('sampleProduct', null));

        $colours = ['primary_color' => '#aa0000', 'secondary_color' => '#f4f1ec', 'accent_color' => '#c2410c'];
        $this->put('/admin/settings/branding', $colours + ['product_card_style' => 'boxed'])->assertSessionHasNoErrors();
        $this->put('/admin/settings/branding', $colours + ['product_card_style' => 'fancy'])->assertSessionHasErrors('product_card_style');

        $s = BusinessSetting::query()->first();
        $this->assertSame('boxed', $s->product_card_style);
        $this->assertSame('#AA0000', $s->primary_color);
        $this->get('/products')->assertInertia(fn (Assert $page) => $page->where('store.card_style', 'boxed'));
    }
}
