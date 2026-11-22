<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Files the browser fetches in the background (service worker, manifests, icons…)
 * must never become the "previous page": a form saved afterwards used to redirect
 * back to /sw.js and show the script instead of the admin page.
 */
class BackgroundFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_save_after_background_fetches_returns_to_the_admin_page(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/reviews')->assertOk();

        // What the browser does by itself while the page is open.
        $this->get('/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->get('/admin/manifest.webmanifest')->assertOk();
        $this->get('/manifest.webmanifest')->assertOk();
        $this->get('/pwa/icon/admin.svg')->assertOk();

        // A form that redirects "back" lands on the admin page, not on a background file.
        $this->put('/admin/reviews/settings', ['reviews_enabled' => '1', 'reviews_home_enabled' => '1', 'reviews_auto_publish' => 'manual'])
            ->assertRedirect('/admin/reviews');
    }

    public function test_downloads_and_error_pages_never_become_the_previous_page(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/reviews')->assertOk();

        // A CSV download (opens as a file) and a missing page, after the admin page.
        $this->get('/admin/orders/export')->assertOk()->assertDownload();
        $this->get('/admin/products/999999/edit')->assertNotFound();

        $this->put('/admin/reviews/settings', ['reviews_enabled' => '1', 'reviews_home_enabled' => '1', 'reviews_auto_publish' => 'manual'])
            ->assertRedirect('/admin/reviews');
    }

    public function test_the_browser_tells_this_site_where_a_form_came_from(): void
    {
        $this->actingAsAdmin();
        // Admin and order pages: never shown to other websites, but sent to this one.
        $this->get('/admin/reviews')->assertHeader('Referrer-Policy', 'same-origin');

        // With a Referer, "back" uses it even if a stray file was fetched in between.
        $this->get('/sw.js');
        $this->from('/admin/coupons')->put('/admin/reviews/settings', ['reviews_enabled' => '1', 'reviews_home_enabled' => '1', 'reviews_auto_publish' => 'manual'])
            ->assertRedirect('/admin/coupons');
    }

    public function test_background_files_do_not_start_a_session(): void
    {
        $response = $this->get('/sw.js')->assertOk();
        $cookies = collect($response->headers->getCookies())->map->getName();
        $this->assertFalse($cookies->contains(config('session.cookie')), 'No session cookie for the service worker.');
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/offline')->assertOk();
    }

    public function test_update_page_buttons_return_to_the_update_page(): void
    {
        $this->actingAsAdmin();
        $this->get('/sw.js');
        $this->post('/admin/updates/online')->assertRedirect('/admin/updates');
    }
}
