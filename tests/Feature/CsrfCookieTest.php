<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CSRF cookie has a name of its own, so another site on the same parent
 * domain setting a domain-wide "XSRF-TOKEN" can't break saving or logging out.
 */
class CsrfCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_csrf_cookie_is_named_after_the_app(): void
    {
        config(['session.cookie' => 'ecommerce-demo-session']);
        $this->assertSame('ecommerce-demo-xsrf', ValidateCsrfToken::cookieName());

        $response = $this->get('/admin/login');
        $response->assertCookie('ecommerce-demo-xsrf');
        $response->assertCookieMissing('XSRF-TOKEN');
        $this->assertStringContainsString('<meta name="xsrf-cookie" content="ecommerce-demo-xsrf">', $response->getContent());
    }
}
