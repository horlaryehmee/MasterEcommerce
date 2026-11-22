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

    public function run(): void
    {
        if (DemoSeeder::$confirmed || ! app()->environment('local', 'testing')) {
            $this->command?->warn('Skipping demo admin (not a local environment). Run: php artisan catalog:create-admin');

            return;
        }

        if (User::query()->exists()) {
            return;
        }

        User::query()->create([
            'name' => 'Demo Admin',
            'email' => self::EMAIL,
            'password' => \Illuminate\Support\Str::password(40),
            'email_verified_at' => now(),
        ]);

        $this->command?->warn('Set a local administrator password with php artisan catalog:create-admin.');
    }
}
