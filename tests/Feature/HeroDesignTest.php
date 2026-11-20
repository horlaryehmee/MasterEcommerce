<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HeroDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
    }

    protected function slide(array $attrs = []): HeroSlide
    {
        return HeroSlide::query()->create($attrs + [
            'desktop_image_path' => 'banners/d'.uniqid().'.webp',
            'heading' => 'Slide',
            'content_mode' => 'text',
            'text_position' => 'left',
            'text_theme' => 'light',
            'is_active' => true,
        ]);
    }

    protected function heroSettings(array $overrides = []): array
    {
        return $overrides + [
            'hero_enabled' => '1',
            'hero_type_desktop' => 'split',
            'hero_type_mobile' => 'split',
            'hero_width' => 'boxed',
            'hero_text_align' => 'left',
            'hero_autoplay' => '1',
            'hero_interval' => 6,
            'hero_desktop_height' => 'standard',
            'hero_mobile_height' => 'portrait',
            'hero_mobile_text' => 'below',
            'hero_heading' => 'Welcome',
        ];
    }

    /* ---------------- Storefront ---------------- */

    public function test_default_split_hero(): void
    {
        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('hero.desktop', 'split')
            ->where('hero.mobile', 'split')
            ->where('hero.content.heading', 'Welcome to our store')
            ->where('hero.slides', []));
    }

    public function test_different_designs_per_device_with_active_slides_in_order(): void
    {
        HomepageSetting::current()->update(['hero_type_desktop' => 'slider', 'hero_type_mobile' => 'banner', 'hero_image_path' => 'banners/hero.webp', 'hero_mobile_text' => 'overlay']);
        $second = $this->slide(['heading' => 'Second', 'sort_order' => 2]);
        $first = $this->slide(['heading' => 'First', 'sort_order' => 1, 'mobile_image_path' => 'banners/m.webp']);
        $this->slide(['heading' => 'Hidden', 'is_active' => false]);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('hero.desktop', 'slider')
            ->where('hero.mobile', 'banner')
            ->has('hero.slides', 2)
            ->where('hero.slides.0.heading', 'First')
            ->where('hero.slides.0.mobile_image.src', fn ($src) => str_contains($src, 'banners/m.webp'))
            ->where('hero.slides.1.heading', 'Second')
            ->where('hero.slides.1.mobile_image', null)
            ->where('hero.slider.autoplay', true)
            ->where('hero.slider.mobile_text', 'overlay'));
    }

    public function test_image_only_slides_hide_text_and_keep_their_link(): void
    {
        HomepageSetting::current()->update(['hero_type_desktop' => 'slider', 'hero_type_mobile' => 'slider']);
        $this->slide(['content_mode' => 'image', 'heading' => 'Ignored', 'button_text' => 'Go', 'button_link' => '/x', 'link' => '/categories/sale']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('hero.slides.0.image_only', true)
            ->where('hero.slides.0.heading', null)
            ->where('hero.slides.0.primary', null)
            ->where('hero.slides.0.link', '/categories/sale'));
    }

    public function test_designs_fall_back_gracefully(): void
    {
        $home = HomepageSetting::current();

        // Slider chosen but no slides: shows the split hero.
        $home->update(['hero_type_desktop' => 'slider', 'hero_type_mobile' => 'slider']);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero.desktop', 'split')->where('hero.mobile', 'split'));

        // Banner without an image: split.
        $home->update(['hero_type_desktop' => 'banner', 'hero_image_path' => null]);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero.desktop', 'split'));

        // No heading but slides exist: the slider is used everywhere.
        $home->update(['hero_heading' => null, 'hero_type_desktop' => 'split', 'hero_type_mobile' => 'split']);
        $this->slide();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero.desktop', 'slider')->where('hero.mobile', 'slider')->where('hero.content', null)->has('hero.slides', 1));

        // Nothing to show at all: hidden.
        HeroSlide::query()->delete();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero', null));
    }

    /* ---------------- Admin: design settings ---------------- */

    public function test_admin_can_choose_designs_per_device(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/content?tab=hero')->assertInertia(fn (Assert $p) => $p->has('heroOptions.types', 3)->has('slides', 0));

        $this->put('/admin/content/hero', $this->heroSettings([
            'hero_type_desktop' => 'slider',
            'hero_type_mobile' => 'banner',
            'hero_width' => 'full',
            'hero_autoplay' => '0',
            'hero_interval' => 8,
            'hero_mobile_height' => 'square',
            'hero_mobile_text' => 'overlay',
            'hero_mobile_image' => UploadedFile::fake()->image('phone.jpg', 1080, 1350),
        ]))->assertSessionHasNoErrors();

        $home = HomepageSetting::query()->first();
        $this->assertSame(['slider', 'banner', 'full', false, 8, 'square', 'overlay'], [
            $home->hero_type_desktop, $home->hero_type_mobile, $home->hero_width, $home->hero_autoplay,
            $home->hero_interval, $home->hero_mobile_height, $home->hero_mobile_text,
        ]);
        Storage::disk('public')->assertExists($home->hero_mobile_image_path);
    }

    public function test_hero_settings_are_validated(): void
    {
        $this->actingAsAdmin();

        $this->put('/admin/content/hero', $this->heroSettings(['hero_type_desktop' => 'carousel3d', 'hero_interval' => 1, 'hero_mobile_text' => 'sideways']))
            ->assertSessionHasErrors(['hero_type_desktop', 'hero_interval', 'hero_mobile_text']);

        // The heading is required only when a device uses the split or banner design...
        $this->put('/admin/content/hero', $this->heroSettings(['hero_heading' => '', 'hero_type_mobile' => 'banner', 'hero_type_desktop' => 'slider']))
            ->assertSessionHasErrors('hero_heading');

        // ...not when both devices use the slider.
        $this->put('/admin/content/hero', $this->heroSettings(['hero_heading' => '', 'hero_type_mobile' => 'slider', 'hero_type_desktop' => 'slider']))
            ->assertSessionHasNoErrors();
    }

    public function test_partial_hero_update_keeps_saved_design(): void
    {
        $this->actingAsAdmin();
        HomepageSetting::current()->update(['hero_type_desktop' => 'slider', 'hero_type_mobile' => 'slider', 'hero_autoplay' => true]);

        $this->put('/admin/content/hero', ['hero_enabled' => '1', 'hero_heading' => ''])->assertSessionHasNoErrors();

        $home = HomepageSetting::query()->first();
        $this->assertSame('slider', $home->hero_type_desktop);
        $this->assertTrue($home->hero_autoplay);
    }

    /* ---------------- Admin: slides ---------------- */

    public function test_admin_can_add_edit_and_delete_slides(): void
    {
        $this->actingAsAdmin();
        $disk = Storage::disk('public');

        $this->post('/admin/hero-slides', [
            'content_mode' => 'text', 'heading' => 'Big sale', 'button_text' => 'Shop', 'button_link' => '/products',
            'text_position' => 'center', 'text_theme' => 'dark', 'is_active' => '1',
            'desktop_image' => UploadedFile::fake()->image('wide.jpg', 1920, 800),
            'mobile_image' => UploadedFile::fake()->image('tall.jpg', 1080, 1350),
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '2 images optimised'));

        $slide = HeroSlide::query()->firstOrFail();
        $disk->assertExists([$slide->desktop_image_path, $slide->mobile_image_path]);
        $oldDesktop = $slide->desktop_image_path;
        $oldMobile = $slide->mobile_image_path;

        // Replace the desktop image and remove the phone image.
        $this->put("/admin/hero-slides/{$slide->id}", [
            'content_mode' => 'image', 'link' => 'whatsapp', 'text_position' => 'left', 'text_theme' => 'light', 'is_active' => '0',
            'desktop_image' => UploadedFile::fake()->image('new.jpg', 1920, 800),
            'remove_mobile_image' => '1',
        ])->assertSessionHasNoErrors();

        $slide->refresh();
        $this->assertSame('image', $slide->content_mode);
        $this->assertFalse($slide->is_active);
        $this->assertNull($slide->mobile_image_path);
        $disk->assertMissing([$oldDesktop, $oldMobile]);
        $disk->assertExists($slide->desktop_image_path);

        $path = $slide->desktop_image_path;
        $this->delete("/admin/hero-slides/{$slide->id}")->assertRedirect();
        $this->assertModelMissing($slide);
        $disk->assertMissing($path);
    }

    public function test_slide_validation(): void
    {
        $this->actingAsAdmin();
        $base = ['text_position' => 'left', 'text_theme' => 'light'];

        // Desktop image is required; text slides need a heading.
        $this->post('/admin/hero-slides', $base + ['content_mode' => 'text'])->assertSessionHasErrors(['desktop_image', 'heading']);
        // Image-only slides don't need a heading.
        $this->post('/admin/hero-slides', $base + ['content_mode' => 'image', 'desktop_image' => UploadedFile::fake()->image('a.jpg', 800, 400)])->assertSessionHasNoErrors();
        // Unsafe links and bad options are rejected.
        $this->post('/admin/hero-slides', [
            'content_mode' => 'text', 'heading' => 'x', 'button_text' => 'Go', 'button_link' => 'javascript:alert(1)',
            'text_position' => 'top', 'text_theme' => 'neon', 'desktop_image' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHasErrors(['button_link', 'text_position', 'text_theme']);
        $this->post('/admin/hero-slides', $base + ['content_mode' => 'image', 'desktop_image' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])
            ->assertSessionHasErrors('desktop_image');
    }

    public function test_slides_can_be_reordered_and_are_limited(): void
    {
        $this->actingAsAdmin();
        $a = $this->slide(['sort_order' => 0]);
        $b = $this->slide(['sort_order' => 1]);

        $this->post('/admin/hero-slides/reorder', ['ids' => [$b->id, $a->id]])->assertRedirect();
        $this->assertSame([$b->id, $a->id], HeroSlide::query()->ordered()->pluck('id')->all());

        for ($i = 0; $i < 6; $i++) {
            $this->slide();
        }
        $this->post('/admin/hero-slides', ['content_mode' => 'image', 'text_position' => 'left', 'text_theme' => 'light', 'desktop_image' => UploadedFile::fake()->image('a.jpg')])
            ->assertSessionHasErrors('desktop_image');
        $this->assertSame(8, HeroSlide::query()->count());
    }

    public function test_guests_cannot_manage_slides(): void
    {
        $slide = $this->slide();
        $this->post('/admin/hero-slides', [])->assertRedirect('/admin/login');
        $this->put("/admin/hero-slides/{$slide->id}", [])->assertRedirect('/admin/login');
        $this->delete("/admin/hero-slides/{$slide->id}")->assertRedirect('/admin/login');
    }
}
