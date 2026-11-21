<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpsSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_visit_is_sent_to_https_when_store_uses_https(): void
    {
        config(['app.url' => 'https://shop.example.com']);

        $this->get('http://shop.example.com/admin/login?a=1')
            ->assertStatus(301)
            ->assertRedirect('https://shop.example.com/admin/login?a=1');
    }

    public function test_https_behind_a_proxy_is_recognised_and_not_redirected(): void
    {
        config(['app.url' => 'https://shop.example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://shop.example.com/admin/login')
            ->assertOk();
    }

    public function test_forwarded_client_ip_is_not_trusted(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('/admin/login');

        $this->assertSame('10.0.0.5', request()->ip());
    }

    public function test_expired_session_on_a_save_redirects_with_303_so_the_browser_does_not_repeat_the_put(): void
    {
        // A 302 would make the browser repeat the PUT on the settings page: "405 Method Not Allowed".
        $this->actingAsAdmin();
        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->app->instance('env', 'production'); // CSRF is skipped when running unit tests

        $response = $this->from('/admin/settings?tab=email')
            ->withHeaders(['X-Inertia' => 'true', 'X-XSRF-TOKEN' => 'expired'])
            ->put('/admin/settings/email', ['mail_mailer' => 'sendmail']);

        $response->assertStatus(303)->assertRedirect('/admin/settings?tab=email')
            ->assertSessionHas('error', 'Your session expired. Please try again.');
    }

    public function test_no_redirect_when_store_address_is_http(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('http://localhost/admin/login')->assertOk();
    }
}
