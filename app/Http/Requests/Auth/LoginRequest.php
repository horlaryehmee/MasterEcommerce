<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per email + IP before a temporary lockout. */
    protected const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Checks the email and password WITHOUT signing in: accounts with two-factor
     * sign-in still need their code (AuthController decides).
     */
    public function authenticate(): \App\Models\User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $credentials['email']]);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($this->throttleKey(), 60);
            RateLimiter::hit($this->accountKey(), 300);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountKey());
        $provider->rehashPasswordIfRequired($user, $credentials);

        return $user;
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS) && ! RateLimiter::tooManyAttempts($this->accountKey(), 20)) {
            return;
        }

        event(new Lockout($this));
        $seconds = max(RateLimiter::availableIn($this->throttleKey()), RateLimiter::availableIn($this->accountKey()));

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function accountKey(): string
    {
        return 'login-account:'.hash('sha256', Str::lower(trim((string) $this->input('email'))));
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
