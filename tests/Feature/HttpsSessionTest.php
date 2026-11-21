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

    public function test_no_redirect_when_store_address_is_http(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('http://localhost/admin/login')->assertOk();
    }
}
