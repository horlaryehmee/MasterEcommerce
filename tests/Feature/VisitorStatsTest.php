<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Services\Stats\Channel;
use App\Services\Stats\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitorStatsTest extends TestCase
{
    use RefreshDatabase;

    protected const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    protected const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    protected function hit(array $data, string $ua = self::CHROME, array $headers = [])
    {
        return $this->withHeaders(['User-Agent' => $ua, ...$headers])->postJson('/hello', $data);
    }

    protected function pageview(string $vid, string $sid, string $path = '/', array $extra = [], string $ua = self::CHROME, array $headers = []): string
    {
        $pid = (string) Str::uuid();
        $this->hit(['t' => 'pageview', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'path' => $path, 'title' => 'Page | Store', ...$extra], $ua, $headers)->assertNoContent();

        return $pid;
    }

    public function test_private_order_paths_and_referrers_are_redacted_before_storage(): void
    {
        $private = '/order/'.Str::random(40);
        $this->pageview((string) Str::uuid(), (string) Str::uuid(), $private);
        $this->pageview((string) Str::uuid(), (string) Str::uuid(), '/products', ['ref' => 'https://shop.test'.$private]);
        $this->assertDatabaseHas('stats_pageviews', ['path' => '/private']);
        $this->assertDatabaseHas('stats_sessions', ['referrer_url' => '/private']);
        $this->assertDatabaseMissing('stats_sessions', ['entry_path' => $private]);
    }

    public function test_historical_link_review_and_targeted_rotation(): void
    {
        $order = Order::create(['customer_name' => 'Customer', 'customer_phone' => '08012345678', 'total' => 100, 'subtotal' => 100, 'currency_code' => 'NGN', 'payment_method' => 'delivery']);
        $private = '/order/'.$order->token;
        $this->pageview((string) Str::uuid(), (string) Str::uuid(), '/products');
        DB::table('stats_pageviews')->update(['path' => $private]);
        $this->artisan('security:review-analytics-links')->assertSuccessful();
        $this->assertDatabaseHas('stats_pageviews', ['path' => $private]);
        $this->artisan('security:review-analytics-links', ['--redact' => true])->assertSuccessful();
        $this->assertDatabaseMissing('stats_pageviews', ['path' => $private]);
        $this->artisan('security:rotate-order-link', ['number' => $order->number])->assertSuccessful();
        $this->assertNotSame($order->token, $order->fresh()->token);
    }

    public function test_page_views_create_visitors_and_sessions(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();

        $pid = $this->pageview($vid, $sid, '/', ['ref' => 'https://www.google.com/', 'w' => 390], self::IPHONE, ['CF-IPCountry' => 'NG']);
        $this->pageview($vid, $sid, '/products');
        // A retried beacon (same page-view ID) is not counted twice.
        $this->hit(['t' => 'pageview', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'path' => '/'], self::IPHONE)->assertNoContent();

        $visitor = DB::table('stats_visitors')->sole();
        $session = DB::table('stats_sessions')->sole();
        $this->assertSame(2, DB::table('stats_pageviews')->count());
        $this->assertSame([2, 1, 'NG'], [(int) $visitor->pageviews_count, (int) $visitor->sessions_count, $visitor->country_code]);
        $this->assertSame(['Organic Search', 'Google', 'google.com', '/', '/products', 2, 'mobile', 'Mobile Safari', 'iOS'], [
            $session->channel, $session->source, $session->referrer_host, $session->entry_path, $session->exit_path, (int) $session->pageviews, $session->device, $session->browser, $session->os,
        ]);
        $this->assertTrue((bool) $session->is_new_visitor);

        // A new session for the same visitor is a returning visit.
        $this->pageview($vid, (string) Str::uuid(), '/about');
        $this->assertSame(2, (int) DB::table('stats_visitors')->value('sessions_count'));
        $this->assertFalse((bool) DB::table('stats_sessions')->latest('id')->value('is_new_visitor'));
    }

    public function test_bots_automation_admin_and_bad_input_are_not_counted(): void
    {
        $data = fn () => ['t' => 'pageview', 'vid' => (string) Str::uuid(), 'sid' => (string) Str::uuid(), 'pid' => (string) Str::uuid(), 'path' => '/'];

        $this->hit($data(), 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertNoContent();
        $this->hit($data(), 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/124.0.0.0 Safari/537.36')->assertNoContent();
        $this->hit($data(), 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')->assertNoContent();
        $this->hit($data(), '')->assertNoContent();
        $this->hit([...$data(), 'path' => '/admin/orders'])->assertNoContent();
        $this->hit([...$data(), 'vid' => 'not-a-uuid'])->assertNoContent();
        $this->hit([...$data(), 'path' => 'https://evil.example'])->assertNoContent();

        // The logged-in owner isn't counted unless "Track my own visits" is on.
        $this->actingAsAdmin();
        $this->hit($data())->assertNoContent();
        $this->assertSame(0, DB::table('stats_pageviews')->count());

        BusinessSetting::current()->update(['track_admins' => true]);
        BusinessSetting::forgetResolved();
        $this->hit($data())->assertNoContent();
        $this->assertSame(1, DB::table('stats_pageviews')->count());

        // Switched off entirely.
        BusinessSetting::current()->update(['stats_enabled' => false]);
        BusinessSetting::forgetResolved();
        $this->hit($data())->assertNoContent();
        $this->assertSame(1, DB::table('stats_pageviews')->count());
    }

    public function test_engagement_time_is_cumulative_and_capped(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();
        $pid = $this->pageview($vid, $sid);

        $this->hit(['t' => 'engage', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'engaged' => 12]);
        $this->hit(['t' => 'engage', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'engaged' => 30]);
        $this->hit(['t' => 'engage', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'engaged' => 20]); // late, smaller: ignored
        $this->assertSame([30, 30], [(int) DB::table('stats_pageviews')->value('engaged_seconds'), (int) DB::table('stats_sessions')->value('engaged_seconds')]);

        $this->hit(['t' => 'engage', 'vid' => $vid, 'sid' => $sid, 'pid' => $pid, 'engaged' => 999999]);
        $this->assertSame(1800, (int) DB::table('stats_pageviews')->value('engaged_seconds'));
    }

    public function test_events_record_conversions_even_if_they_arrive_first(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();

        // Event before its page view (beacons can arrive out of order).
        $this->hit(['t' => 'event', 'vid' => $vid, 'sid' => $sid, 'path' => '/order/x', 'name' => 'purchase', 'label' => '#1001', 'value' => 23000, 'utm' => ['source' => 'newsletter', 'medium' => 'email']]);
        $this->pageview($vid, $sid, '/order/x');
        $this->hit(['t' => 'event', 'vid' => $vid, 'sid' => $sid, 'path' => '/', 'name' => 'outbound_click', 'label' => 'WhatsApp']);
        $this->hit(['t' => 'event', 'vid' => $vid, 'sid' => $sid, 'path' => '/', 'name' => 'hack']);

        $session = DB::table('stats_sessions')->sole();
        $this->assertTrue((bool) $session->has_conversion);
        $this->assertSame(['Email', 'newsletter'], [$session->channel, $session->source]);
        $this->assertSame(['purchase', 'outbound_click'], DB::table('stats_events')->orderBy('id')->pluck('name')->all());
        $this->assertEquals(23000, DB::table('stats_events')->where('name', 'purchase')->value('value'));
    }

    public function test_orders_are_linked_to_the_visitor_cookie(): void
    {
        BusinessSetting::current()->update(['shop_enabled' => true]);
        BusinessSetting::forgetResolved();
        $vid = (string) Str::uuid();
        $this->pageview($vid, (string) Str::uuid(), '/checkout');
        $product = \App\Models\Product::factory()->create(['price' => 5000]);

        $this->withUnencryptedCookie('nx_vid', $vid)->post('/cart', ['product_id' => $product->id]);
        $this->withUnencryptedCookie('nx_vid', $vid)->post('/checkout', [
            'customer_name' => 'Ada', 'customer_phone' => '08012345678', 'country' => 'NG', 'state' => 'LA', 'city' => 'Ikeja', 'address' => '1 Road', 'payment_method' => 'delivery',
        ])->assertSessionHasNoErrors();

        $this->assertSame(DB::table('stats_visitors')->value('id'), Order::query()->sole()->stats_visitor_id);
    }

    public function test_channel_grouping_follows_google_analytics(): void
    {
        $site = 'shop.example.com';
        $c = fn ($ref, $utm = []) => Channel::classify($ref, $utm, $site)['channel'];

        $this->assertSame('Direct', $c(null));
        $this->assertSame('Direct', $c('https://shop.example.com/products'));
        $this->assertSame('Organic Search', $c('https://www.google.com.ng/'));
        $this->assertSame('Organic Search', $c('https://duckduckgo.com/'));
        $this->assertSame('Organic Social', $c('https://l.facebook.com/l.php?u=x'));
        $this->assertSame('Organic Social', $c('https://t.co/abc'));
        $this->assertSame('Organic Social', $c(null, ['source' => 'instagram']));
        $this->assertSame('Paid Search', $c('https://www.google.com/', ['source' => 'google', 'medium' => 'cpc']));
        $this->assertSame('Paid Social', $c(null, ['source' => 'facebook', 'medium' => 'paid']));
        $this->assertSame('Email', $c(null, ['source' => 'mailchimp', 'medium' => 'email']));
        $this->assertSame('Referral', $c('https://blog.partner.ng/review'));
        $this->assertSame('Instagram', Channel::classify('https://www.instagram.com/', [], $site)['source']);
        $this->assertSame('summer-sale', Channel::classify(null, ['source' => 'ig', 'campaign' => 'summer-sale'], $site)['campaign']);
    }

    public function test_days_follow_the_store_timezone(): void
    {
        BusinessSetting::current()->update(['timezone' => 'Africa/Lagos']);
        BusinessSetting::forgetResolved();

        // 23:30 UTC is already the next day in Lagos (UTC+1).
        Carbon::setTestNow('2026-03-10 23:30:00');
        $this->pageview((string) Str::uuid(), (string) Str::uuid());
        $this->assertSame(['2026-03-11', 0], [substr((string) DB::table('stats_pageviews')->value('local_date'), 0, 10), (int) DB::table('stats_pageviews')->value('local_hour')]);
        Carbon::setTestNow();
    }

    public function test_report_figures_use_google_analytics_definitions(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $c = (string) Str::uuid();

        // Visitor A: 2 pages (engaged). Visitor B: 1 page, 3 seconds (bounce). Visitor C: 1 page, 40 seconds (engaged), from Ghana.
        $sa = (string) Str::uuid();
        $this->pageview($a, $sa, '/', ['ref' => 'https://google.com'], self::CHROME, ['CF-IPCountry' => 'NG']);
        $this->pageview($a, $sa, '/products', [], self::CHROME, ['CF-IPCountry' => 'NG']);
        $sb = (string) Str::uuid();
        $pb = $this->pageview($b, $sb, '/', [], self::IPHONE, ['CF-IPCountry' => 'NG']);
        $this->hit(['t' => 'engage', 'vid' => $b, 'sid' => $sb, 'pid' => $pb, 'engaged' => 3]);
        $sc = (string) Str::uuid();
        $pc = $this->pageview($c, $sc, '/products/lamp', ['ref' => 'https://www.instagram.com/'], self::IPHONE, ['CF-IPCountry' => 'GH']);
        $this->hit(['t' => 'engage', 'vid' => $c, 'sid' => $sc, 'pid' => $pc, 'engaged' => 40]);
        $this->hit(['t' => 'event', 'vid' => $c, 'sid' => $sc, 'path' => '/products/lamp', 'name' => 'view_item', 'label' => 'Lamp', 'value' => 9000]);

        $r = new Report('today');
        $t = $r->totals();
        $this->assertSame(3, $t['visitors']);
        $this->assertSame(3, $t['sessions']);
        $this->assertSame(4, $t['pageviews']);
        $this->assertSame(33.3, $t['bounce_rate']);          // 1 of 3 visits not engaged
        $this->assertSame(14, $t['avg_engagement_seconds']); // (0 + 3 + 40) / 3 visitors
        $this->assertSame(1.33, $t['views_per_session']);

        $countries = collect($r->countries())->pluck('visitors', 'code')->all();
        $this->assertSame(['NG' => 2, 'GH' => 1], $countries);
        $this->assertEqualsCanonicalizing(['Direct', 'Organic Search', 'Organic Social'], collect($r->bySession('channel'))->pluck('name')->all());
        $this->assertSame('/products', collect($r->pages())->firstWhere('path', '/products')['path']);
        $this->assertSame(1, collect($r->funnel())->firstWhere('key', 'view_item')['sessions']);

        // Hourly series for a single day, with unique visitors per hour.
        $series = $r->series();
        $this->assertSame('hour', $series['unit']);
        $noon = collect($series['points'])->firstWhere('key', '13:00'); // 12:00 UTC = 13:00 Lagos
        $this->assertSame([4, 3], [$noon['views'], $noon['visitors']]);

        // Daily series fills empty days; unique visitors aren't double counted.
        $week = (new Report('7d'))->series();
        $this->assertCount(7, $week['points']);
        $this->assertSame(3, collect($week['points'])->last()['visitors']);

        // Comparison with the previous period (no data then).
        $this->assertNull((new Report('today'))->summary()['change']['visitors']);
        Carbon::setTestNow();
    }

    public function test_admin_analytics_pages(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();
        $this->pageview($vid, $sid, '/', [], self::CHROME, ['CF-IPCountry' => 'NG']);
        $this->hit(['t' => 'event', 'vid' => $vid, 'sid' => $sid, 'path' => '/', 'name' => 'search', 'label' => 'blender']);
        $visitorId = DB::table('stats_visitors')->value('id');

        $this->actingAsAdmin();
        $this->get('/admin/analytics')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/Overview')
            ->where('summary.totals.visitors', 1)
            ->where('countries.0.code', 'NG')
            ->where('searches.0.name', 'blender')
            ->has('summary.series.points', 7));
        $this->get('/admin/analytics?period=custom&from=2026-01-01&to=2026-01-31')->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.period', 'custom'));
        $this->get('/admin/analytics/realtime')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('realtime.active5', 1)
            ->where('realtime.recent.0.path', '/')
            ->where('realtime.perMinute', fn ($m) => array_sum($m->all()) === 1 && $m->last() === 1));
        $this->get('/admin/analytics/visitors')->assertOk()->assertInertia(fn (Assert $page) => $page->has('visitors.data', 1)->where('visitors.data.0.country', 'NG'));
        $this->get('/admin/analytics/visitors?country=GH')->assertInertia(fn (Assert $page) => $page->has('visitors.data', 0));
        $this->get("/admin/analytics/visitors/{$visitorId}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/Visitor')
            ->where('sessions.0.timeline.0.type', 'view')
            ->where('sessions.0.timeline.1.name', 'search'));
        $this->get('/admin/analytics/visitors/999999')->assertNotFound();

        // Storefront pages tell the beacon whether to run; the admin never does.
        auth()->logout();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('stats', true));
    }
}
