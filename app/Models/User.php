<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public const RECOVERY_CODES = 8;

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Two-factor sign-in is set up and confirmed. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** Fresh one-time recovery codes, e.g. "K7Q2M-9XHTR". */
    public static function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(5).'-'.\Illuminate\Support\Str::random(5)))
            ->all();
    }

    /**
     * Checks a 6-digit app code (each code works once) or a recovery code
     * (removed once used). Atomic, so two requests can't both use one code.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (! filled($this->two_factor_secret)) {
            return false;
        }

        $step = \App\Support\Totp::verify($this->two_factor_secret, $code);
        if ($step !== null) {
            $claimed = static::query()->whereKey($this->id)
                ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
                ->update(['two_factor_last_step' => $step]);
            $this->two_factor_last_step = $step;

            return $claimed === 1;
        }

        return $this->useRecoveryCode($code);
    }

    protected function useRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        if (! preg_match('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $code)) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($code) {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->id);
            $codes = $fresh->two_factor_recovery_codes ?? [];
            $match = collect($codes)->first(fn ($c) => hash_equals($c, $code));
            if (! $match) {
                return false;
            }
            $fresh->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$match]))])->save();
            $this->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }
}
