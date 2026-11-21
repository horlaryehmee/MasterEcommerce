<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\Themes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function asOwner(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_storefront_uses_the_essential_template_by_default(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('theme.key', 'essential')
            ->where('theme.header', 'classic')
            ->where('theme.preview', null)
            ->where('order', Themes::ALL['essential']['layout']['home']));
    }

    public function test_admin_sees_every_template_with_preview_links(): void
    {
        $this->asOwner()->get('/admin/templates')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Templates/Index')
            ->where('current', 'essential')
            ->has('templates', count(Themes::ALL))
            ->where('templates.1.key', 'atelier')
            ->where('templates.1.preview_url', fn ($url) => str_contains($url, '/preview/template/atelier') && str_contains($url, 'signature=')));
    }

    public function test_applying_a_template_with_and_without_its_style(): void
    {
        $this->asOwner()->put('/admin/templates', ['theme' => 'glow', 'with_style' => false])->assertSessionHasNoErrors();
        $s = BusinessSetting::current()->fresh();
        $this->assertSame('glow', $s->theme);
        $this->assertSame('#111827', $s->primary_color, 'Brand colours kept.');

        $this->asOwner()->put('/admin/templates', ['theme' => 'atelier', 'with_style' => true])->assertSessionHasNoErrors();
        $s = BusinessSetting::current()->fresh();
        $this->assertSame('atelier', $s->theme);
        $this->assertSame('Cormorant Garamond', $s->heading_font);
        $this->assertSame('portrait', $s->product_card_style);
        $this->assertSame('square', $s->button_shape);
        BusinessSetting::forgetResolved();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('theme.key', 'atelier')
            ->where('theme.header', 'centered')
            ->where('theme.categories', 'editorial')
            ->where('store.card_style', 'portrait'));

        $this->asOwner()->put('/admin/templates', ['theme' => 'nope'])->assertSessionHasErrors('theme');
    }

    public function test_a_signed_preview_link_shows_the_template_without_saving_anything(): void
    {
        $this->get(Themes::previewUrl('volt'))->assertRedirect('/');

        $html = $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('theme.key', 'volt')
            ->where('theme.header', 'marketplace')
            ->where('theme.preview.name', 'Volt')
            ->where('theme.preview.can_apply', false)
            ->where('store.card_style', 'boxed'))->getContent();
        // The preview shows the template's own colours and fonts.
        $this->assertStringContainsString('--brand-primary: #0F172A', $html);
        $this->assertStringContainsString('family=Manrope', $html);

        $this->assertSame('essential', BusinessSetting::current()->fresh()->theme);

        $this->get('/preview/exit')->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('theme.key', 'essential')->where('theme.preview', null));
    }

    public function test_preview_links_cannot_be_forged(): void
    {
        $this->get('/preview/template/volt')->assertForbidden();
        $this->get(Themes::previewUrl('volt').'x')->assertForbidden();
        $this->get('/?template_preview=volt&embed=1')->assertInertia(fn (Assert $page) => $page->where('theme.key', 'essential'));

        // The owner can preview without a signature.
        $this->asOwner()->get('/preview/template/bloom')->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('theme.key', 'bloom')->where('theme.preview.can_apply', true));
    }

    public function test_thumbnails_preview_for_one_request_only(): void
    {
        $this->get(Themes::thumbnailUrl('glow'))->assertInertia(fn (Assert $page) => $page
            ->where('theme.key', 'glow')
            ->where('theme.preview', null));

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('theme.key', 'essential'));
    }

    public function test_every_template_renders_the_homepage_and_a_product_page(): void
    {
        $product = \App\Models\Product::factory()->create();
        foreach (array_keys(Themes::ALL) as $key) {
            BusinessSetting::current()->update(['theme' => $key]);
            BusinessSetting::forgetResolved();
            $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('theme.key', $key));
            $this->get($product->url())->assertOk();
        }
    }
}
