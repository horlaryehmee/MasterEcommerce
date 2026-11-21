<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Mail\OrderUpdated;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        BusinessSetting::current()->update(['shop_enabled' => true, 'email' => 'owner@shop.test', 'phone' => '0809 000 0000', 'business_name' => 'Kettle Co']);
        BusinessSetting::forgetResolved();
    }

    protected function placeOrder(array $overrides = []): Order
    {
        $product = Product::factory()->create(['price' => 10000, 'name' => 'Blue Kettle']);
        $this->post('/cart', ['product_id' => $product->id]);
        $this->post('/checkout', $overrides + [
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'customer_email' => 'ada@example.com',
            'country' => 'NG',
            'state' => 'LA',
            'address' => '12 Allen Avenue',
            'city' => 'Ikeja',
            'payment_method' => 'delivery',
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    protected function settings(array $values): void
    {
        BusinessSetting::current()->update($values);
        BusinessSetting::forgetResolved();
    }

    public function test_new_order_emails_the_owner_and_customer_with_reply_to(): void
    {
        $this->placeOrder();

        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $m) => $m->forOwner && $m->hasTo('owner@shop.test') && $m->hasReplyTo('ada@example.com'));
        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $m) => ! $m->forOwner && $m->hasTo('ada@example.com') && $m->hasReplyTo('owner@shop.test'));
    }

    public function test_new_order_emails_can_be_switched_off(): void
    {
        $this->settings(['email_owner_new_order' => false, 'email_customer_new_order' => false]);
        $this->placeOrder();

        Mail::assertNothingSent();
    }

    public function test_status_and_payment_changes_email_the_customer(): void
    {
        $order = $this->placeOrder();
        $this->actingAsAdmin();

        $this->patch("/admin/orders/{$order->id}", ['status' => 'confirmed'])->assertSessionHas('success', "Order #{$order->number} updated. The customer has been emailed.");
        $this->patch("/admin/orders/{$order->id}", ['status' => 'shipped']);
        $this->patch("/admin/orders/{$order->id}", ['payment_status' => 'paid']);

        foreach (['status.confirmed', 'status.shipped', 'payment.paid'] as $event) {
            Mail::assertSent(OrderUpdated::class, fn (OrderUpdated $m) => $m->event === $event && $m->hasTo('ada@example.com'));
        }
        Mail::assertSent(OrderUpdated::class, 3);
    }

    public function test_no_update_email_when_nothing_changed_reverting_switched_off_or_no_email(): void
    {
        $order = $this->placeOrder();
        $this->actingAsAdmin();

        // Same value, or a correction back to New: silent.
        $this->patch("/admin/orders/{$order->id}", ['status' => 'pending'])->assertSessionHas('success', "Order #{$order->number} updated.");
        $order->update(['status' => 'confirmed']);
        $this->patch("/admin/orders/{$order->id}", ['status' => 'pending']);
        Mail::assertNotSent(OrderUpdated::class);

        $this->settings(['email_customer_updates' => false]);
        $this->patch("/admin/orders/{$order->id}", ['status' => 'completed']);
        Mail::assertNotSent(OrderUpdated::class);

        $this->settings(['email_customer_updates' => true]);
        $noEmail = $this->placeOrder(['customer_email' => '']);
        $this->patch("/admin/orders/{$noEmail->id}", ['status' => 'cancelled']);
        Mail::assertNotSent(OrderUpdated::class);
    }

    public function test_update_emails_render(): void
    {
        $order = $this->placeOrder();

        $shipped = new OrderUpdated($order, 'status.shipped');
        $html = $shipped->render();
        $this->assertSame('Your order is on its way — Kettle Co order #'.$order->number, $shipped->envelope()->subject);
        $this->assertStringContainsString('12 Allen Avenue', $html);
        $this->assertStringContainsString('Blue Kettle', $html);
        $this->assertStringContainsString($order->url(), $html);
        $this->assertStringContainsString('0809 000 0000', $html);

        $order->update(['fulfilment' => 'pickup']);
        $this->assertSame('Your order is ready for pickup', (new OrderUpdated($order, 'status.shipped'))->headline());
        $this->assertStringContainsString('cancelled', (new OrderUpdated($order, 'status.cancelled'))->render());
    }

    public function test_settings_save_email_switches_and_send_a_test_email(): void
    {
        $this->actingAsAdmin();

        $this->put('/admin/settings/shop', [
            'shop_enabled' => true, 'pay_on_delivery_enabled' => true,
            'email_owner_new_order' => true, 'email_customer_new_order' => false, 'email_customer_updates' => true,
            'order_notification_email' => 'orders@shop.test',
        ])->assertSessionHasNoErrors();

        $s = BusinessSetting::query()->first();
        $this->assertTrue($s->email_owner_new_order);
        $this->assertFalse($s->email_customer_new_order);
        $this->assertSame('orders@shop.test', $s->orderNotificationAddress());

        // Tests use the "array" mailer, so the admin is told mail isn't really delivered yet.
        $this->post('/admin/settings/email/test')->assertRedirect('/admin/settings?tab=email')
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'email sending is off'));

        // No order or business email: the test goes to the signed-in admin instead.
        $this->put('/admin/settings/shop', ['shop_enabled' => true, 'pay_on_delivery_enabled' => true, 'order_notification_email' => '']);
        $this->settings(['email' => null]);
        $this->post('/admin/settings/email/test')->assertSessionHas('success');
        $this->post('/admin/settings/email/test', ['to' => 'not-an-email'])->assertSessionHasErrors('to');
    }
}
