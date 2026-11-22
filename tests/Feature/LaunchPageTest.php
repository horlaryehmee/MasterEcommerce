<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Support\LaunchPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaunchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function turnOn(array $values = []): BusinessSetting
    {
        $s = BusinessSetting::current();
        $s->update($values + ['launch_enabled' => true, 'launch_mode' => 'coming_soon', 'launch_at' => now()->addDays(3), 'launch_token' => str_repeat('k', 32)]);
        BusinessSetting::forgetResolved();

        return $s->fresh();
    }

    public function test_the_shop_is_open_until_the_page_is_switched_on(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Storefront/Home')->where('launch', null));
    }

    public function test_visitors_see_the_page_and_admins_see_the_shop(): void
    {
        $this->turnOn(['launch_heading' => 'Nexa opens soon', 'launch_message' => 'Big things coming.']);

        $this->get('/')->assertStatus(503)
            ->assertSee('Nexa opens soon')->assertSee('Big things coming.')
            ->assertSee('id="countdown"', false)
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertHeader('Retry-After');
        $this->get('/products')->assertStatus(503);
        $this->get('/cart')->assertStatus(503);

        // An open page navigating inside the app reloads fully, so it gets the page too.
        $this->withHeaders(['X-Inertia' => 'true'])->get('/products')->assertStatus(409)->assertHeader('X-Inertia-Location');

        $this->actingAsAdmin();
        $this->flushHeaders()->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->where('launch.mode', 'coming_soon'));
    }

    public function test_orders_payments_admin_and_background_files_keep_working(): void
    {
        $this->turnOn();
        $order = Order::create(['payment_method' => 'delivery', 'customer_name' => 'Ada', 'customer_phone' => '0801 234 5678', 'shipping_country' => 'NG', 'subtotal' => 1000, 'total' => 1000, 'currency_code' => 'NGN']);

        $this->get($order->url())->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/sw.js')->assertOk();
        $this->get('/robots.txt')->assertOk();
        $this->get('/payments/paystack/callback?reference=nope')->assertNotFound(); // reaches the controller
    }

    public function test_the_secret_preview_link_lets_someone_see_the_shop(): void
    {
        $this->turnOn();

        $this->get('/?preview=wrong-token-wrong-token-wrong')->assertStatus(503);
        $response = $this->get('/products?preview='.str_repeat('k', 32));
        $response->assertRedirect('/products')->assertCookie(LaunchPage::PREVIEW_COOKIE);

        $this->withCookie(LaunchPage::PREVIEW_COOKIE, str_repeat('k', 32))->get('/products')->assertOk();
        $this->withCookie(LaunchPage::PREVIEW_COOKIE, 'old-link-old-link-old-link')->get('/products')->assertStatus(503);
    }

    public function test_the_shop_opens_by_itself_when_the_countdown_ends(): void
    {
        $this->turnOn(['launch_at' => now()->subMinute(), 'launch_auto_open' => true]);
        $this->get('/')->assertOk();

        // Without auto-open the page stays (and no countdown is shown once the time has passed).
        $this->turnOn(['launch_at' => now()->subMinute(), 'launch_auto_open' => false]);
        $this->get('/')->assertStatus(503)->assertDontSee('id="countdown"', false);
    }

    public function test_maintenance_wording_and_the_admin_preview(): void
    {
        $this->turnOn(['launch_mode' => 'maintenance', 'launch_heading' => null, 'launch_at' => null]);
        $this->get('/')->assertStatus(503)->assertSee('Down for maintenance')->assertSee('back soon', false)->assertDontSee('id="countdown"', false);

        // The admin can preview the page even while it's off; visitors can't trigger it.
        BusinessSetting::current()->update(['launch_enabled' => false]);
        BusinessSetting::forgetResolved();
        $this->get('/?launch_preview=1')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Storefront/Home'));
        $this->actingAsAdmin();
        $this->get('/?launch_preview=1')->assertOk()->assertSee('Down for maintenance');
    }

    public function test_settings_save_the_launch_time_in_the_shops_time_zone(): void
    {
        $this->actingAsAdmin();
        BusinessSetting::current()->update(['timezone' => 'Africa/Lagos']); // UTC+1
        BusinessSetting::forgetResolved();

        $this->put('/admin/settings/launch', [
            'launch_enabled' => '1', 'launch_mode' => 'coming_soon', 'launch_heading' => 'Soon',
            'launch_at_local' => '2030-12-01T09:00', 'launch_countdown' => '1', 'launch_auto_open' => '1', 'launch_show_contact' => '0',
        ])->assertRedirect('/admin/settings?tab=launch')->assertSessionHas('success');

        $s = BusinessSetting::query()->first();
        $this->assertTrue($s->launch_enabled);
        $this->assertSame('2030-12-01 08:00:00', $s->launch_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(32, strlen($s->launch_token), 'A secret preview link is created when switched on.');

        $this->get('/admin/settings?tab=launch')->assertInertia(fn (Assert $p) => $p->where('launch.launch_at_local', '2030-12-01T09:00')->where('launch.active', true));

        $old = $s->launch_token;
        $this->post('/admin/settings/launch/link')->assertSessionHas('success');
        $this->assertNotSame($old, $s->fresh()->launch_token);

        $this->put('/admin/settings/launch', ['launch_mode' => 'teaser'])->assertSessionHasErrors('launch_mode');
    }
}
