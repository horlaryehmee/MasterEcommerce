<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Auth/Login'));
    }

    public function test_guests_are_redirected_from_every_admin_page(): void
    {
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/categories', '/admin/content', '/admin/settings', '/admin/profile'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
        $this->post('/admin/products', ['name' => 'X'])->assertRedirect('/admin/login');
        $this->put('/admin/settings/business', ['business_name' => 'X'])->assertRedirect('/admin/login');
    }

    public function test_admin_can_log_in_and_out(): void
    {
        $user = $this->admin();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')->has('stats'));

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->admin();
        $this->from('/admin/login')->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $response = $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_guessing_one_account_from_many_addresses_is_also_limited(): void
    {
        $user = $this->admin();

        // 20 wrong passwords spread over 20 addresses (each one stays under its own limit).
        foreach (range(1, 20) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])
                ->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-'.$i]);
        }

        // Even the right password from a new address now waits.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->admin();

        $this->post('/admin/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token, $user) {
            $token = $n->token;
            // The emailed link points at the admin reset page.
            $this->assertStringContainsString('/admin/reset-password/', $n->toMail($user)->actionUrl);

            return true;
        });

        $this->get("/admin/reset-password/{$token}?email={$user->email}")->assertOk();

        $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect('/admin/login');

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_reset_link_request_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();
        $this->post('/admin/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_admin_can_update_profile_and_change_password(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $input = ['name' => 'Owner', 'email' => 'new@example.com'];
        $this->put('/admin/profile', $input)->assertSessionHasErrors('current_password');
        $this->put('/admin/profile', $input + ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('owner@example.com', $user->fresh()->email);
        $this->post('/admin/profile/verify-email', ['code' => '00000000'])->assertSessionHasErrors('code');
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        preg_match('/verification code is (\d{8})/', $messages->last()->getOriginalMessage()->getTextBody(), $match);
        $this->post('/admin/profile/verify-email', ['code' => $match[1]])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);

        $this->put('/admin/profile/password', ['current_password' => 'wrong', 'password' => 'Another123', 'password_confirmation' => 'Another123'])
            ->assertSessionHasErrors('current_password');

        $this->put('/admin/profile/password', ['current_password' => 'password', 'password' => 'Another123', 'password_confirmation' => 'Another123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Another123', $user->fresh()->password));
    }

    public function test_update_source_requires_recent_password_and_credentials_are_not_flashed(): void
    {
        $this->actingAsAdmin();
        $this->put('/admin/updates/source', ['repository' => 'some/store'])->assertRedirect(route('admin.password.confirm'));
        $this->post('/admin/confirm-password', ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->put('/admin/updates/source', ['token' => str_repeat('x', 501)])->assertSessionHasErrors('token')
            ->assertSessionMissing('_old_input.token');
    }

    public function test_setup_routes_require_owner_verification(): void
    {
        config(['catalog.setup_token' => str_repeat('a', 64)]);
        $this->get('/install/database')->assertForbidden();
        $this->post('/install/unlock', ['setup_token' => 'incorrect'])->assertSessionHasErrors('setup_token');
        $this->post('/install/unlock', ['setup_token' => config('catalog.setup_token')])->assertRedirect(route('install.welcome'));
        $this->get('/install/database')->assertOk();
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_create_admin_command_creates_and_replaces_the_single_admin(): void
    {
        $this->artisan('catalog:create-admin', ['--name' => 'Ada', '--email' => 'ada@example.com', '--password' => 'Secret123'])->assertSuccessful();
        $this->assertSame(1, User::count());

        $this->artisan('catalog:create-admin', ['--name' => 'Bola', '--email' => 'bola@example.com', '--password' => 'Secret456', '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertSame('bola@example.com', User::first()->email);

        $this->artisan('catalog:create-admin', ['--name' => 'X', '--email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    }
}
