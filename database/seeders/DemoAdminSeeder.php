<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo administrator for LOCAL DEVELOPMENT ONLY.
 *
 * Never runs in production and never runs when an administrator already
 * exists. For real installations use:  php artisan catalog:create-admin
 */
class DemoAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@example.com';

    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Skipping demo admin (not a local environment). Run: php artisan catalog:create-admin');

            return;
        }

        if (User::query()->exists()) {
            return;
        }

        User::query()->create([
            'name' => 'Demo Admin',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);

        $this->command?->warn('Demo admin created for local use: '.self::EMAIL.' / '.self::PASSWORD);
    }
}
