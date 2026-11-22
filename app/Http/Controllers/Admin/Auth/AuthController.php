<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /** Minutes a correct password stays valid while waiting for the two-factor code. */
    public const TWO_FACTOR_WINDOW = 10;

    /** Wrong codes allowed per account within 15 minutes, before the sign-in is cancelled. */
    public const TWO_FACTOR_ATTEMPTS = 5;

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();
        $request->session()->regenerate();

        $request->session()->passwordConfirmed();

        if ($user->hasTwoFactor()) {
            // Not signed in yet: remember who passed the password step, briefly.
            $request->session()->put('login.two_factor', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(self::TWO_FACTOR_WINDOW)->getTimestamp(),
            ]);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function showTwoFactorChallenge(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingTwoFactorUser($request)) {
            return redirect()->route('admin.login')->with('status', 'Please log in again.');
        }

        return Inertia::render('Admin/Auth/TwoFactorChallenge');
    }

    public function twoFactorChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingTwoFactorUser($request);
        if (! $user) {
            return redirect()->route('admin.login')->with('status', 'That took too long. Please log in again.');
        }

        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::TWO_FACTOR_ATTEMPTS)) {
            $request->session()->forget('login.two_factor');

            return redirect()->route('admin.login')->with('status', 'Too many wrong codes. Please wait '.ceil(RateLimiter::availableIn($key) / 60).' minutes and log in again.');
        }

        if (! $user->verifyTwoFactorCode((string) $request->input('code'))) {
            RateLimiter::hit($key, 15 * 60);
            Log::notice('Wrong two-factor code', ['user' => $user->id, 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['code' => 'That code isn’t right. Enter the current 6-digit code from your app, or one of your recovery codes.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) ($request->session()->get('login.two_factor')['remember'] ?? false);
        $request->session()->forget('login.two_factor');
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $left = count($user->fresh()->two_factor_recovery_codes ?? []);
        $redirect = redirect()->intended(route('admin.dashboard'));

        return $left <= 2
            ? $redirect->with('error', "You have {$left} recovery ".($left === 1 ? 'code' : 'codes').' left. Make new ones in Profile → Two-factor sign-in.')
            : $redirect;
    }

    protected function pendingTwoFactorUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.two_factor');
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('login.two_factor');

            return null;
        }
        $user = User::query()->find($pending['id'] ?? 0);

        return $user?->hasTwoFactor() ? $user : null;
    }

    public function confirmPassword(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $request->session()->passwordConfirmed();

        $destination = $request->session()->pull('url.intended', route('admin.dashboard'));
        $request->session()->flash('success', 'Password confirmed. You can continue.');

        return $request->header('X-Inertia') ? Inertia::location($destination) : redirect()->to($destination);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function showForgotPassword(Request $request): Response
    {
        return Inertia::render('Admin/Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:160']]);

        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email exists, so accounts cannot be discovered.
        return back()->with('status', 'If that email address belongs to the administrator, a password reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Admin/Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => $request->password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('admin.login')->with('status', 'Your password has been reset. You can now log in.');
    }
}
