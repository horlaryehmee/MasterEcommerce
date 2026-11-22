<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Create the administrator account (or replace its login details).
 * Each installation has one primary administrator.
 */
class CreateAdmin extends Command
{
    protected $signature = 'catalog:create-admin
        {--name= : Administrator name}
        {--email= : Login email address}
        {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create the administrator account or reset its login details';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Administrator name', default: 'Administrator', required: true);
        $email = $this->option('email') ?: text('Login email address', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.');
        $plain = $this->option('password') ?: password('Password (min. 8 characters, letters and numbers)', required: true);

        $validator = Validator::make(compact('name', 'email') + ['password' => $plain], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        $existing = User::query()->orderBy('id')->first();

        if (! $user && $existing) {
            $replace = $this->option('no-interaction')
                || confirm("An administrator already exists ({$existing->email}). Replace its login details with these?", default: true);
            if (! $replace) {
                $this->warn('Nothing changed.');

                return self::SUCCESS;
            }
            $user = $existing;
        }

        $user ??= new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Administrator ready. Log in at ".url('/admin')." with {$email}.");

        return self::SUCCESS;
    }
}
