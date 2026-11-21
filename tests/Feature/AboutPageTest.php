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

    public function test_numbers_and_founders_note_are_edited_and_shown(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();

        $this->actingAs(User::factory()->create())->put("/admin/pages/{$about->id}", [
            'title' => 'About Us',
            'content' => 'Our story.',
            'is_published' => true,
            'stats' => [['value' => ' 8+ ', 'label' => 'Years in business'], ['value' => '12,000+', 'label' => 'Happy customers']],
            'quote' => ['text' => 'Welcome to {business_name}.', 'name' => 'Ada Okafor', 'role' => 'Founder'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('8+', $about->fresh()->extras['stats'][0]['value']);

        $this->get('/about')->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/About')
            ->has('stats', 2)
            ->where('stats.1.label', 'Happy customers')
            ->where('quote.name', 'Ada Okafor')
            ->where('quote.text', fn ($t) => ! str_contains($t, '{business_name}')));
    }

    public function test_client_sites_start_without_made_up_numbers(): void
    {
        $this->get('/about')->assertInertia(fn (Assert $page) => $page->where('stats', [])->where('quote', null));
    }

    public function test_validation(): void
    {
        $about = Page::query()->where('slug', 'about')->firstOrFail();
        $this->actingAs(User::factory()->create())->put("/admin/pages/{$about->id}", [
            'title' => 'About Us',
            'stats' => array_fill(0, 5, ['value' => '1', 'label' => 'x']),
        ])->assertSessionHasErrors('stats');
        $this->put("/admin/pages/{$about->id}", ['title' => 'About Us', 'stats' => [['value' => '', 'label' => 'x']]])->assertSessionHasErrors('stats.0.value');
    }
}
