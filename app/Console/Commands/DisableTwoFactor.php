<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Last resort when the admin has lost both their phone and recovery codes.
 * Needs server access (SSH or cPanel Terminal), so it can't be used remotely.
 */
class DisableTwoFactor extends Command
{
    protected $signature = 'admin:disable-2fa {email : The admin’s login email}';

    protected $description = 'Turn off two-factor sign-in for an admin who lost their phone and recovery codes';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No admin with that email.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $this->info("Two-factor sign-in is off for {$user->email}. Log in with the password, then turn it on again in Profile.");

        return self::SUCCESS;
    }
}
