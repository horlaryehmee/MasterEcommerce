<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\HomepageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromoBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function save(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(User::factory()->create())->put('/admin/content/promo', $data + [
            'promo_enabled' => true,
            'promo_heading' => 'Upgrade your home for less',
        ]);
    }

    public function test_sticker_end_date_and_colour_are_saved_in_the_store_timezone(): void
    {
        BusinessSetting::current()->update(['timezone' => 'Africa/Lagos']);
        BusinessSetting::forgetResolved();
        Carbon::setTestNow('2026-11-21 10:00:00');

        $this->save(['promo_badge' => 'Up to 30% off', 'promo_ends_at' => '2026-11-30T18:00', 'promo_bg_color' => '#e9dccd'])->assertSessionHasNoErrors();

        $home = HomepageSetting::current()->fresh();
        $this->assertSame('2026-11-30 17:00:00', $home->promo_ends_at->utc()->format('Y-m-d H:i:s'), '18:00 in Lagos is 17:00 UTC.');
        $this->assertSame('#E9DCCD', $home->promo_bg_color);
        HomepageSetting::forgetResolved();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('promo.badge', 'Up to 30% off')
            ->where('promo.bg', '#E9DCCD')
            ->where('promo.ends_at', '2026-11-30T17:00:00+00:00'));
        $this->actingAs(User::factory()->create())->get('/admin/content?tab=promo')
            ->assertInertia(fn (Assert $page) => $page->where('home.promo_ends_local', '2026-11-30T18:00'));
    }

    public function test_the_banner_hides_itself_once_the_offer_ends(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->save(['promo_ends_at' => '2026-11-22T10:00'])->assertSessionHasNoErrors();
        HomepageSetting::forgetResolved();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->whereNot('promo', null));

        Carbon::setTestNow('2026-11-23 10:00:00');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('promo', null));

        // No end date: always shown.
        $this->save(['promo_ends_at' => ''])->assertSessionHasNoErrors();
        HomepageSetting::forgetResolved();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('promo.ends_at', null));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->save(['promo_ends_at' => 'next friday', 'promo_bg_color' => 'beige', 'promo_badge' => str_repeat('x', 41)])
            ->assertSessionHasErrors(['promo_ends_at', 'promo_bg_color', 'promo_badge']);
    }
}
