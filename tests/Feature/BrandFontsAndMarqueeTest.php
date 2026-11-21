<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\HomepageSetting;
use App\Models\User;
use App\Support\Fonts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandFontsAndMarqueeTest extends TestCase
{
    use RefreshDatabase;

    protected function asOwner(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    protected function branding(array $data): \Illuminate\Testing\TestResponse
    {
        $s = BusinessSetting::current();

        return $this->asOwner()->put('/admin/settings/branding', $data + [
            'primary_color' => $s->primary_color,
            'secondary_color' => $s->secondary_color,
            'accent_color' => $s->accent_color,
        ]);
    }

    public function test_built_in_font_needs_no_google_fonts(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('--brand-font-heading', $html);
    }

    public function test_owner_can_choose_title_and_body_fonts(): void
    {
        $this->branding(['heading_font' => 'Cormorant Garamond', 'body_font' => 'Lato', 'heading_weight' => 600, 'button_shape' => 'rounded'])
            ->assertSessionHasNoErrors();

        $s = BusinessSetting::current()->fresh();
        $this->assertSame('Cormorant Garamond', $s->heading_font);
        $this->assertSame(600, $s->heading_weight);
        BusinessSetting::forgetResolved();

        $html = $this->get('/')->getContent();
        // Only the weights each family has, or Google refuses the stylesheet.
        $this->assertStringContainsString('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&amp;family=Lato:wght@400;700;900&amp;display=swap', $html);
        $this->assertStringContainsString("--brand-font-heading: 'Cormorant Garamond', ui-serif", $html);
        $this->assertStringContainsString("--brand-font-body: 'Lato', ui-sans-serif", $html);
        $this->assertStringContainsString('--brand-heading-weight: 600', $html);
        $this->assertStringContainsString('--btn-radius: 0.5rem', $html);
    }

    public function test_fonts_are_not_applied_in_the_admin(): void
    {
        $this->branding(['heading_font' => 'Playfair Display', 'body_font' => 'Inter']);
        BusinessSetting::forgetResolved();

        $html = $this->asOwner()->get('/admin')->getContent();
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_any_google_font_can_be_typed_but_names_are_sanitised(): void
    {
        $this->branding(['heading_font' => '  Space   Grotesk ', 'body_font' => 'Plus Jakarta Sans'])->assertSessionHasNoErrors();
        $s = BusinessSetting::current()->fresh();
        $this->assertSame('Space Grotesk', $s->heading_font);
        $this->assertNull($s->body_font, 'Choosing the built-in font stores nothing.');
        $this->assertSame('https://fonts.googleapis.com/css2?family=Space+Grotesk&display=swap', Fonts::stylesheetUrl([$s->heading_font]));

        // Font names end up in CSS and a URL: nothing but letters, digits and spaces.
        foreach (["Inter'; } body { display:none", 'Lato</style><script>', 'Roboto&family=x'] as $bad) {
            $this->branding(['heading_font' => $bad])->assertSessionHasErrors('heading_font');
        }
        $this->branding(['heading_weight' => 950])->assertSessionHasErrors('heading_weight');
        $this->branding(['button_shape' => 'blob'])->assertSessionHasErrors('button_shape');
    }

    public function test_marquee_is_saved_and_shown_under_the_hero(): void
    {
        $this->asOwner()->put('/admin/content/marquee', [
            'marquee_enabled' => true,
            'marquee_items' => ['Open Mon - Sun', '  ', '30 - 60mins dispatch'],
            'marquee_separator' => '✱',
            'marquee_bg_color' => '#ffffff',
            'marquee_text_color' => '#111111',
            'marquee_accent_color' => '#e11d8f',
            'marquee_border' => true,
            'marquee_uppercase' => true,
            'marquee_speed' => 'slow',
        ])->assertSessionHasNoErrors();

        $home = HomepageSetting::current()->fresh();
        $this->assertSame(['Open Mon - Sun', '30 - 60mins dispatch'], $home->marquee_items);
        $this->assertSame('#E11D8F', $home->marquee_accent_color);
        HomepageSetting::forgetResolved();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('marquee.items', ['Open Mon - Sun', '30 - 60mins dispatch'])
            ->where('marquee.separator', '✱')
            ->where('marquee.accent', '#E11D8F')
            ->where('marquee.speed', 'slow')
            ->where('marquee.uppercase', true));
    }

    public function test_marquee_is_hidden_when_off_or_empty_and_validated(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('marquee', null));

        $this->asOwner()->put('/admin/content/marquee', ['marquee_enabled' => true, 'marquee_items' => [], 'marquee_speed' => 'normal'])->assertSessionHasNoErrors();
        HomepageSetting::forgetResolved();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('marquee', null));

        $this->asOwner()->put('/admin/content/marquee', ['marquee_items' => [str_repeat('x', 81)], 'marquee_speed' => 'warp', 'marquee_bg_color' => 'red'])
            ->assertSessionHasErrors(['marquee_items.0', 'marquee_speed', 'marquee_bg_color']);
    }

    public function test_hero_dots_and_shade_options(): void
    {
        $this->asOwner()->put('/admin/content/hero', [
            'hero_enabled' => true,
            'hero_heading' => 'Open for delivery seven days a week',
            'hero_dots_position' => 'left',
            'hero_shade' => 'none',
        ])->assertSessionHasNoErrors();
        HomepageSetting::forgetResolved();

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('hero.slider.dots', 'left')->where('hero.slider.shade', 'none'));

        $this->asOwner()->put('/admin/content/hero', ['hero_enabled' => true, 'hero_heading' => 'X', 'hero_shade' => 'heavy'])->assertSessionHasErrors('hero_shade');
    }
}
