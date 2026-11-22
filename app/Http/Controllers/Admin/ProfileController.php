<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $pending = filled($user->two_factor_secret) && ! $user->hasTwoFactor();

        return Inertia::render('Admin/Profile', [
            'pendingEmail' => $request->session()->get('profile.email_change.email'),
            'profile' => $user->only('name', 'email'),
            'twoFactor' => [
                'enabled' => $user->hasTwoFactor(),
                'confirmed_at' => $user->two_factor_confirmed_at?->format('j M Y'),
                'recovery_left' => count($user->two_factor_recovery_codes ?? []),
                // Only while setting up: once confirmed, the secret is never shown again.
                'setup' => $pending ? [
                    'secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')),
                    'uri' => Totp::uri($user->two_factor_secret, $user->email, BusinessSetting::current()->business_name ?: config('app.name')),
                ] : null,
            ],
            // Shown once, right after they are made.
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
            'push' => [
                'publicKey' => app(\App\Services\WebPush::class)->publicKey(),
                'devices' => $user->pushSubscriptions()->active()->latest()->get()->map(fn ($d) => [
                    'id' => $d->id,
                    'device' => $d->device,
                    'endpoint_hash' => $d->endpoint_hash,
                    'added' => $d->created_at?->format('j M Y'),
                    'last_used' => $d->last_used_at?->diffForHumans(),
                ]),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users')->ignore($user->id)],
        ]);

        if ($data['email'] !== $user->email) {
            $this->confirmPassword($request);
            $code = (string) random_int(10000000, 99999999);
            try {
                Mail::raw("Your email change verification code is {$code}. It expires in 10 minutes.", fn ($mail) => $mail->to($data['email'])->subject('Verify your administrator email'));
            } catch (\Throwable $e) {
                report($e);
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'We couldn’t send the verification code to that address. Check that your website can send emails (Website Settings → Email → Send a test email), then try again.']);
            }
            $request->session()->put('profile.email_change', [
                'user_id' => $user->id, 'previous' => $user->email, 'email' => $data['email'],
                'hash' => Hash::make($code), 'expires' => now()->addMinutes(10)->timestamp,
            ]);
            $user->update(['name' => $data['name']]);

            return back()->with('success', 'Check your new address for the verification code.');
        }
        $user->update(['name' => $data['name']]);

        return back()->with('success', 'Profile updated.');
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:8']]);
        $change = $request->session()->get('profile.email_change');
        $user = $request->user();
        if (! $change || $change['user_id'] !== $user->id || $change['previous'] !== $user->email
            || $change['expires'] < now()->timestamp || ! Hash::check($data['code'], $change['hash'])) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired verification code.']);
        }
        $previous = $user->email;
        $user->forceFill(['email' => $change['email'], 'email_verified_at' => now()])->save();
        $request->session()->forget('profile.email_change');

        // Tell the old address (if this wasn't you, you'll know). A mail failure doesn't undo the verified change.
        try {
            Mail::raw("The administrator login email for your website was changed from {$previous} to {$change['email']}. If you didn't do this, contact the person who manages your website or hosting straight away.", fn ($mail) => $mail->to($previous)->subject('Your administrator email was changed'));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Your administrator email has been updated.');
    }

    /** Step 1: a new secret for the authenticator app (not active until confirmed). */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        $request->user()->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back()->with('success', 'Scan the QR code with your authenticator app, then enter the code it shows.');
    }

    /** Step 2: prove the app works; two-factor sign-in is then on. */
    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();
        if (! filled($user->two_factor_secret) || $user->hasTwoFactor()) {
            return back();
        }
        if (! $user->verifyTwoFactorCode($request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'That code isn’t right. Check the time on your phone is set automatically, then try the newest code.']);
        }

        $codes = User::newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();

        return back()->with('success', 'Two-factor sign-in is on.')->with('two_factor_recovery_codes', $codes);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        abort_unless($request->user()->hasTwoFactor(), 404);
        $codes = User::newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return back()->with('success', 'New recovery codes made. The old ones no longer work.')->with('two_factor_recovery_codes', $codes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $wasOn = $request->user()->hasTwoFactor();
        // Cancelling an unfinished setup doesn't need the password; turning protection off does.
        if ($wasOn) {
            $this->confirmPassword($request);
        }
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back()->with('success', $wasOn ? 'Two-factor sign-in is off.' : 'Setup cancelled.');
    }

    protected function confirmPassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'current_password']], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $request->user()->update(['password' => $request->password]);

        // Admin routes use the "auth.session" middleware, so other browsers still
        // holding the old password hash are signed out on their next request.
        $request->session()->regenerate();

        return back()->with('success', 'Password changed.');
    }
}
