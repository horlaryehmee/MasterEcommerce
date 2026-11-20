<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\SocialLink;
use App\Support\Money;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAndContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
        $this->actingAsAdmin();
    }

    public function test_settings_and_content_pages_render(): void
    {
        foreach (['business', 'branding', 'currency', 'whatsapp', 'social', 'seo'] as $tab) {
            $this->get("/admin/settings?tab={$tab}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Settings/Edit')->where('tab', $tab));
        }
        $this->get('/admin/content')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Content/Edit')->has('pages', 6));
        $this->get('/admin/pages/'.Page::first()->id.'/edit')->assertOk();
    }

    public function test_business_information_with_logo_and_favicon(): void
    {
        $this->put('/admin/settings/business', [
            'business_name' => 'Ada Lighting',
            'business_description' => 'Lamps and fittings.',
            'email' => 'hi@ada.test',
            'phone' => '+234 801 234 5678',
            'logo' => UploadedFile::fake()->image('logo.png', 800, 200),
            'favicon' => UploadedFile::fake()->image('fav.png', 256, 256),
        ])->assertSessionHasNoErrors();

        $s = BusinessSetting::query()->first();
        $this->assertSame('Ada Lighting', $s->business_name);
        Storage::disk('public')->assertExists([$s->logo_path, $s->favicon_path]);
        $this->assertStringEndsWith('.png', $s->favicon_path);

        // The new name appears on the storefront (cache is refreshed on save).
        $this->get('/')->assertSee('Ada Lighting', false);

        $this->put('/admin/settings/business', ['business_name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['business_name', 'email']);
    }

    /** What the admin form really sends: multipart POST + _method=put, booleans as "0", empty file fields. */
    public function test_business_information_saves_from_the_browser_form_with_a_logo(): void
    {
        $this->post('/admin/settings/business', [
            '_method' => 'put',
            'business_name' => 'Nexa Stores',
            'business_description' => '',
            'email' => 'hello@nexa.test',
            'phone' => '',
            'address' => '',
            'opening_hours' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 600, 200),
            'favicon' => '',
            'remove_logo' => '0',
            'remove_favicon' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $s = BusinessSetting::query()->first();
        $this->assertSame(['Nexa Stores', 'hello@nexa.test'], [$s->business_name, $s->email]);
        $this->assertNotNull($s->logo_path);
        Storage::disk('public')->assertExists($s->logo_path);
    }

    public function test_branding_colours_are_validated_and_applied(): void
    {
        $this->put('/admin/settings/branding', ['primary_color' => 'red', 'secondary_color' => '#FFFFFF', 'accent_color' => '#000000'])->assertSessionHasErrors('primary_color');
        $this->put('/admin/settings/branding', ['primary_color' => '#1d4ed8', 'secondary_color' => '#f8fafc', 'accent_color' => '#e11d48'])->assertSessionHasNoErrors();

        $this->get('/')->assertSee('--brand-primary: #1D4ED8', false)->assertSee('--brand-accent: #E11D48', false);
    }

    public function test_currency_settings_change_price_formatting(): void
    {
        $this->put('/admin/settings/currency', [
            'currency_code' => 'eur', 'currency_symbol' => '€', 'currency_position' => 'after',
            'decimal_places' => 2, 'thousands_separator' => '.', 'decimal_separator' => ',',
        ])->assertSessionHasNoErrors();
        BusinessSetting::forgetResolved();
        $this->assertSame('1.234,50 €', Money::format(1234.5));
        $this->assertSame('EUR', BusinessSetting::current()->currency_code);

        // A space thousands separator survives Laravel's string trimming.
        $this->put('/admin/settings/currency', [
            'currency_code' => 'ZAR', 'currency_symbol' => 'R', 'currency_position' => 'before',
            'decimal_places' => 0, 'thousands_separator' => ' ', 'decimal_separator' => '.',
        ]);
        BusinessSetting::forgetResolved();
        $this->assertSame('R1 234 567', Money::format(1234567));

        $this->put('/admin/settings/currency', ['currency_code' => 'NAIRA', 'currency_position' => 'middle', 'decimal_places' => 9, 'decimal_separator' => '-'])
            ->assertSessionHasErrors(['currency_code', 'currency_position', 'decimal_places', 'decimal_separator']);
    }

    public function test_whatsapp_number_is_validated_and_normalised(): void
    {
        $this->put('/admin/settings/whatsapp', ['whatsapp_number' => '08012345678'])->assertSessionHasErrors('whatsapp_number');
        $this->put('/admin/settings/whatsapp', ['whatsapp_number' => '123'])->assertSessionHasErrors('whatsapp_number');

        $this->put('/admin/settings/whatsapp', [
            'whatsapp_number' => '+234 (801) 234-5678',
            'whatsapp_general_message' => 'Hi {business_name}!',
            'whatsapp_product_message' => 'I want {product_name} for {product_price}: {product_url}',
            'show_floating_whatsapp' => '0',
        ])->assertSessionHasNoErrors();

        $s = BusinessSetting::query()->first();
        $this->assertSame('2348012345678', $s->whatsapp_number);
        $this->assertFalse($s->show_floating_whatsapp);
    }

    public function test_social_links_only_accept_matching_platform_urls(): void
    {
        $this->put('/admin/settings/social', ['links' => ['instagram' => 'https://evil.example.com/x']])->assertSessionHasErrors('links.instagram');
        $this->put('/admin/settings/social', ['links' => ['facebook' => 'javascript:alert(1)']])->assertSessionHasErrors('links.facebook');

        $this->put('/admin/settings/social', ['links' => [
            'instagram' => 'https://www.instagram.com/adalighting',
            'x' => 'https://twitter.com/ada',
            'tiktok' => '',
        ]])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['instagram', 'x'], SocialLink::pluck('platform')->all());

        // Clearing a link removes it.
        $this->put('/admin/settings/social', ['links' => ['instagram' => 'https://instagram.com/adalighting']]);
        $this->assertEquals(['instagram'], SocialLink::pluck('platform')->all());
    }

    public function test_homepage_hero_section_with_image(): void
    {
        $this->put('/admin/content/hero', [
            'hero_enabled' => '1',
            'hero_heading' => 'Light up your home',
            'hero_primary_text' => 'Shop',
            'hero_primary_link' => '/products',
            'hero_secondary_text' => 'Chat',
            'hero_secondary_link' => 'whatsapp',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 2400, 1600),
        ])->assertSessionHasNoErrors();

        $home = HomepageSetting::query()->first();
        $this->assertSame('Light up your home', $home->hero_heading);
        Storage::disk('public')->assertExists($home->hero_image_path);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero.content.heading', 'Light up your home')->where('hero.content.secondary.link', 'whatsapp'));
    }

    public function test_unsafe_links_and_missing_headings_are_rejected(): void
    {
        $this->put('/admin/content/hero', ['hero_enabled' => '1', 'hero_heading' => 'Hi', 'hero_primary_text' => 'Go', 'hero_primary_link' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('hero_primary_link');
        $this->put('/admin/content/hero', ['hero_enabled' => '1', 'hero_heading' => ''])->assertSessionHasErrors('hero_heading');
        $this->put('/admin/content/announcement', ['announcement_enabled' => '1', 'announcement_link' => '//evil.com'])->assertSessionHasErrors(['announcement_text', 'announcement_link']);
        $this->put('/admin/content/unknown', [])->assertNotFound();
    }

    public function test_sections_can_be_disabled_and_hidden(): void
    {
        $this->put('/admin/content/hero', ['hero_enabled' => '0'])->assertSessionHasNoErrors();
        $this->put('/admin/content/announcement', ['announcement_enabled' => '0']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero', null)->where('store.announcement', null));
    }

    public function test_featured_categories_and_products_selection(): void
    {
        [$a, $b] = Category::factory()->count(2)->create();
        [$p1, $p2] = Product::factory()->count(2)->create(['category_id' => $a->id]);
        $p2->update(['is_featured' => true]);

        $this->put('/admin/content/categories', ['categories_enabled' => '1', 'categories_heading' => 'Shop', 'featured_ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertTrue($b->fresh()->is_featured);
        $this->assertFalse($a->fresh()->is_featured);

        $this->put('/admin/content/featured', ['featured_enabled' => '1', 'featured_heading' => 'Top', 'featured_ids' => [$p1->id]])->assertSessionHasNoErrors();
        $this->assertTrue($p1->fresh()->is_featured);
        $this->assertFalse($p2->fresh()->is_featured);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('featured.items', 1)->where('featured.items.0.id', $p1->id));
    }

    public function test_why_choose_us_items_and_footer(): void
    {
        $this->put('/admin/content/why', [
            'why_enabled' => '1',
            'why_heading' => 'Why us',
            'why_items' => [['icon' => 'truck', 'title' => 'Fast delivery', 'text' => 'Same day in Lagos']],
        ])->assertSessionHasNoErrors();
        $this->put('/admin/content/why', ['why_enabled' => '1', 'why_items' => [['icon' => 'bomb', 'title' => '']]])->assertSessionHasErrors(['why_items.0.icon', 'why_items.0.title']);

        $this->put('/admin/content/footer', ['footer_description' => 'We sell lamps.', 'copyright_text' => '© {year} Ada'])->assertSessionHasNoErrors();

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('why.items.0.title', 'Fast delivery')
            ->where('store.footer_description', 'We sell lamps.')
            ->where('store.copyright', '© '.date('Y').' Ada'));
    }

    public function test_pages_can_be_edited_and_unpublished(): void
    {
        $page = Page::where('slug', 'faq')->first();
        $this->put("/admin/pages/{$page->id}", ['title' => 'Help', 'content' => '## Question', 'is_published' => '1'])->assertSessionHasNoErrors();
        $this->get('/faq')->assertOk()->assertInertia(fn (Assert $p) => $p->where('page.title', 'Help'));

        $this->put("/admin/pages/{$page->id}", ['title' => 'Help', 'is_published' => '0']);
        $this->get('/faq')->assertNotFound();
    }
}
