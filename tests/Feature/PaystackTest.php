<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Paystack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaystackTest extends TestCase
{
    use RefreshDatabase;

    // Fake keys, split in two so secret scanners (e.g. GitHub push protection) don't flag them.
    protected const SECRET = 'sk_'.'test_abcdefghijklmnopqrstuvwxyz0123456789';

    protected const LIVE = 'sk_'.'live_abcdefghijklmnopqrstuvwxyz0123456789';

    protected const TEST_PUBLIC = 'pk_'.'test_abcdefghijklmnopqrstuvwxyz0123456789';

    protected const LIVE_PUBLIC = 'pk_'.'live_abcdefghijklmnopqrstuvwxyz0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::current()->update([
            'shop_enabled' => true,
            'pay_on_delivery_enabled' => true,
            'pay_paystack_enabled' => true,
            'paystack_mode' => 'test',
            'paystack_test_secret_key' => self::SECRET,
            'paystack_test_public_key' => self::TEST_PUBLIC,
            'currency_code' => 'NGN',
        ]);
        BusinessSetting::forgetResolved();
    }

    protected string $checkoutUrl = 'https://checkout.paystack.com/abc123';

    protected array $verifyData = [];

    protected bool $referenceUnknown = false;

    /** One fake for the whole test; the helpers change what it answers. */
    protected function fakePaystack(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => ['authorization_url' => $this->checkoutUrl, 'reference' => 'x']]);
            }
            if (str_contains($request->url(), '/bank')) {
                // Paystack accepts only the two real keys of this account.
                $ok = in_array($request->header('Authorization')[0] ?? '', ['Bearer '.self::SECRET, 'Bearer '.self::LIVE], true);

                return Http::response($ok ? ['status' => true, 'data' => []] : ['status' => false, 'message' => 'Invalid key'], $ok ? 200 : 401);
            }
            if (str_contains($request->url(), '/transaction/verify/') && $this->referenceUnknown) {
                return Http::response(['status' => false, 'message' => 'Transaction reference not found'], 400);
            }
            if (str_contains($request->url(), '/transaction/verify/')) {
                return Http::response(['status' => true, 'data' => $this->verifyData]);
            }

            return Http::response(['status' => false], 404);
        });
    }

    protected function fakeInitialize(string $url = 'https://checkout.paystack.com/abc123'): void
    {
        $this->checkoutUrl = $url;
        $this->fakePaystack();
    }

    protected function fakeVerify(Payment $payment, array $data = []): void
    {
        $this->fakePaystack();
        $this->verifyData = $data + [
            'id' => 4099260516,
            'status' => 'success',
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => 'NGN',
            'channel' => 'card',
            'domain' => 'test',
            'gateway_response' => 'Successful',
        ];
    }

    /** Places an order for ₦12,345.60 paid with Paystack; returns it. */
    protected function checkout(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $product = Product::factory()->create(['price' => 12345.60]);
        $this->post('/cart', ['product_id' => $product->id]);

        return $this->post('/checkout', $overrides + [
            'customer_name' => 'Ada Obi',
            'customer_phone' => '0801 234 5678',
            'customer_email' => 'ada@example.com',
            'country' => 'NG',
            'state' => 'LA',
            'address' => '12 Allen Avenue',
            'city' => 'Ikeja',
            'payment_method' => 'paystack',
        ]);
    }

    public function test_amounts_convert_to_kobo_exactly(): void
    {
        $this->assertSame(1234560, Paystack::toSubunit('12345.60'));
        $this->assertSame(1234560, Paystack::toSubunit(12345.6));
        $this->assertSame(1, Paystack::toSubunit('0.01'));
        $this->assertSame(29, Paystack::toSubunit('0.29')); // float maths would give 28
        $this->assertSame(100000000000, Paystack::toSubunit('1000000000'));
    }

    public function test_checkout_sends_the_customer_to_paystack_with_the_server_side_amount(): void
    {
        $this->fakeInitialize();

        $this->checkout(['total' => 1, 'amount' => 1])->assertRedirect('https://checkout.paystack.com/abc123');

        $order = Order::firstOrFail();
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(1234560, $payment->amount);
        $this->assertMatchesRegularExpression('/^NX'.$order->number.'-[A-Z0-9]{24}$/', $payment->reference);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.paystack.co/transaction/initialize'
            && $r['amount'] === 1234560 && $r['currency'] === 'NGN' && $r['reference'] === $payment->reference
            && $r->hasHeader('Authorization', 'Bearer '.self::SECRET));
    }

    public function test_online_payment_needs_an_email(): void
    {
        $this->fakeInitialize();
        $this->checkout(['customer_email' => ''])->assertSessionHasErrors('customer_email');
        $this->assertSame(0, Order::count());
    }

    public function test_a_non_paystack_checkout_url_is_never_followed(): void
    {
        $this->fakeInitialize('https://evil.example/phish');

        $response = $this->checkout();
        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order->token))->assertSessionHas('error');
        $this->assertSame('failed', $order->payments()->first()->status);
    }

    public function test_callback_verifies_with_paystack_before_marking_paid(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();
        $this->fakeVerify($payment);

        $this->get('/payments/paystack/callback?reference='.$payment->reference)->assertRedirect(route('orders.show', $order->token));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        // Paid: now a New order for the owner to fulfil.
        $this->assertSame('pending', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('success', $payment->fresh()->status);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.paystack.co/transaction/verify/'.$payment->reference);
    }

    public function test_underpaid_or_wrong_currency_payments_are_flagged_not_paid(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();

        $this->fakeVerify($payment, ['amount' => 100]);
        $this->get('/payments/paystack/callback?reference='.$payment->reference)->assertSessionHas('error');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('flagged', $payment->fresh()->status);
    }

    public function test_failed_payment_leaves_the_order_unpaid_and_offers_pay_now(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();
        $this->fakeVerify($payment, ['status' => 'abandoned']);

        $this->get('/payments/paystack/callback?reference='.$payment->reference);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('payUrl', route('orders.pay', $order->token)));

        $this->fakeInitialize('https://checkout.paystack.com/retry');
        $this->post(route('orders.pay', $order->token))->assertRedirect('https://checkout.paystack.com/retry');
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_callback_rejects_unknown_or_malformed_references(): void
    {
        Http::fake();
        $this->get('/payments/paystack/callback?reference=NX1001-AAAAAAAAAAAAAAAAAAAAAAAA')->assertNotFound();
        $this->get("/payments/paystack/callback?reference=1' OR '1'='1")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_webhook_requires_a_valid_signature_and_is_re_verified(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference, 'amount' => $payment->amount, 'status' => 'success']]);

        // Forged: no / wrong signature.
        $this->call('POST', '/payments/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
        $this->call('POST', '/payments/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_wrong')], $body)->assertStatus(401);
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Correctly signed, but Paystack's API says it wasn't paid: still not paid.
        $this->fakeVerify($payment, ['status' => 'failed']);
        $signed = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET)];
        $this->call('POST', '/payments/paystack/webhook', [], [], [], $signed, $body)->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Signed and verified: paid. Repeats are harmless.
        $this->fakeVerify($payment);
        $this->call('POST', '/payments/paystack/webhook', [], [], [], $signed, $body)->assertOk();
        $this->call('POST', '/payments/paystack/webhook', [], [], [], $signed, $body)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, Payment::where('status', 'success')->count());
    }

    public function test_secret_key_is_encrypted_and_never_sent_to_the_browser(): void
    {
        $this->actingAsAdmin();

        $raw = \DB::table('business_settings')->value('paystack_test_secret_key');
        $this->assertStringNotContainsString(self::SECRET, $raw);

        $response = $this->get('/admin/settings?tab=shop')->assertOk();
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
        $response->assertInertia(fn (Assert $p) => $p->where('paystack.keys.test.hint', 'sk_test_…6789')->missing('settings.paystack_test_secret_key'));
    }

    protected function saveShop(array $data): \Illuminate\Testing\TestResponse
    {
        $this->fakePaystack();

        return $this->put('/admin/settings/shop', $data + ['shop_enabled' => true, 'pay_paystack_enabled' => true, 'pay_on_delivery_enabled' => true, 'paystack_test_public_key' => self::TEST_PUBLIC]);
    }

    public function test_mixed_up_keys_are_caught_with_a_clear_message(): void
    {
        $this->actingAsAdmin();

        // Test key pasted into the live field, live key into the test field, public key instead of secret.
        $this->saveShop(['paystack_live_secret_key' => self::SECRET])->assertSessionHasErrors(['paystack_live_secret_key' => 'This is a TEST key. The live secret key starts with sk_live_. In Paystack, switch the dashboard to Live Mode to copy it.']);
        $this->saveShop(['paystack_test_secret_key' => self::LIVE])->assertSessionHasErrors('paystack_test_secret_key');
        $this->saveShop(['paystack_live_secret_key' => 'pk_'.'live_abcdefghijklmnopqrstuvwxyz0123456789'])->assertSessionHasErrors('paystack_live_secret_key');
        $this->saveShop(['paystack_live_secret_key' => 'sk_live 123'])->assertSessionHasErrors('paystack_live_secret_key');

        // Right shape, but Paystack doesn't recognise it.
        $this->saveShop(['paystack_live_secret_key' => 'sk_'.'live_zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'])->assertSessionHasErrors('paystack_live_secret_key');

        // Live mode needs a live key.
        $this->saveShop(['paystack_mode' => 'live'])->assertSessionHasErrors('paystack_live_secret_key');
        BusinessSetting::forgetResolved();
        $this->assertNull(BusinessSetting::current()->paystack_live_secret_key);
    }

    public function test_switching_to_live_mode_with_a_valid_key(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.paystack_test_mode', true));

        // Live mode needs the live public key too.
        $this->saveShop(['paystack_mode' => 'live', 'paystack_live_secret_key' => self::LIVE])->assertSessionHasErrors('paystack_live_public_key');
        $this->saveShop(['paystack_mode' => 'live', 'paystack_live_secret_key' => self::LIVE, 'paystack_live_public_key' => self::LIVE_PUBLIC])->assertSessionHasNoErrors();
        BusinessSetting::forgetResolved();
        $s = BusinessSetting::current();
        $this->assertSame('live', $s->paystack_mode);
        $this->assertSame(self::LIVE, $s->paystack_live_secret_key);
        // Blank fields keep the saved keys.
        $this->assertSame(self::SECRET, $s->paystack_test_secret_key);
        $this->assertSame(self::LIVE, app(Paystack::class)->secretKey($s));

        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.paystack_test_mode', false));
    }

    public function test_a_test_key_saved_in_the_live_slot_is_never_used(): void
    {
        BusinessSetting::current()->update(['paystack_mode' => 'live', 'paystack_live_secret_key' => self::SECRET]);
        BusinessSetting::forgetResolved();

        $this->assertNull(app(Paystack::class)->secretKey());
        $this->assertFalse(app(Paystack::class)->ready());
    }

    public function test_mixed_up_public_keys_are_caught(): void
    {
        $this->actingAsAdmin();

        // Secret key pasted as the public key, test public key in the live field, and nonsense.
        $this->saveShop(['paystack_live_public_key' => self::LIVE])->assertSessionHasErrors(['paystack_live_public_key' => 'This is your SECRET key. Paste the public key here instead: it starts with pk_live_. Keep the secret key private.']);
        $this->saveShop(['paystack_live_public_key' => self::TEST_PUBLIC])->assertSessionHasErrors('paystack_live_public_key');
        $this->saveShop(['paystack_test_public_key' => self::LIVE_PUBLIC])->assertSessionHasErrors('paystack_test_public_key');
        $this->saveShop(['paystack_test_public_key' => 'pk_test 123'])->assertSessionHasErrors('paystack_test_public_key');

        // Test mode on without its public key.
        $this->saveShop(['paystack_test_public_key' => ''])->assertSessionHasErrors('paystack_test_public_key');

        // Saved public keys are shown in full (they're public); secret keys never are.
        $this->saveShop(['paystack_live_public_key' => self::LIVE_PUBLIC])->assertSessionHasNoErrors();
        $this->get('/admin/settings?tab=shop')->assertInertia(fn (Assert $p) => $p
            ->where('paystack.keys.live.public', self::LIVE_PUBLIC)
            ->where('paystack.keys.test.public', self::TEST_PUBLIC)
            ->missing('paystack.keys.test.secret'));
        BusinessSetting::forgetResolved();
        $this->assertSame(self::LIVE_PUBLIC, app(Paystack::class)->publicKey(null, 'live'));
    }

    public function test_cancelling_on_paystack_shows_not_paid_not_thank_you(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        BusinessSetting::current()->update(['email_customer_new_order' => true, 'email_owner_new_order' => true, 'email' => 'owner@shop.test']);
        BusinessSetting::forgetResolved();

        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();

        // No "thank you" email for an order that isn't paid.
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        // Paystack's "Cancel" button comes back through the callback with the reference.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/transaction/initialize')
            && $r['metadata']['cancel_action'] === route('payments.paystack.callback', ['reference' => $payment->reference, 'cancelled' => 1]));

        // Chose "Pay with transfer", then cancelled: Paystack still says the transfer is waiting.
        $this->fakeVerify($payment, ['status' => 'ongoing']);
        $this->get(route('payments.paystack.callback', ['reference' => $payment->reference, 'cancelled' => 1]))
            ->assertRedirect(route('orders.show', $order->token))
            ->assertSessionHas('error', 'Payment cancelled. You haven’t been charged.');

        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p
            ->where('paymentState', 'not_paid')
            ->where('payUrl', route('orders.pay', $order->token)));
        $this->assertSame('awaiting_payment', $order->fresh()->status);
        // Not shown as a new order to ship.
        $this->actingAsAdmin();
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.new_orders', 0));
    }

    public function test_payment_page_never_opened_counts_as_not_paid(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();
        $this->referenceUnknown = true;

        $this->get(route('payments.paystack.callback', ['reference' => $payment->reference]))->assertSessionHas('error');
        $this->assertSame('abandoned', $payment->fresh()->status);
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('paymentState', 'not_paid'));
    }

    public function test_slow_payments_show_confirming_without_a_pay_button(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();
        $this->fakeVerify($payment, ['status' => 'ongoing']);

        $this->get(route('payments.paystack.callback', ['reference' => $payment->reference]))->assertSessionMissing('error');
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('paymentState', 'confirming')->where('payUrl', route('orders.pay', $order->token)));

        // After 15 minutes of waiting, the customer can pay again.
        $this->travel(16)->minutes();
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('paymentState', 'not_paid'));

        // The bank confirms: the page (re-checking with Paystack) now shows paid.
        $this->fakeVerify($payment);
        \Illuminate\Support\Facades\Cache::flush();
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('paymentState', 'paid'));
    }

    public function test_confirmation_email_is_sent_once_paid(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        BusinessSetting::current()->update(['email_customer_new_order' => true, 'email_owner_new_order' => true, 'email' => 'owner@shop.test']);
        BusinessSetting::forgetResolved();

        $this->fakeInitialize();
        $this->checkout();
        $payment = Order::firstOrFail()->payments()->first();
        $this->fakeVerify($payment);
        $this->get(route('payments.paystack.callback', ['reference' => $payment->reference]))->assertSessionHas('success', 'Payment received. Thank you!');

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OrderPlaced::class, 2); // customer + owner
        \Illuminate\Support\Facades\Mail::assertNotSent(\App\Mail\OrderUpdated::class);
    }

    public function test_unpaid_online_orders_are_cancelled_after_a_day_and_not_counted_as_sales(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $payment = $order->payments()->first();

        $this->actingAsAdmin();
        $this->get('/admin/customers')->assertInertia(fn (Assert $p) => $p->where('customers.data.0.spent', '₦0'));

        $this->fakeVerify($payment, ['status' => 'abandoned']);
        $this->travel(23)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('awaiting_payment', $order->fresh()->status);

        $this->travel(2)->hours();
        $this->artisan('orders:cancel-unpaid')->expectsOutput('1 unpaid order cancelled.');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->get(route('orders.show', $order->token))->assertInertia(fn (Assert $p) => $p->where('paymentState', 'cancelled')->where('payUrl', null));
    }

    public function test_a_late_payment_is_recorded_instead_of_cancelling(): void
    {
        $this->fakeInitialize();
        $this->checkout();
        $order = Order::firstOrFail();
        $this->fakeVerify($order->payments()->first());

        $this->travel(25)->hours();
        $this->artisan('orders:cancel-unpaid')->expectsOutput('0 unpaid orders cancelled.');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
