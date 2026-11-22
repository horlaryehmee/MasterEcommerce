<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        Storage::fake('local');
        Storage::fake('public');
        BusinessSetting::current()->update(['bank_account_number' => '0123456789', 'bank_name' => 'Demo Bank', 'pay_bank_transfer_enabled' => true]);
        BusinessSetting::forgetResolved();
        $this->order = $this->makeOrder();
    }

    protected function makeOrder(array $overrides = []): Order
    {
        return Order::create($overrides + [
            'payment_method' => 'bank_transfer', 'customer_name' => 'Ada Obi', 'customer_phone' => '0801 234 5678', 'customer_email' => 'ada@example.com',
            'shipping_country' => 'NG', 'subtotal' => 25000, 'total' => 25000, 'currency_code' => 'NGN',
        ]);
    }

    protected function upload(UploadedFile $file, ?Order $order = null)
    {
        $order ??= $this->order;

        return $this->from($order->url())->post("/order/{$order->token}/receipt", ['receipt' => $file]);
    }

    protected function pdf(string $body = "1 0 obj << /Type /Catalog >> endobj\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n{$body}trailer << /Root 1 0 R >>\n%%EOF\n");
    }

    public function test_customer_uploads_a_screenshot_which_is_stored_privately_as_webp(): void
    {
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p->where('receiptUpload.remaining', 5)->has('receipts', 0));

        $this->upload(UploadedFile::fake()->image('transfer.png', 1200, 2400))->assertRedirect($this->order->url())->assertSessionHas('receipt_uploaded');

        $receipt = PaymentReceipt::firstOrFail();
        $this->assertSame('pending', $receipt->status);
        $this->assertSame('image', $receipt->kind);
        $this->assertStringEndsWith('.webp', $receipt->path);
        Storage::disk('local')->assertExists($receipt->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($receipt->path)));
        $this->assertLessThanOrEqual(2000, getimagesizefromstring(Storage::disk('local')->get($receipt->path))[0]);

        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p->where('receipts.0.status', 'pending')->missing('receipts.0.path')->where('receiptUpload.remaining', 4));
    }

    public function test_bank_transfer_shows_the_payment_step_then_the_thank_you_page(): void
    {
        // Step 2: bank details + upload + "I've made the payment".
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p
            ->where('transferStep', route('orders.payment-sent', $this->order->token))
            ->where('paymentReported', false)
            ->where('bank.account_number', '0123456789'));

        $this->post("/order/{$this->order->token}/payment-sent")->assertRedirect($this->order->url());
        $this->assertNotNull($this->order->fresh()->payment_reported_at);

        // Step 3: thank you, being verified. Pressing again changes nothing.
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p->where('transferStep', null)->where('paymentReported', true));
        $at = $this->order->fresh()->payment_reported_at;
        $this->travel(5)->minutes();
        $this->post("/order/{$this->order->token}/payment-sent");
        $this->assertEquals($at, $this->order->fresh()->payment_reported_at);

        // The owner sees it as a payment to check, even without a receipt.
        $this->actingAsAdmin();
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.payments_to_check', 1));
        $this->get('/admin/orders?receipt=pending')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1)->where('orders.data.0.payment_to_check', true));
        $this->get("/admin/orders/{$this->order->id}")->assertInertia(fn (Assert $p) => $p->whereNot('paymentReportedAt', null));

        // Marked paid: no longer to check, and the customer page shows it as paid.
        $this->patch("/admin/orders/{$this->order->id}", ['payment_status' => 'paid']);
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.payments_to_check', 0));
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p->where('paymentReported', false)->where('transferStep', null));
    }

    public function test_only_unpaid_transfer_orders_can_report_payment(): void
    {
        $this->post('/order/'.$this->makeOrder(['payment_method' => 'paystack'])->token.'/payment-sent')->assertForbidden();
        $this->post('/order/'.$this->makeOrder(['payment_method' => 'delivery'])->token.'/payment-sent')->assertForbidden();
        $this->post('/order/'.$this->makeOrder(['payment_status' => 'paid'])->token.'/payment-sent')->assertForbidden();
        $this->post('/order/'.$this->makeOrder(['status' => 'cancelled'])->token.'/payment-sent')->assertForbidden();
        $this->get($this->makeOrder(['payment_method' => 'delivery'])->url())->assertInertia(fn (Assert $p) => $p->where('transferStep', null));
    }

    public function test_pdfs_are_accepted_but_scripted_or_fake_files_are_not(): void
    {
        $this->upload($this->pdf())->assertSessionHasNoErrors();
        $this->assertSame('pdf', PaymentReceipt::firstOrFail()->kind);

        $this->upload($this->pdf("1 0 obj << /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >> endobj\n"))->assertSessionHasErrors('receipt');
        $this->upload(UploadedFile::fake()->createWithContent('receipt.pdf', 'just some text'))->assertSessionHasErrors('receipt');
        $this->upload(UploadedFile::fake()->createWithContent('receipt.png', '<?php echo "hi";'))->assertSessionHasErrors('receipt');
        $this->upload(UploadedFile::fake()->create('receipt.exe', 10, 'application/octet-stream'))->assertSessionHasErrors('receipt');
        $this->upload(UploadedFile::fake()->image('huge.jpg')->size(9000))->assertSessionHasErrors('receipt');

        $this->assertSame(1, PaymentReceipt::count());
    }

    public function test_only_unpaid_transfer_orders_take_receipts_and_uploads_are_limited(): void
    {
        $this->upload(UploadedFile::fake()->image('r.jpg'), $this->makeOrder(['payment_method' => 'paystack']))->assertForbidden();
        $this->upload(UploadedFile::fake()->image('r.jpg'), $this->makeOrder(['payment_method' => 'delivery']))->assertForbidden();
        $this->get($this->makeOrder(['payment_method' => 'delivery'])->url())->assertInertia(fn (Assert $p) => $p->where('receiptUpload', null));
        $this->upload(UploadedFile::fake()->image('r.jpg'), $this->makeOrder(['payment_status' => 'paid']))->assertForbidden();
        $this->upload(UploadedFile::fake()->image('r.jpg'), $this->makeOrder(['status' => 'cancelled']))->assertForbidden();
        $this->post('/order/'.str_repeat('a', 40).'/receipt', ['receipt' => UploadedFile::fake()->image('r.jpg')])->assertNotFound();

        foreach (range(1, 5) as $i) {
            PaymentReceipt::create(['order_id' => $this->order->id, 'path' => "receipts/x{$i}.webp", 'kind' => 'image', 'size' => 1]);
        }
        $this->upload(UploadedFile::fake()->image('r.jpg'))->assertSessionHasErrors('receipt');
    }

    public function test_image_dimensions_are_bounded_before_storage(): void
    {
        $this->upload(UploadedFile::fake()->image('narrow.png', 12001, 1))->assertSessionHasErrors('receipt');
        $this->assertDatabaseCount('payment_receipts', 0);
        $this->assertSame([], Storage::disk(PaymentReceipt::DISK)->allFiles('receipts'));
    }

    public function test_pdf_is_delivered_as_an_isolated_download(): void
    {
        $this->upload($this->pdf())->assertSessionHasNoErrors();
        $receipt = PaymentReceipt::firstOrFail();
        $this->actingAsAdmin();
        $this->get("/admin/receipts/{$receipt->id}/file")->assertOk()->assertDownload()
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; frame-ancestors 'none'");
    }

    public function test_private_order_pages_exclude_pixels_and_custom_code(): void
    {
        BusinessSetting::current()->update(['ga4_id' => 'G-123456', 'head_code' => '<script>window.customPixel = true;</script>']);
        BusinessSetting::forgetResolved();
        $this->get($this->order->url())->assertOk()->assertDontSee('window.customPixel', false)
            ->assertDontSee('googletagmanager.com', false)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $p) => $p->where('tracking', null));
    }

    public function test_the_tokenless_thank_you_page_can_measure_purchases_without_leaking_the_link(): void
    {
        BusinessSetting::current()->update(['ga4_id' => 'G-123456']);
        BusinessSetting::forgetResolved();

        // The browser that placed the order: pixels allowed, no token in the address, private link offered.
        $this->withSession(['orders' => [$this->order->token]])->get('/order/thanks')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Storefront/OrderConfirmation')
                ->where('order.number', $this->order->number)
                ->where('tracking.ga4', 'G-123456')
                ->where('privateUrl', $this->order->url()));

        // Checkout pages carry no credential, so begin_checkout can be measured again.
        $this->assertFalse(\App\Support\Tracking::privatePage(\Illuminate\Http\Request::create('/checkout')));
        $this->assertTrue(\App\Support\Tracking::privatePage(\Illuminate\Http\Request::create('/order/'.$this->order->token)));

        // Another browser sees nothing; an old order hands over to the private link.
        $this->flushSession();
        $this->get('/order/thanks')->assertRedirect(route('home'));
        $this->order->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();
        $this->withSession(['orders' => [$this->order->token]])->get('/order/thanks')->assertRedirect($this->order->url());
    }

    public function test_only_admins_can_open_the_file(): void
    {
        $this->upload(UploadedFile::fake()->image('r.jpg'));
        $receipt = PaymentReceipt::firstOrFail();

        $this->get("/admin/receipts/{$receipt->id}/file")->assertRedirect('/admin/login');

        $this->actingAsAdmin();
        $this->get("/admin/receipts/{$receipt->id}/file")->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.payments_to_check', 1));
        $this->get('/admin/orders?receipt=pending')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1)->where('orders.data.0.payment_to_check', true));
        $this->get("/admin/orders/{$this->order->id}")->assertInertia(fn (Assert $p) => $p->where('receipts.0.status', 'pending'));
    }

    public function test_admin_confirms_the_payment(): void
    {
        $this->upload(UploadedFile::fake()->image('r.jpg'));
        $this->upload($this->pdf());
        [$pdf, $image] = PaymentReceipt::latest('id')->get()->all();
        $this->actingAsAdmin();

        $this->patch("/admin/receipts/{$image->id}", ['action' => 'approve'])->assertSessionHas('success');

        $this->order->refresh();
        $this->assertSame('paid', $this->order->payment_status);
        $this->assertNotNull($this->order->paid_at);
        $this->assertSame('approved', $image->fresh()->status);
        $this->assertSame('rejected', $pdf->fresh()->status); // the other one is settled
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.payments_to_check', 0));

        // Paid: no more uploads.
        $this->upload(UploadedFile::fake()->image('again.jpg'))->assertForbidden();
    }

    public function test_admin_rejects_with_a_reason_the_customer_sees(): void
    {
        $this->upload(UploadedFile::fake()->image('r.jpg'));
        $this->post("/order/{$this->order->token}/payment-sent");
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p->where('paymentReported', true)->where('transferStep', null));
        $receipt = PaymentReceipt::firstOrFail();
        $this->actingAsAdmin();

        $this->patch("/admin/receipts/{$receipt->id}", ['action' => 'reject'])->assertSessionHasErrors('note');
        $this->patch("/admin/receipts/{$receipt->id}", ['action' => 'reject', 'note' => 'No payment received yet'])->assertSessionHas('success');

        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        // Back on the payment page, with the reason, to upload a new receipt.
        $this->get($this->order->url())->assertInertia(fn (Assert $p) => $p
            ->where('transferStep', route('orders.payment-sent', $this->order->token))
            ->where('paymentReported', false)
            ->where('receipts.0.status', 'rejected')
            ->where('receipts.0.note', 'No payment received yet')
            ->where('receiptUpload.remaining', 4));
    }

    public function test_marking_paid_or_cancelled_elsewhere_settles_receipts_and_deleting_removes_files(): void
    {
        $this->upload(UploadedFile::fake()->image('r.jpg'));
        $this->actingAsAdmin();
        $this->patch("/admin/orders/{$this->order->id}", ['payment_status' => 'paid']);
        $this->assertSame('approved', PaymentReceipt::firstOrFail()->status);

        $other = $this->makeOrder();
        $this->upload(UploadedFile::fake()->image('r.jpg'), $other);
        $path = PaymentReceipt::latest('id')->value('path');
        $this->post('/admin/orders/bulk', ['action' => 'delete', 'ids' => [$other->id]]);
        $this->assertSame(0, PaymentReceipt::where('order_id', $other->id)->count());
        Storage::disk('local')->assertMissing($path);
    }
}
