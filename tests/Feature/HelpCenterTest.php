<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_help_center(): void
    {
        $this->get('/admin/help')->assertRedirect('/admin/login');

        $this->actingAsAdmin();
        $this->get('/admin/help')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Help/Index'));
        $this->get('/admin/help?guide=add-product')->assertOk();
    }

    public function test_every_screenshot_used_by_a_guide_exists(): void
    {
        $guides = file_get_contents(resource_path('js/Pages/Admin/Help/guides.js'));
        preg_match_all("/img: '([a-z0-9-]+)'/", $guides, $m);

        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $name) {
            $this->assertFileExists(public_path("help/{$name}.webp"), "Missing Help screenshot: {$name}");
        }

        // Guide ids are unique (they are the page addresses).
        preg_match_all("/^\s{16}id: '([a-z0-9-]+)'/m", $guides, $ids);
        $this->assertSame(count($ids[1]), count(array_unique($ids[1])));
    }
}
