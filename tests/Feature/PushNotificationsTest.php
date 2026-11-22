<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/device-abc';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->admin = User::factory()->create();
        BusinessSetting::current()->update(['shop_enabled' => true, 'pay_on_delivery_enabled' => true]);
        BusinessSetting::forgetResolved();
    }

    /** A real P-256 key pair, as a browser would make for its subscription. */
    protected function browserKeys(): array
    {
        $ec = openssl_pkey_get_details(WebPush::newKey())['ec'];

        return [
            'p256dh' => WebPush::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'auth' => WebPush::b64e(random_bytes(16)),
        ];
    }

    protected function subscribe(string $endpoint = self::ENDPOINT): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/admin/push-subscriptions', ['endpoint' => $endpoint, 'keys' => $this->browserKeys()]);
    }

    protected function placeOrder(): void
    {
        auth()->logout();
        $product = Product::factory()->create(['price' => 10000, 'name' => 'Blue Kettle']);
        $this->post('/cart', ['product_id' => $product->id]);
        $this->post('/checkout', [
            'customer_name' => 'Ada Obi', 'customer_phone' => '0801 234 5678', 'customer_email' => 'ada@example.com',
            'country' => 'NG', 'state' => 'LA', 'address' => '12 Allen Avenue', 'city' => 'Ikeja', 'payment_method' => 'delivery',
        ])->assertRedirect();
    }

    public function test_turning_on_saves_the_device_and_sends_a_signed_encrypted_test(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);

        $this->subscribe()->assertSessionHas('success');

        $sub = PushSubscription::firstOrFail();
        $this->assertSame($this->admin->id, $sub->user_id);
        $this->assertNotEmpty($sub->device);

        Http::assertSent(function (HttpRequest $r) {
            if ($r->url() !== self::ENDPOINT) {
                return false;
            }
            $this->assertSame('aes128gcm', $r->header('Content-Encoding')[0]);
            $this->assertSame('86400', $r->header('TTL')[0]);
            // Body: salt(16) + record size(4) + key length(1) + sender key(65) + ciphertext + tag(16).
            $this->assertSame(4096, unpack('N', substr($r->body(), 16, 4))[1]);
            $this->assertGreaterThan(102, strlen($r->body()));

            // VAPID: a JWT for this push service, signed with the site's key.
            preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $r->header('Authorization')[0], $m);
            $claims = json_decode(WebPush::b64d($m[2]), true);
            $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
            $this->assertGreaterThan(time(), $claims['exp']);
            $this->assertSame(app(WebPush::class)->publicKey(), $m[4]);
            $this->assertTrue($this->jwtSignatureValid("{$m[1]}.{$m[2]}", WebPush::b64d($m[3]), WebPush::b64d($m[4])));

            return true;
        });
    }

    public function test_only_real_push_services_can_be_registered(): void
    {
        Http::fake();
        $this->subscribe('https://192.168.1.10/internal')->assertSessionHasErrors('endpoint');
        $this->subscribe('http://fcm.googleapis.com/x')->assertSessionHasErrors('endpoint');
        $this->actingAs($this->admin)->post('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'bad', 'auth' => 'bad']])->assertSessionHasErrors(['keys.p256dh', 'keys.auth']);
        $this->assertSame(0, PushSubscription::count());
        Http::assertNothingSent();

        // Guests can't register devices.
        auth()->logout();
        $this->post('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => $this->browserKeys()])->assertRedirect('/admin/login');
    }

    public function test_a_new_order_notifies_every_admin_device(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), 'web.push.apple.com/*' => Http::response('', 201)]);
        $this->subscribe();
        $this->subscribe('https://web.push.apple.com/QGxIphone');
        Http::fake(['*' => Http::response('', 201)]); // reset the record of sent requests

        $this->placeOrder();

        $sent = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com') || str_contains($pair[0]->url(), 'web.push.apple.com'));
        $this->assertCount(2, $sent);
    }

    public function test_unpaid_online_orders_do_not_notify_until_paid(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $this->subscribe();
        BusinessSetting::current()->update(['pay_paystack_enabled' => true, 'paystack_test_secret_key' => 'sk_'.'test_abcdefghijklmnopqrstuvwxyz0123456789']);
        BusinessSetting::forgetResolved();

        $pushes = 0;
        Http::fake(function (HttpRequest $r) use (&$pushes) {
            if (str_contains($r->url(), 'fcm.googleapis.com')) {
                $pushes++;

                return Http::response('', 201);
            }
            if (str_ends_with($r->url(), '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]);
            }
            $payment = \App\Models\Payment::first();

            return Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount, 'currency' => 'NGN', 'domain' => 'test']]);
        });

        auth()->logout();
        $product = Product::factory()->create(['price' => 10000]);
        $this->post('/cart', ['product_id' => $product->id]);
        $this->post('/checkout', [
            'customer_name' => 'Ada Obi', 'customer_phone' => '0801 234 5678', 'customer_email' => 'ada@example.com',
            'country' => 'NG', 'state' => 'LA', 'address' => '12 Allen Avenue', 'city' => 'Ikeja', 'payment_method' => 'paystack',
        ]);
        $this->assertSame(0, $pushes);

        $this->get(route('payments.paystack.callback', ['reference' => \App\Models\Payment::first()->reference]));
        $this->assertSame(1, $pushes);
    }

    public function test_devices_that_unsubscribed_are_removed(): void
    {
        $status = 201;
        Http::fake(function () use (&$status) {
            return Http::response('', $status);
        });
        $this->subscribe();
        $this->assertSame(1, PushSubscription::count());

        // The phone's browser data was cleared: the push service answers 410 Gone.
        $status = 410;
        $this->placeOrder();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_profile_lists_devices_without_secrets_and_can_remove_them(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $this->subscribe();
        $sub = PushSubscription::firstOrFail();

        $response = $this->get('/admin/profile')->assertInertia(fn (Assert $p) => $p
            ->has('push.devices', 1)
            ->where('push.publicKey', app(WebPush::class)->publicKey())
            ->missing('push.devices.0.endpoint')
            ->missing('push.devices.0.auth_token'));
        $this->assertStringNotContainsString('BEGIN EC PRIVATE KEY', $response->getContent());
        $this->assertStringNotContainsString($sub->auth_token, $response->getContent());

        $this->delete('/admin/push-subscriptions', ['id' => $sub->id])->assertSessionHas('success');
        // Switched off (kept, so the device doesn't quietly turn itself back on).
        $this->assertSame(0, PushSubscription::query()->active()->count());
    }

    public function test_service_worker_shows_notifications_and_opens_only_this_site(): void
    {
        $js = $this->get('/sw.js')->getContent();
        $this->assertStringContainsString("self.addEventListener('push'", $js);
        $this->assertStringContainsString('setAppBadge', $js);
        $this->assertStringContainsString('if (target.origin !== self.location.origin) return;', $js);
    }

    /** Verifies an ES256 JWT signature (raw r||s) with a raw P-256 public point. */
    protected function jwtSignatureValid(string $signed, string $raw, string $point): bool
    {
        $int = fn ($b) => ($b = ltrim($b, "\0")) === '' ? "\0" : (ord($b[0]) & 0x80 ? "\0".$b : $b);
        [$r, $s] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
        $seq = "\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $der = "\x30".chr(strlen($seq)).$seq;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point), 64, "\n")."-----END PUBLIC KEY-----\n";

        return openssl_verify($signed, $der, $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    public function test_a_device_that_already_allows_notifications_turns_on_silently(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $keys = $this->browserKeys();

        $this->actingAs($this->admin)
            ->postJson('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'silent' => true])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, PushSubscription::query()->active()->count());
        // Silent: no test notification.
        Http::assertNothingSent();

        // Every admin page knows this device is on (so it won't prompt).
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p
            ->where('admin.push.on', [hash('sha256', self::ENDPOINT)])
            ->where('admin.push.publicKey', app(WebPush::class)->publicKey()));
    }

    public function test_a_device_switched_off_from_the_list_stays_off_until_turned_on_on_it(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $keys = $this->browserKeys();
        $this->actingAs($this->admin)->post('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => $keys]);
        $sub = PushSubscription::firstOrFail();

        $this->delete('/admin/push-subscriptions', ['id' => $sub->id]);
        $this->assertNotNull($sub->fresh()->disabled_at);
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.push.on', [])->where('admin.push.off', [hash('sha256', self::ENDPOINT)]));
        $this->get('/admin/profile')->assertInertia(fn (Assert $p) => $p->has('push.devices', 0));

        // The device tries to turn itself back on quietly: refused.
        $this->postJson('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => $keys, 'silent' => true])->assertJson(['ok' => false, 'disabled' => true]);
        $this->assertNotNull($sub->fresh()->disabled_at);

        // Switched-off devices get nothing.
        Http::fake(['*' => Http::response('', 201)]);
        $this->placeOrder();
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com'));

        // Tapping "Turn on" on that device turns it back on.
        $this->actingAs($this->admin)->post('/admin/push-subscriptions', ['endpoint' => self::ENDPOINT, 'keys' => $keys])->assertSessionHas('success');
        $this->assertNull($sub->fresh()->disabled_at);
    }
}
