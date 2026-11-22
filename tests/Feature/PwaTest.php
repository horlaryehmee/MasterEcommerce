<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::current()->update(['business_name' => 'Kettle Co', 'primary_color' => '#C2410C']);
        BusinessSetting::forgetResolved();
    }

    protected function withFavicon(): void
    {
        $im = imagecreatetruecolor(300, 300);
        ob_start();
        imagewebp($im);
        Storage::disk('public')->put('branding/favicon.webp', ob_get_clean());
        BusinessSetting::current()->update(['favicon_path' => 'branding/favicon.webp']);
        BusinessSetting::forgetResolved();
    }

    public function test_shop_and_admin_install_as_two_separate_apps(): void
    {
        $shop = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $shop->assertJsonPath('name', 'Kettle Co')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', url('/'))
            ->assertJsonPath('theme_color', '#C2410C');

        $admin = $this->get('/admin/manifest.webmanifest')->assertOk();
        $admin->assertJsonPath('name', 'Kettle Co Admin')
            ->assertJsonPath('short_name', 'Admin')
            ->assertJsonPath('start_url', url('/admin'))
            ->assertJsonPath('scope', url('/admin'));
        $this->assertNotSame($shop->json('id'), $admin->json('id'));
    }

    public function test_pages_link_the_right_manifest(): void
    {
        $this->get('/')->assertSee('<link rel="manifest" href="'.url('/manifest.webmanifest').'">', false);
        $this->get('/admin/login')->assertSee('<link rel="manifest" href="'.url('/admin/manifest.webmanifest').'">', false);

        $this->actingAsAdmin();
        $this->get('/admin')->assertSee(url('/admin/manifest.webmanifest'), false);
    }

    public function test_icons_are_made_from_the_favicon_in_every_size(): void
    {
        $this->withFavicon();

        $icons = collect($this->get('/manifest.webmanifest')->json('icons'));
        $this->assertSame(['192x192', '512x512'], $icons->pluck('sizes')->unique()->values()->all());
        $this->assertSame(['any', 'maskable'], $icons->pluck('purpose')->unique()->sort()->values()->all());

        $admin = collect($this->get('/admin/manifest.webmanifest')->json('icons'));
        // Different icon for the admin app (dark background).
        $this->assertEmpty($icons->pluck('src')->intersect($admin->pluck('src')));

        foreach ($icons->merge($admin) as $icon) {
            $path = 'pwa/'.basename($icon['src']);
            Storage::disk('public')->assertExists($path);
            [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }

        $this->get('/')->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_without_an_image_the_initial_is_used(): void
    {
        $this->get('/manifest.webmanifest')->assertJsonPath('icons.0.type', 'image/svg+xml');
        $this->get('/pwa/icon/shop.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('>K</text>', false);
        $this->get('/pwa/icon/admin.svg')->assertOk();
        $this->get('/pwa/icon/other.svg')->assertNotFound();
    }

    public function test_service_worker_never_caches_pages_or_private_data(): void
    {
        $sw = $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/');
        $this->assertStringStartsWith('application/javascript', $sw->headers->get('Content-Type'));
        $js = $sw->getContent();

        // Only the hashed build files and the offline page are cached.
        $this->assertStringContainsString("const ASSETS = '/build/assets/'", $js);
        $this->assertStringContainsString("const OFFLINE = '/offline'", $js);
        $this->assertStringContainsString("if (request.method !== 'GET') return;", $js);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE))', $js);
        $this->assertSame(2, substr_count($js, 'cache.put(') + substr_count($js, 'cache.add('));
    }

    public function test_offline_page(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You’re offline')->assertSee('Kettle Co');
    }
}
