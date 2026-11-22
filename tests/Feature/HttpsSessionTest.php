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
        config(['app.url' => 'https://shop.example.com', 'trustedproxy.proxies' => ['10.0.0.5']]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://shop.example.com/admin/login')
            ->assertOk();
    }

    public function test_without_configured_proxies_https_from_a_hosting_proxy_still_works(): void
    {
        // Typical cPanel + Cloudflare/LiteSpeed: HTTPS ends at the proxy. Must not redirect forever.
        config(['app.url' => 'https://shop.example.com', 'trustedproxy.proxies' => []]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('http://shop.example.com/admin/login')
            ->assertOk();

        // ...but the forwarded client IP is still ignored, so IP limits can't be dodged.
        $this->assertSame('172.70.1.1', request()->ip());
    }

    public function test_configured_proxies_give_the_real_visitor_ip(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.5']]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeader('X-Forwarded-For', '203.0.113.9')->get('/admin/login');
        $this->assertSame('203.0.113.9', request()->ip());
    }

    public function test_direct_http_requests_cannot_override_the_transport(): void
    {
        config(['app.url' => 'https://shop.example.com', 'trustedproxy.proxies' => ['10.0.0.5']]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://shop.example.com/admin/login')->assertRedirect('https://shop.example.com/admin/login');
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
