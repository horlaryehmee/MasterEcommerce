<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` only runs the essential setup (settings rows and the
 * standard pages). It never adds demo content and never overwrites data.
 *
 * Demo content is opt-in:  php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EssentialSeeder::class);
    }
}
