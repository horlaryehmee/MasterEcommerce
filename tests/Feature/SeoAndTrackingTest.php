<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(array $attrs): BusinessSetting
    {
        $s = BusinessSetting::current();
        $s->update($attrs);
        BusinessSetting::forgetResolved();

        return $s;
    }

    public function test_storefront_head_has_verification_custom_meta_and_product_tags(): void
    {
        $this->settings([
            'business_name' => 'Nexa',
            'title_separator' => '—',
            'twitter_handle' => 'nexastores',
            'verify_google' => 'abc123',
            'verify_facebook' => 'fb987',
            'custom_meta' => [['attribute' => 'name', 'key' => 'author', 'content' => 'Nexa Team'], ['attribute' => 'property', 'key' => 'fb:app_id', 'content' => '42']],
        ]);
        $product = Product::factory()->create(['name' => 'Desk Lamp', 'price' => 15000, 'availability' => 'in_stock']);

        $this->get($product->url())
            ->assertOk()
            ->assertSee('<title inertia>Desk Lamp — Nexa</title>', false)
            ->assertSee('<meta name="google-site-verification" content="abc123">', false)
            ->assertSee('<meta name="facebook-domain-verification" content="fb987">', false)
            ->assertSee('<meta name="author" content="Nexa Team">', false)
            ->assertSee('<meta property="fb:app_id" content="42">', false)
            ->assertSee('<meta name="twitter:site" content="@nexastores">', false)
            ->assertSee('<meta property="product:price:amount" content="15000.00">', false)
            ->assertSee('<meta property="product:availability" content="in stock">', false)
            ->assertSee('max-image-preview:large', false)
            ->assertSee('"itemCondition":"https://schema.org/NewCondition"', false);

        // Nothing storefront-specific in the admin.
        $this->get('/admin/login')->assertDontSee('google-site-verification')->assertDontSee('Nexa Team');
    }

    public function test_discouraging_search_engines_noindexes_pages_and_robots(): void
    {
        $this->get('/')->assertSee('index, follow, max-image-preview:large', false);

        $this->settings(['search_engines_visible' => false]);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // On a live site: the switch decides robots.txt, and extra rules are appended.
        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertSee("User-agent: *\nDisallow: /\n", false)->assertDontSee('Sitemap:');

        $this->settings(['search_engines_visible' => true, 'robots_extra' => "User-agent: GPTBot\nDisallow: /"]);
        $this->get('/robots.txt')
            ->assertSee('Disallow: /checkout')
            ->assertSee("User-agent: GPTBot\nDisallow: /", false)
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_paginated_lists_use_self_referencing_canonicals_and_sitemap_lists_images(): void
    {
        $category = Category::factory()->create(['slug' => 'lamps', 'image_path' => 'categories/lamps.webp']);
        $products = Product::factory()->count(13)->create(['category_id' => $category->id]);
        ProductImage::query()->forceCreate(['product_id' => $products[0]->id, 'path' => 'products/lamp-a.webp', 'is_primary' => true]);
        ProductImage::query()->forceCreate(['product_id' => $products[0]->id, 'path' => 'products/lamp-b.webp']);

        $this->get('/products')->assertSee('<link rel="canonical" href="'.route('products.index').'">', false);
        $this->get('/products?page=2')->assertSee('<link rel="canonical" href="'.route('products.index').'?page=2">', false);
        $this->get('/categories/lamps?page=2&sort=price_asc')->assertSee('<link rel="canonical" href="'.route('categories.show', 'lamps').'?page=2">', false);

        $this->get('/sitemap.xml')
            ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
            ->assertSee('products/lamp-a.webp')
            ->assertSee('products/lamp-b.webp')
            ->assertSee('categories/lamps.webp');
    }

    public function test_pixels_load_for_visitors_but_not_the_admin_or_logged_in_owner(): void
    {
        $this->settings(['ga4_id' => 'G-TEST123', 'gtm_id' => 'GTM-ABC1234', 'meta_pixel_id' => '123456789012', 'tiktok_pixel_id' => 'C4ABCDEFGH1234567890', 'google_ads_id' => 'AW-1234567', 'google_ads_purchase_label' => 'Label_1']);

        $this->get('/')
            ->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee('googletagmanager.com/ns.html?id=GTM-ABC1234', false)
            ->assertSee("fbq('init',\"123456789012\")", false)
            ->assertSee('ttq.load("C4ABCDEFGH1234567890")', false)
            ->assertInertia(fn (Assert $page) => $page->where('tracking.ga4', 'G-TEST123')->where('tracking.adsPurchaseLabel', 'AW-1234567/Label_1')->where('tracking.currency', 'NGN'));

        $this->actingAsAdmin();
        $this->get('/')->assertDontSee('gtag/js')->assertDontSee('fbevents.js')->assertInertia(fn (Assert $page) => $page->where('tracking', null));
        $this->get('/admin')->assertDontSee('gtag/js');

        $this->settings(['track_admins' => true]);
        $this->get('/')->assertSee('gtag/js?id=G-TEST123', false);
        $this->get('/admin')->assertDontSee('gtag/js');
    }

    public function test_custom_code_is_added_to_storefront_pages_only(): void
    {
        $this->settings(['head_code' => '<meta name="x-head" content="1">', 'body_code' => '<div id="x-body"></div>', 'footer_code' => '<script>window.xFooter=1</script>']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="x-head" content="1">', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'x-head'));
        $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'x-body'));
        $this->assertGreaterThan(strpos($html, 'x-body'), strpos($html, 'xFooter'));

        $this->get('/admin/login')->assertDontSee('x-head')->assertDontSee('xFooter');
    }

    public function test_shop_events_are_sent_for_the_pixels(): void
    {
        $this->settings(['shop_enabled' => true, 'meta_pixel_id' => '123456789012']);
        $product = Product::factory()->create(['name' => 'Kettle', 'price' => 8000]);

        $this->get($product->url())->assertInertia(fn (Assert $page) => $page
            ->where('track.0.event', 'view_item')
            ->where('track.0.params.value', 8000)
            ->where('track.0.params.items.0.item_name', 'Kettle'));

        $this->get('/products?search=kettle')->assertInertia(fn (Assert $page) => $page->where('track.0.event', 'search')->where('track.0.params.search_term', 'kettle'));

        $this->from($product->url())->post('/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertSessionHas('analytics.0.event', 'add_to_cart')
            ->assertSessionHas('analytics.0.params.value', 16000);

        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page->where('track.0.event', 'begin_checkout')->where('track.0.params.value', 16000));

        $this->post('/checkout', [
            'customer_name' => 'Ada', 'customer_phone' => '08012345678', 'country' => 'NG', 'state' => 'LA',
            'city' => 'Ikeja', 'address' => '1 Road', 'payment_method' => 'delivery',
        ])->assertSessionHas('analytics.0.event', 'purchase');

        $order = Order::query()->sole();
        $this->get($order->url())->assertInertia(fn (Assert $page) => $page
            ->where('analytics.0.params.transaction_id', '1001')
            ->where('analytics.0.params.value', 16000)
            ->where('analytics.0.params.items.0.quantity', 2));
        // Shown once only.
        $this->get($order->url())->assertInertia(fn (Assert $page) => $page->where('analytics', null));
    }

    public function test_admin_saves_seo_tracking_and_code_settings(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/settings?tab=seo')->assertOk()->assertInertia(fn (Assert $page) => $page->has('titleSeparators')->where('settings.search_engines_visible', true));
        foreach (['tracking', 'code', 'redirects'] as $tab) {
            $this->get("/admin/settings?tab={$tab}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('tab', $tab));
        }

        $this->put('/admin/settings/seo', [
            'title_separator' => '·',
            'search_engines_visible' => '0',
            'twitter_handle' => '@nexa_ng',
            'verify_google' => '<meta name="google-site-verification" content="g-Code_123" />',
            'verify_bing' => '"BING456"',
            'custom_meta' => [['attribute' => 'name', 'key' => 'author', 'content' => 'Me'], ['attribute' => 'name', 'key' => '', 'content' => '']],
        ])->assertSessionHasNoErrors();

        $s = BusinessSetting::query()->first();
        $this->assertSame(['·', false, 'nexa_ng', 'g-Code_123', 'BING456'], [$s->title_separator, $s->search_engines_visible, $s->twitter_handle, $s->verify_google, $s->verify_bing]);
        $this->assertSame([['attribute' => 'name', 'key' => 'author', 'content' => 'Me']], $s->custom_meta);

        $this->put('/admin/settings/seo', ['title_separator' => '~', 'twitter_handle' => 'way too long handle!', 'custom_meta' => [['attribute' => 'script', 'key' => 'x"><script>', 'content' => '']]])
            ->assertSessionHasErrors(['title_separator', 'twitter_handle', 'custom_meta.0.attribute', 'custom_meta.0.key']);

        $this->put('/admin/settings/tracking', ['ga4_id' => 'g-abc123', 'meta_pixel_id' => '123456789', 'track_admins' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('G-ABC123', BusinessSetting::query()->first()->ga4_id);
        $this->put('/admin/settings/tracking', ['ga4_id' => 'UA-123', 'gtm_id' => 'GTM-', 'meta_pixel_id' => 'abc', 'google_ads_id' => 'AW-x'])
            ->assertSessionHasErrors(['ga4_id', 'gtm_id', 'meta_pixel_id', 'google_ads_id']);

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $this->put('/admin/settings/code', ['head_code' => '<script>1</script>', 'footer_code' => ''])->assertSessionHasNoErrors();
        $this->assertSame('<script>1</script>', BusinessSetting::query()->first()->head_code);

        $this->put('/admin/settings/redirects', [])->assertNotFound();
    }

    public function test_changed_product_and_category_urls_redirect_automatically(): void
    {
        $category = Category::factory()->create(['slug' => 'lamps']);
        $product = Product::factory()->create(['slug' => 'desk-lamp', 'category_id' => $category->id]);

        $product->update(['slug' => 'led-desk-lamp']);
        $this->get('/products/desk-lamp')->assertStatus(301)->assertRedirect(route('products.show', 'led-desk-lamp'));
        $this->get('/products/desk-lamp?utm_source=ig')->assertRedirect(route('products.show', 'led-desk-lamp').'?utm_source=ig');

        // Renaming again points the oldest URL straight at the newest (no chains).
        $product->update(['slug' => 'smart-desk-lamp']);
        $this->get('/products/desk-lamp')->assertRedirect(route('products.show', 'smart-desk-lamp'));
        $this->get('/products/led-desk-lamp')->assertRedirect(route('products.show', 'smart-desk-lamp'));
        $this->get('/products/smart-desk-lamp')->assertOk();

        // Going back to an old slug removes the loop.
        $product->update(['slug' => 'desk-lamp']);
        $this->get('/products/desk-lamp')->assertOk();

        $category->update(['slug' => 'lighting']);
        $this->get('/categories/lamps')->assertRedirect(route('categories.show', 'lighting'));
        $this->assertSame(3, Redirect::query()->where('is_automatic', true)->count());
        $this->assertGreaterThan(0, Redirect::query()->where('from_path', '/products/led-desk-lamp')->value('hits'));

        // Drafts never had a public URL, so no redirect is created.
        $draft = Product::factory()->draft()->create(['slug' => 'secret']);
        $draft->update(['slug' => 'still-secret']);
        $this->assertNull(Redirect::query()->where('from_path', '/products/secret')->first());
    }

    public function test_admin_manages_manual_redirects(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->post('/admin/redirects', ['from_path' => 'https://example.com/Old-Page/?x=1', 'to_path' => '/products', 'status_code' => 302])->assertRedirect('/admin/settings?tab=redirects');
        $redirect = Redirect::query()->sole();
        $this->assertSame(['/old-page', '/products', 302, false], [$redirect->from_path, $redirect->to_path, $redirect->status_code, $redirect->is_automatic]);

        // Works for addresses that don't exist as routes.
        auth()->logout();
        $this->get('/old-page')->assertStatus(302)->assertRedirect(url('/products'));
        $this->get('/OLD-PAGE/')->assertRedirect(url('/products'));
        $this->actingAs($admin);

        $this->post('/admin/redirects', ['from_path' => '/old-page', 'to_path' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $this->post('/admin/redirects', ['from_path' => '/admin/settings', 'to_path' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $this->post('/admin/redirects', ['from_path' => '/', 'to_path' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $this->post('/admin/redirects', ['from_path' => '/a', 'to_path' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('to_path');
        $this->post('/admin/redirects', ['from_path' => '/a', 'to_path' => '/a', 'status_code' => 301])->assertSessionHasErrors('to_path');
        $this->post('/admin/redirects', ['from_path' => '/blog', 'to_path' => 'https://blog.example.com', 'status_code' => 301])->assertSessionHasNoErrors();

        $this->delete("/admin/redirects/{$redirect->id}")->assertRedirect('/admin/settings?tab=redirects');
        $this->get('/old-page')->assertNotFound();
    }
}
