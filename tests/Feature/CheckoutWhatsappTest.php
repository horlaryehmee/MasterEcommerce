<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        BusinessSetting::current()->update(['shop_enabled' => true, 'pay_on_delivery_enabled' => true, 'email' => 'owner@shop.test', 'bank_name' => 'GTBank', 'bank_account_name' => 'Kettle Co', 'bank_account_number' => '0123456789']);
        BusinessSetting::forgetResolved();
    }

    protected function checkout(array $overrides = [])
    {
        $product = Product::factory()->create(['price' => 10000]);
        $this->post('/cart', ['product_id' => $product->id]);

        return $this->post('/checkout', $overrides + [
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'customer_email' => 'ada@example.com',
            'country' => 'NG',
            'state' => 'LA',
            'address' => '12 Allen Avenue',
            'city' => 'Ikeja',
            'payment_method' => 'delivery',
        ]);
    }

    public function test_whatsapp_defaults_to_the_phone_number(): void
    {
        $this->checkout(['whatsapp_same' => '1'])->assertRedirect();
        $this->assertSame('0801 234 5678', Order::sole()->customer_whatsapp);
    }

    public function test_a_separate_whatsapp_number_is_required_when_unticked_and_saved(): void
    {
        $this->checkout(['whatsapp_same' => '0'])->assertSessionHasErrors('customer_whatsapp');
        $this->checkout(['whatsapp_same' => '0', 'customer_whatsapp' => 'abc'])->assertSessionHasErrors('customer_whatsapp');

        $this->checkout(['whatsapp_same' => '0', 'customer_whatsapp' => '0903 111 2222'])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('0903 111 2222', $order->customer_whatsapp);
        // Local Nigerian number -> international wa.me link.
        $this->assertStringStartsWith('https://wa.me/2349031112222', $order->customerWhatsappLink('Hi'));

        $this->actingAsAdmin();
        $this->get("/admin/orders/{$order->id}")->assertInertia(fn (Assert $p) => $p
            ->where('order.customer_whatsapp', '0903 111 2222')
            ->where('customerWhatsapp', fn ($url) => str_starts_with($url, 'https://wa.me/2349031112222')));

        Mail::assertSent(OrderPlaced::class, fn ($m) => $m->forOwner && str_contains($m->render(), 'WhatsApp 0903 111 2222'));
    }

    public function test_pay_on_delivery_offers_transfer_on_arrival_with_bank_details(): void
    {
        $this->checkout()->assertRedirect();
        $order = Order::sole();

        $this->get($order->url())->assertInertia(fn (Assert $p) => $p
            ->component('Storefront/OrderConfirmation')
            ->where('bank.account_number', '0123456789'));

        Mail::assertSent(OrderPlaced::class, fn ($m) => ! $m->forOwner && str_contains($m->render(), 'cash or bank transfer') && str_contains($m->render(), '0123456789'));
    }

    public function test_local_numbers_are_converted_with_the_country(): void
    {
        $this->assertSame('2348012345678', WhatsApp::normalize('0801 234 5678', 'NG'));
        $this->assertSame('2348012345678', WhatsApp::normalize('+234 801 234 5678', 'GH'));
        $this->assertSame('233241234567', WhatsApp::normalize('024 123 4567', 'GH'));
        $this->assertSame('12025550123', WhatsApp::normalize('(202) 555-0123', 'US'));
        $this->assertNull(WhatsApp::normalize('0801 234 5678'));
    }
}
