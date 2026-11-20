<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\SocialLink;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
        BusinessSetting::current()->update(['business_name' => 'Test Shop', 'whatsapp_number' => '2348000000000']);
    }

    public function test_homepage_renders_with_sections_and_shared_store_data(): void
    {
        $cat = Category::factory()->create(['name' => 'Lamps', 'is_featured' => true]);
        Product::factory()->featured()->count(3)->create(['category_id' => $cat->id]);
        Product::factory()->draft()->featured()->create(['category_id' => $cat->id]);
        SocialLink::create(['platform' => 'instagram', 'url' => 'https://instagram.com/test']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title inertia>Test Shop</title>', false)
            ->assertSee('application/ld+json', false)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Storefront/Home')
                ->has('featured.items', 3) // the draft is excluded
                ->has('categories.items', 1)
                ->where('store.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/2348000000000?text='))
                ->has('store.socials', 1)
                ->has('store.pages', 6));
    }

    public function test_empty_store_homepage_hides_sections_gracefully(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->where('featured', null)->where('newArrivals', null)->where('categories', null));
    }

    public function test_catalog_search_filter_sort_and_pagination(): void
    {
        $phones = Category::factory()->create(['slug' => 'phones']);
        $shoes = Category::factory()->create(['slug' => 'shoes']);
        Product::factory()->create(['name' => 'Alpha Phone', 'price' => 300, 'category_id' => $phones->id]);
        Product::factory()->create(['name' => 'Beta Phone', 'price' => 100, 'category_id' => $phones->id, 'description' => 'waterproof body']);
        Product::factory()->create(['name' => 'Gamma Shoe', 'price' => 200, 'category_id' => $shoes->id]);
        Product::factory()->draft()->create(['name' => 'Hidden Phone', 'category_id' => $phones->id]);

        $this->get('/products')->assertInertia(fn (Assert $p) => $p->component('Storefront/Catalog')->where('products.total', 3));
        $this->get('/products?search=phone')->assertInertia(fn (Assert $p) => $p->where('products.total', 2));
        $this->get('/products?search=waterproof')->assertInertia(fn (Assert $p) => $p->where('products.total', 1)->where('products.data.0.name', 'Beta Phone'));
        $this->get('/products?search=100%25')->assertInertia(fn (Assert $p) => $p->where('products.total', 0)); // "%" is matched literally
        $this->get('/products?category=shoes')->assertInertia(fn (Assert $p) => $p->where('products.total', 1));
        $this->get('/products?sort=price_asc')->assertInertia(fn (Assert $p) => $p->where('products.data.0.name', 'Beta Phone'));
        $this->get('/products?sort=price_desc')->assertInertia(fn (Assert $p) => $p->where('products.data.0.name', 'Alpha Phone'));
        $this->get('/products?sort=name_desc')->assertInertia(fn (Assert $p) => $p->where('products.data.0.name', 'Gamma Shoe'));
        $this->get('/products?sort=DROP TABLE')->assertInertia(fn (Assert $p) => $p->where('filters.sort', 'latest'));

        Product::factory()->count(12)->create();
        $this->get('/products?page=2')->assertInertia(fn (Assert $p) => $p->where('products.current_page', 2)->where('products.last_page', 2)->has('products.data', 3));
    }

    public function test_search_alias_redirects_to_catalog(): void
    {
        $this->get('/search?q=lamp')->assertRedirect('/products?search=lamp');
    }

    public function test_product_page_has_whatsapp_enquiry_and_seo(): void
    {
        $cat = Category::factory()->create(['name' => 'Audio']);
        $product = Product::factory()->create([
            'name' => 'Pulse Headphones',
            'slug' => 'pulse-headphones',
            'price' => 78500,
            'compare_at_price' => 95000,
            'category_id' => $cat->id,
            'seo_title' => 'Buy Pulse Headphones',
            'seo_description' => 'Great sound.',
            'description' => "Hello <script>alert('x')</script> **bold** [bad](javascript:alert(1))",
        ]);
        Product::factory()->count(2)->create(['category_id' => $cat->id]);

        $response = $this->get('/products/pulse-headphones')->assertOk();

        $response->assertSee('<title inertia>Buy Pulse Headphones | Test Shop</title>', false)
            ->assertSee('<meta name="description" content="Great sound.">', false)
            ->assertSee('<link rel="canonical" href="'.url('/products/pulse-headphones').'">', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"price":"78500.00"', false)
            ->assertDontSee('ratingValue', false);

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Storefront/ProductShow')
            ->where('product.price', '₦78,500')
            ->where('product.compare_at_price', '₦95,000')
            ->where('product.discount_percent', 17)
            ->where('product.category.name', 'Audio')
            ->where('product.description_html', fn ($html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:') && str_contains($html, '<strong>bold</strong>'))
            ->where('whatsappUrl', function ($url) {
                $text = urldecode(parse_url($url, PHP_URL_QUERY));

                return str_starts_with($url, 'https://wa.me/2348000000000?text=')
                    && str_contains($text, 'Pulse Headphones')
                    && str_contains($text, '₦78,500')
                    && str_contains($text, url('/products/pulse-headphones'))
                    && str_contains($text, 'Test Shop');
            })
            ->has('related', 2));
    }

    public function test_drafts_and_hidden_categories_are_not_public(): void
    {
        $draft = Product::factory()->draft()->create(['slug' => 'secret']);
        $hiddenCat = Category::factory()->hidden()->create(['slug' => 'hidden']);
        $inHidden = Product::factory()->create(['slug' => 'in-hidden', 'category_id' => $hiddenCat->id]);

        $this->get('/products/secret')->assertNotFound();
        $this->get('/products/in-hidden')->assertNotFound();
        $this->get('/categories/hidden')->assertNotFound();
        $this->get('/products')->assertInertia(fn (Assert $p) => $p->where('products.total', 0));

        // The admin can preview them.
        $this->actingAsAdmin();
        $this->get('/products/secret')->assertOk()
            ->assertSee('noindex', false)
            ->assertInertia(fn (Assert $p) => $p->where('isPreview', true));
    }

    public function test_category_pages(): void
    {
        $cat = Category::factory()->create(['name' => 'Lighting', 'slug' => 'lighting', 'description' => 'Lamps.']);
        Product::factory()->count(2)->create(['category_id' => $cat->id]);
        Product::factory()->create();

        $this->get('/categories')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Storefront/Categories')->has('categories', 2));
        $this->get('/categories/lighting')->assertOk()
            ->assertSee('<title inertia>Lighting | Test Shop</title>', false)
            ->assertInertia(fn (Assert $p) => $p->component('Storefront/CategoryShow')->where('products.total', 2));
        $this->get('/categories/nope')->assertNotFound();
    }

    public function test_information_pages_replace_placeholders(): void
    {
        BusinessSetting::current()->update(['email' => 'hello@test.shop']);

        $this->get('/about')->assertOk()->assertInertia(fn (Assert $p) => $p->where('page.content_html', fn ($h) => str_contains($h, 'Test Shop') && ! str_contains($h, '{business_name}')));
        $this->get('/privacy-policy')->assertOk()->assertInertia(fn (Assert $p) => $p->where('page.content_html', fn ($h) => str_contains($h, 'hello@test.shop')));
        $this->get('/contact')->assertOk()->assertInertia(fn (Assert $p) => $p->where('contact.email', 'hello@test.shop'));
        foreach (['/faq', '/terms-and-conditions', '/shipping-and-delivery'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_sitemap_and_robots(): void
    {
        Product::factory()->create(['slug' => 'visible-item']);
        Product::factory()->draft()->create(['slug' => 'draft-item']);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/products/visible-item', false)
            ->assertDontSee('draft-item', false)
            ->assertSee('/about', false);

        // Non-production installs are kept out of search engines.
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }

    public function test_unknown_pages_show_the_friendly_404(): void
    {
        $this->get('/products/does-not-exist')->assertNotFound()->assertInertia(fn (Assert $p) => $p->component('Error')->where('status', 404));
    }

    public function test_whatsapp_falls_back_gracefully_without_a_number(): void
    {
        BusinessSetting::current()->update(['whatsapp_number' => null]);
        $product = Product::factory()->create();

        $this->get('/products/'.$product->slug)->assertInertia(fn (Assert $p) => $p->where('whatsappUrl', null)->where('store.whatsapp_url', null));
    }
}
