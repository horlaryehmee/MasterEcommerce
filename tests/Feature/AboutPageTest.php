<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
    }

    public function test_headline_numbers_are_edited_and_shown(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        $this->actingAs(User::factory()->create())->put("/admin/pages/{$about->id}", [
            'title' => 'About Us',
            'content' => 'Our story.',
            'is_published' => true,
            'stats' => [['value' => ' 8+ ', 'label' => 'Years in business'], ['value' => '12,000+', 'label' => 'Happy customers']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('8+', $about->fresh()->extras['stats'][0]['value']);

        $this->get('/about')->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/About')
            ->has('stats', 2)
            ->where('stats.1.label', 'Happy customers')
            ->missing('quote')
            ->missing('contact'));
    }

    public function test_the_closing_box_wording_can_be_changed(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        // Standard wording until the owner changes it.
        $this->get('/about')->assertInertia(fn (Assert $page) => $page->where('cta', Page::ABOUT_CTA));

        $this->actingAs(User::factory()->create())->put("/admin/pages/{$about->id}", [
            'title' => 'About Us',
            'is_published' => true,
            'cta' => ['heading' => ' Visit {business_name} today ', 'text' => '', 'button' => 'See new arrivals', 'link' => '/categories/new'],
        ])->assertSessionHasNoErrors();

        $this->get("/admin/pages/{$about->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('page.cta.button', 'See new arrivals')->where('page.cta.text', '')->where('ctaDefaults', Page::ABOUT_CTA));

        $this->get('/about')->assertInertia(fn (Assert $page) => $page
            ->where('cta.heading', fn ($h) => str_starts_with($h, 'Visit ') && ! str_contains($h, '{business_name}'))
            ->where('cta.text', Page::ABOUT_CTA['text']) // empty field: standard wording
            ->where('cta.button', 'See new arrivals')
            ->where('cta.link', '/categories/new'));

        foreach (['javascript:alert(1)', '//evil.example', 'products'] as $bad) {
            $this->put("/admin/pages/{$about->id}", ['title' => 'About Us', 'cta' => ['link' => $bad]])->assertSessionHasErrors('cta.link');
        }
        $this->put("/admin/pages/{$about->id}", ['title' => 'About Us', 'cta' => ['link' => 'https://wa.me/2348000000000']])->assertSessionHasNoErrors();
    }

    public function test_client_sites_start_without_made_up_numbers(): void
    {
        $this->get('/about')->assertInertia(fn (Assert $page) => $page->where('stats', []));
    }

    public function test_validation(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();
        $this->actingAs(User::factory()->create())->put("/admin/pages/{$about->id}", [
            'title' => 'About Us',
            'stats' => array_fill(0, 4, ['value' => '1', 'label' => 'x']), // the layout has room for three
        ])->assertSessionHasErrors('stats');
        $this->put("/admin/pages/{$about->id}", ['title' => 'About Us', 'stats' => [['value' => '', 'label' => 'x']]])->assertSessionHasErrors('stats.0.value');
    }
}
