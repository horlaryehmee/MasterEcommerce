<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Page;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutAndFaqPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
        BusinessSetting::current()->update([
            'business_name' => 'Ada Lighting',
            'business_description' => 'Beautiful lamps for every room.',
            'phone' => '+234 801 000 0000',
            'whatsapp_number' => '2348010000000',
        ]);
    }

    public function test_about_page_has_story_values_collections_and_contact(): void
    {
        HomepageSetting::current()->update([
            'why_heading' => 'Our values',
            'why_items' => [['icon' => 'heart', 'title' => 'Care', 'text' => 'We care.'], ['icon' => 'star', 'title' => '', 'text' => 'ignored']],
        ]);
        Category::factory()->count(5)->create();

        $this->get('/about')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Storefront/About')
            ->where('page.title', 'About Us')
            ->where('page.intro', 'Beautiful lamps for every room.')
            ->where('page.content_html', fn ($h) => str_contains($h, 'Ada Lighting') && str_contains($h, '<h2>Our story</h2>'))
            ->where('values.heading', 'Our values')
            ->has('values.items', 1)
            ->has('categories', 5) // the collections mosaic: one large tile and four small
            ->where('contact.phone', '+234 801 000 0000'));
    }

    public function test_about_page_hides_values_when_there_are_none(): void
    {
        HomepageSetting::current()->update(['why_items' => []]);
        $this->get('/about')->assertInertia(fn (Assert $p) => $p->where('values', null)->where('page.image', null));
    }

    public function test_about_page_image_upload_and_homepage_fallback(): void
    {
        $this->actingAsAdmin();
        $about = Page::where('slug', 'about')->first();

        $this->get("/admin/pages/{$about->id}/edit")->assertInertia(fn (Assert $p) => $p->where('supportsImage', true)->where('supportsFaq', false));

        $this->put("/admin/pages/{$about->id}", [
            'title' => 'Our Story',
            'content' => 'We started small.',
            'is_published' => '1',
            'image' => UploadedFile::fake()->image('team.jpg', 2000, 1600),
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'optimised'));

        $about->refresh();
        Storage::disk('public')->assertExists($about->image_path);
        $this->get('/about')->assertInertia(fn (Assert $p) => $p->where('page.title', 'Our Story')->where('page.image.src', fn ($src) => str_contains($src, $about->image_path)));

        // Removing it falls back to the homepage About image.
        HomepageSetting::current()->update(['about_image_path' => 'banners/home-about.webp']);
        $this->put("/admin/pages/{$about->id}", ['title' => 'Our Story', 'is_published' => '1', 'remove_image' => '1']);
        $this->assertNull($about->fresh()->image_path);
        $this->get('/about')->assertInertia(fn (Assert $p) => $p->where('page.image.src', fn ($src) => str_contains($src, 'home-about')));
    }

    public function test_faq_page_shows_structured_questions_with_schema(): void
    {
        $response = $this->get('/faq')->assertOk();

        $response->assertSee('"@type":"FAQPage"', false)
            ->assertSee('How do I place an order?', false)
            ->assertInertia(fn (Assert $p) => $p
                ->component('Storefront/Faq')
                ->has('page.items', 10)
                ->where('page.items.0.question', 'How do I place an order?')
                ->where('page.items.0.answer_html', fn ($h) => str_contains($h, '<strong>Enquire on WhatsApp</strong>'))
                ->where('contact.phone', '+234 801 000 0000'));
    }

    public function test_admin_can_edit_faq_questions(): void
    {
        $this->actingAsAdmin();
        $faq = Page::where('slug', 'faq')->first();

        $this->get("/admin/pages/{$faq->id}/edit")->assertInertia(fn (Assert $p) => $p->where('supportsFaq', true)->has('page.faq_items', 10));

        $this->put("/admin/pages/{$faq->id}", [
            'title' => 'Help Centre',
            'content' => 'Answers from {business_name}.',
            'is_published' => '1',
            'faq_items' => [
                ['question' => 'Do you install lamps?', 'answer' => 'Yes — call {phone}. <script>alert(1)</script>'],
                ['question' => 'Warranty?', 'answer' => '12 months on **all** lamps.'],
            ],
        ])->assertSessionHasNoErrors();

        $this->get('/faq')->assertInertia(fn (Assert $p) => $p
            ->where('page.title', 'Help Centre')
            ->where('page.content_html', fn ($h) => str_contains($h, 'Answers from Ada Lighting.'))
            ->has('page.items', 2)
            ->where('page.items.0.answer_html', fn ($h) => str_contains($h, '+234 801 000 0000') && ! str_contains($h, '<script'))
            ->where('page.items.1.answer_html', fn ($h) => str_contains($h, '<strong>all</strong>')));

        $this->put("/admin/pages/{$faq->id}", ['title' => 'Help', 'is_published' => '1', 'faq_items' => [['question' => '', 'answer' => '']]])
            ->assertSessionHasErrors(['faq_items.0.question', 'faq_items.0.answer']);
    }

    public function test_faq_items_are_ignored_on_other_pages(): void
    {
        $this->actingAsAdmin();
        $terms = Page::where('slug', 'terms-and-conditions')->first();
        $this->put("/admin/pages/{$terms->id}", ['title' => 'Terms', 'is_published' => '1', 'faq_items' => [['question' => 'x', 'answer' => 'y']]]);
        $this->assertNull($terms->fresh()->faq_items);
    }

    public function test_upgrade_migration_converts_old_markdown_faqs(): void
    {
        DB::table('pages')->where('slug', 'faq')->update([
            'faq_items' => null,
            'content' => "Intro text.\n\n## How do I order?\n\nMessage us on WhatsApp.\n\n## Do you deliver?\n\nYes, **nationwide**.",
        ]);

        (require database_path('migrations/2026_01_01_000400_add_image_and_faq_items_to_pages.php'))->convertMarkdownFaq();

        $faq = Page::where('slug', 'faq')->first();
        $this->assertSame('Intro text.', $faq->content);
        // assertEquals: MySQL's JSON type reorders object keys; the values are what matter.
        $this->assertEquals([
            ['question' => 'How do I order?', 'answer' => 'Message us on WhatsApp.'],
            ['question' => 'Do you deliver?', 'answer' => 'Yes, **nationwide**.'],
        ], $faq->faq_items);
    }
}
