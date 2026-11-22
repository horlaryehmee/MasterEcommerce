<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithTwoFactor(): User
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD', 'EEEEE-FFFFF', 'GGGGG-HHHHH'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    /** A valid code for the next 30-second step, which the "same code twice" rule hasn't seen. */
    protected function code(User $user, int $offset = 0): string
    {
        return Totp::now($user->fresh()->two_factor_secret, time() + $offset);
    }

    public function test_totp_matches_the_rfc_6238_reference_values(): void
    {
        // RFC 6238 appendix B, SHA-1 secret "12345678901234567890" (last 6 digits).
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::now($secret, 59));
        $this->assertSame('081804', Totp::now($secret, 1111111109));
        $this->assertSame('050471', Totp::now($secret, 1111111111));
        $this->assertSame('005924', Totp::now($secret, 1234567890));
        $this->assertSame('279037', Totp::now($secret, 2000000000));
        $this->assertNull(Totp::verify($secret, '000000', 1, 59));
        $this->assertSame(1, Totp::verify($secret, '287082', 1, 59));
    }

    public function test_password_alone_does_not_sign_in_when_two_factor_is_on(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin/two-factor-challenge');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->post('/admin/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/admin/two-factor-challenge', ['code' => $this->code($user)])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $user = $this->userWithTwoFactor();
        $code = $this->code($user);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/admin/two-factor-challenge', ['code' => $code])->assertRedirect('/admin');
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/admin/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_work_once(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/admin/two-factor-challenge', ['code' => 'ccccc-ddddd'])->assertRedirect('/admin');
        $this->assertNotContains('CCCCC-DDDDD', $user->fresh()->two_factor_recovery_codes);
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/admin/two-factor-challenge', ['code' => 'CCCCC-DDDDD'])->assertSessionHasErrors('code');
    }

    public function test_too_many_wrong_codes_cancel_the_sign_in(): void
    {
        $user = $this->userWithTwoFactor();
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);

        foreach (range(1, 5) as $i) {
            $this->post('/admin/two-factor-challenge', ['code' => '000000']);
        }
        // Even the right code is refused now; the password step must be repeated later.
        $this->post('/admin/two-factor-challenge', ['code' => $this->code($user)])->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_the_challenge_expires(): void
    {
        $user = $this->userWithTwoFactor();
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password']);

        $this->travel(11)->minutes();
        $this->post('/admin/two-factor-challenge', ['code' => $this->code($user)])->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_challenge_page_needs_a_password_first(): void
    {
        $this->get('/admin/two-factor-challenge')->assertRedirect('/admin/login');
        $this->post('/admin/two-factor-challenge', ['code' => '123456'])->assertRedirect('/admin/login');
    }

    public function test_admin_turns_two_factor_on_and_off(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/admin/profile/two-factor', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->post('/admin/profile/two-factor', ['current_password' => 'password'])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactor());
        $this->get('/admin/profile')->assertInertia(fn (Assert $p) => $p->where('twoFactor.enabled', false)->whereNot('twoFactor.setup', null));

        $this->post('/admin/profile/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/admin/profile/two-factor/confirm', ['code' => $this->code($user)])->assertSessionHas('two_factor_recovery_codes');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertCount(8, $user->two_factor_recovery_codes);
        // The secret is encrypted at rest and no longer shown once confirmed.
        $this->assertStringNotContainsString($user->two_factor_secret, (string) \DB::table('users')->value('two_factor_secret'));
        $this->get('/admin/profile')->assertInertia(fn (Assert $p) => $p->where('twoFactor.enabled', true)->where('twoFactor.setup', null));

        $this->delete('/admin/profile/two-factor', [])->assertSessionHasErrors('current_password');
        $this->delete('/admin/profile/two-factor', ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    public function test_accounts_without_two_factor_still_log_in_normally(): void
    {
        $user = User::factory()->create();
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }
}
