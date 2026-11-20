<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Shipping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(array $attrs = []): BusinessSetting
    {
        $s = BusinessSetting::current();
        $s->update($attrs + ['shop_enabled' => true]);
        BusinessSetting::forgetResolved();

        return $s;
    }

    protected function zone(string $name, array $locations, array $methods = [], int $order = 0): ShippingZone
    {
        $zone = ShippingZone::query()->create(['name' => $name, 'sort_order' => $order]);
        foreach ($locations as $code) {
            $type = str_starts_with($code, 'pc:') ? 'postcode' : (str_contains($code, ':') ? 'state' : 'country');
            $zone->locations()->create(['type' => $type, 'code' => $type === 'postcode' ? substr($code, 3) : $code]);
        }
        foreach ($methods as $i => [$type, $title, $settings]) {
            $zone->methods()->create(['type' => $type, 'title' => $title, 'settings' => $settings, 'sort_order' => $i, 'is_enabled' => $settings['enabled'] ?? true]);
        }

        return $zone;
    }

    protected function shipping(): Shipping
    {
        return new Shipping;
    }

    public function test_zones_match_top_to_bottom_with_the_fallback_last(): void
    {
        $this->settings(['ship_to' => 'all']);
        $this->zone('Lagos', ['NG:LA'], [['flat_rate', 'Lagos delivery', ['cost' => 2500]]], 0);
        $this->zone('Nigeria', ['NG'], [['flat_rate', 'Nationwide', ['cost' => 6000]]], 1);
        $this->zone('West Africa', ['GH', 'TG'], [['flat_rate', 'Regional', ['cost' => 20000]]], 2);
        ShippingZone::fallback()->methods()->create(['type' => 'flat_rate', 'title' => 'International', 'settings' => ['cost' => 50000]]);

        $s = $this->shipping();
        $this->assertSame('Lagos', $s->matchZone('NG', 'LA')->name);
        $this->assertSame('Nigeria', $s->matchZone('NG', 'FC')->name);
        $this->assertSame('Nigeria', $s->matchZone('NG')->name);
        $this->assertSame('West Africa', $s->matchZone('GH', 'AA')->name);
        $this->assertTrue($s->matchZone('US', 'CA')->is_fallback);

        $this->assertSame(['International'], $s->rates('US', null, null, 1000, 1)->pluck('title')->all());
        $this->assertSame([2500.0], $s->rates('NG', 'LA', null, 1000, 1)->pluck('cost')->all());
    }

    public function test_postcode_rules_follow_woocommerce(): void
    {
        $this->assertTrue(Shipping::postcodeMatches('100271', ['100271']));
        $this->assertTrue(Shipping::postcodeMatches('100 271', ['1002*']));
        $this->assertTrue(Shipping::postcodeMatches('sw1a 1aa', ['SW1A*']));
        $this->assertTrue(Shipping::postcodeMatches('90500', ['90210...99000']));
        $this->assertFalse(Shipping::postcodeMatches('89999', ['90210...99000']));
        $this->assertFalse(Shipping::postcodeMatches('', ['1*']));

        $this->settings();
        $this->zone('Ikeja only', ['NG:LA', 'pc:1002*'], [['flat_rate', 'Same day', ['cost' => 1500]]], 0);
        $this->zone('Lagos', ['NG:LA'], [['flat_rate', 'Standard', ['cost' => 3000]]], 1);

        $this->assertSame('Ikeja only', $this->shipping()->matchZone('NG', 'LA', '100271')->name);
        $this->assertSame('Lagos', $this->shipping()->matchZone('NG', 'LA', '101233')->name);
        $this->assertSame('Lagos', $this->shipping()->matchZone('NG', 'LA', null)->name);
    }

    public function test_selling_locations_limit_the_countries(): void
    {
        $this->settings(['ship_to' => 'specific', 'ship_to_countries' => ['NG', 'GH'], 'default_country' => 'GH']);
        $this->assertSame(['GH', 'NG'], array_keys($this->shipping()->allowedCountries()));
        $this->assertSame('GH', $this->shipping()->defaultCountry());

        $this->settings(['ship_to' => 'all_except', 'ship_except_countries' => ['US']]);
        $allowed = $this->shipping()->allowedCountries();
        $this->assertArrayNotHasKey('US', $allowed);
        $this->assertArrayHasKey('GB', $allowed);

        $this->settings(['ship_to' => 'all']);
        $this->assertCount(count(config('countries')), $this->shipping()->allowedCountries());

        // A store country outside the allowed list falls back to the first allowed one.
        $this->settings(['ship_to' => 'specific', 'ship_to_countries' => ['KE'], 'default_country' => 'NG']);
        $this->assertSame('KE', $this->shipping()->defaultCountry());

        // Rates are never offered outside the selling locations, even for a matching zone.
        $this->zone('Nigeria', ['NG'], [['flat_rate', 'Nationwide', ['cost' => 1]]]);
        $this->assertCount(0, $this->shipping()->rates('NG', null, null, 100, 1));
    }

    public function test_method_costs_and_availability(): void
    {
        $this->settings();
        $zone = $this->zone('Nigeria', ['NG'], [
            ['flat_rate', 'Per item', ['cost' => 1000, 'per_item' => 250]],
            ['free_shipping', 'Free', ['requires' => 'none']],
            ['local_pickup', 'Pickup', ['cost' => 300, 'address' => 'Main Market']],
            ['flat_rate', 'Hidden', ['cost' => 1, 'enabled' => false]],
        ]);

        $rates = $this->shipping()->rates('NG', 'LA', null, 5000, 3)->keyBy('title');
        $this->assertSame(['Per item', 'Free', 'Pickup'], $rates->keys()->all());
        $this->assertSame(1750.0, $rates['Per item']['cost']);
        $this->assertSame('Free', $rates['Free']['cost_label']);
        $this->assertSame('Main Market', $rates['Pickup']['address']);
        $this->assertSame('Nigeria', $rates['Pickup']['zone']);
        $this->assertSame('Flat rate · ₦1,000 + ₦250/item', $zone->methods()->first()->summary());
    }

    public function test_checkout_rates_endpoint_and_shared_settings(): void
    {
        $this->settings(['ship_to' => 'specific', 'ship_to_countries' => ['NG', 'GH']]);
        $this->zone('Ghana', ['GH'], [['flat_rate', 'Ghana delivery', ['cost' => 25000]]]);
        $product = Product::factory()->create(['price' => 10000]);
        $this->post('/cart', ['product_id' => $product->id]);

        $this->getJson('/checkout/rates?country=GH&state=AA')->assertOk()->assertJsonPath('rates.0.title', 'Ghana delivery')->assertJsonPath('rates.0.cost_label', '₦25,000')->assertJsonMissingPath('rates.0.method');
        $this->getJson('/checkout/rates?country=NG')->assertOk()->assertJsonCount(0, 'rates');
        $this->getJson('/checkout/rates?country=US')->assertOk()->assertJsonCount(0, 'rates');
        $this->getJson('/checkout/rates')->assertStatus(422);

        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page
            ->where('countries', [['code' => 'GH', 'name' => 'Ghana'], ['code' => 'NG', 'name' => 'Nigeria']])
            ->has('states.GH', 16)
            ->has('states.NG', 37));
    }

    public function test_admin_manages_shipping_locations_and_zones(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/settings?tab=shipping')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('shippingZones', 1) // the fallback zone
            ->where('shippingZones.0.is_fallback', true)
            ->has('countries', count(config('countries'))));

        $this->put('/admin/settings/shipping', ['default_country' => 'GH', 'ship_to' => 'specific', 'ship_to_countries' => ['GH', 'NG', 'NG']])->assertRedirect('/admin/settings?tab=shipping');
        $s = BusinessSetting::query()->first();
        $this->assertSame(['GH', 'NG'], $s->ship_to_countries);
        $this->assertSame('GH', $s->default_country);
        $this->put('/admin/settings/shipping', ['default_country' => 'ZZ', 'ship_to' => 'specific', 'ship_to_countries' => []])->assertSessionHasErrors(['default_country', 'ship_to_countries']);

        // Create a zone with regions, postcodes and all three methods.
        $this->get('/admin/shipping-zones/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipping/ZoneForm')
            ->where('regionOptions', fn ($options) => collect($options)->contains('value', 'NG:LA') && collect($options)->contains('value', 'FR')));

        $this->post('/admin/shipping-zones', [
            'name' => 'Lagos',
            'regions' => ['NG:LA'],
            'postcodes' => "1002*\n100001...100099",
            'methods' => [
                ['type' => 'flat_rate', 'title' => 'Standard', 'is_enabled' => true, 'cost' => '2500', 'per_item' => ''],
                ['type' => 'free_shipping', 'title' => 'Free', 'is_enabled' => true, 'requires' => 'min_amount', 'min_amount' => '100000'],
                ['type' => 'local_pickup', 'title' => 'Pickup', 'is_enabled' => false, 'cost' => '0', 'address' => 'Ikeja City Mall'],
            ],
        ])->assertSessionHasNoErrors();

        $zone = ShippingZone::query()->where('name', 'Lagos')->with(['locations', 'methods'])->sole();
        $this->assertSame(['NG:LA'], $zone->regionCodes());
        $this->assertSame(['1002*', '100001...100099'], $zone->postcodes());
        $this->assertSame(['flat_rate', 'free_shipping', 'local_pickup'], $zone->methods->pluck('type')->all());
        $this->assertEquals(2500, $zone->methods[0]->setting('cost'));
        $this->assertFalse($zone->methods[2]->is_enabled);

        // Update: rename, change regions, keep one method, add another.
        $keep = $zone->methods[0];
        $this->put("/admin/shipping-zones/{$zone->id}", [
            'name' => 'Lagos & Ogun',
            'regions' => ['NG:LA', 'NG:OG'],
            'postcodes' => '',
            'methods' => [
                ['id' => $keep->id, 'type' => 'flat_rate', 'title' => 'Standard', 'is_enabled' => true, 'cost' => '3000'],
                ['type' => 'flat_rate', 'title' => 'Express', 'is_enabled' => true, 'cost' => '6000'],
            ],
        ])->assertRedirect("/admin/shipping-zones/{$zone->id}/edit");

        $zone->refresh()->load(['locations', 'methods']);
        $this->assertSame('Lagos & Ogun', $zone->name);
        $this->assertSame(['NG:LA', 'NG:OG'], $zone->regionCodes());
        $this->assertSame([], $zone->postcodes());
        $this->assertSame(['Standard', 'Express'], $zone->methods->pluck('title')->all());
        $this->assertSame($keep->id, $zone->methods[0]->id);
        // Methods left out of the save are deleted.
        $this->assertSame(0, ShippingMethod::query()->whereIn('title', ['Free', 'Pickup'])->count());

        // Validation.
        $this->put("/admin/shipping-zones/{$zone->id}", ['name' => '', 'regions' => ['NG:XX'], 'methods' => [['type' => 'teleport', 'title' => '']]])
            ->assertSessionHasErrors(['name', 'regions.0', 'methods.0.type', 'methods.0.title']);
        $this->put("/admin/shipping-zones/{$zone->id}", ['name' => 'Lagos', 'methods' => [['type' => 'free_shipping', 'title' => 'Free', 'requires' => 'min_amount', 'min_amount' => '']]])
            ->assertSessionHasErrors('methods.0.min_amount');

        // Reorder and delete; the fallback zone can't be deleted.
        $other = ShippingZone::query()->create(['name' => 'Abuja', 'sort_order' => 5]);
        $this->post('/admin/shipping-zones/reorder', ['ids' => [$other->id, $zone->id]]);
        $this->assertSame(['Abuja', 'Lagos & Ogun'], ShippingZone::query()->where('is_fallback', false)->ordered()->pluck('name')->all());

        $this->delete("/admin/shipping-zones/{$other->id}")->assertRedirect('/admin/settings?tab=shipping');
        $this->assertNull($other->fresh());
        $this->delete('/admin/shipping-zones/'.ShippingZone::fallback()->id)->assertForbidden();

        // The fallback zone keeps its name and has no regions, but takes methods.
        $fallback = ShippingZone::fallback();
        $this->put("/admin/shipping-zones/{$fallback->id}", ['name' => 'Hacked', 'regions' => ['NG'], 'methods' => [['type' => 'flat_rate', 'title' => 'Worldwide', 'cost' => '90000']]])->assertSessionHasNoErrors();
        $fallback->refresh()->load('locations');
        $this->assertSame(ShippingZone::FALLBACK_NAME, $fallback->name);
        $this->assertCount(0, $fallback->locations);
        $this->assertSame(['Worldwide'], $fallback->methods()->pluck('title')->all());
    }
}
