<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Populates the "Nexa Stores" demo website.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Safety rules:
 *  - Refuses to run if the database already contains a client's own products,
 *    categories or business details.
 *  - Every demo seeder only creates records that are missing; it never updates
 *    or deletes existing records, so running it twice is harmless.
 *  - In production it asks for confirmation (or set DEMO_SEED_ALLOW_PRODUCTION=true).
 */
class DemoSeeder extends Seeder
{
    /** Set by the setup wizard when the owner ticks "Add demo products" (that is the confirmation). */
    public static bool $confirmed = false;

    public function run(): void
    {
        if (app()->isProduction() && ! env('DEMO_SEED_ALLOW_PRODUCTION') && ! static::$confirmed) {
            if (! $this->command?->confirm('You are in PRODUCTION. Load the Nexa Stores demo content?', false)) {
                $this->command?->warn('Demo seeding cancelled.');

                return;
            }
        }

        if ($reason = $this->clientDataReason()) {
            $this->command?->error('Demo content was NOT loaded: '.$reason);
            $this->command?->line('The demo seeder never touches a real client\'s website.');

            return;
        }

        $this->call(EssentialSeeder::class);
        $this->call([
            DemoBusinessSeeder::class,
            DemoSettingsSeeder::class,
            DemoCategorySeeder::class,
            DemoProductSeeder::class,
            DemoHomepageSeeder::class,
            DemoHeroSlideSeeder::class,
            DemoAdminSeeder::class,
        ]);

        $this->command?->info('Nexa Stores demo content is ready.');
    }

    /** Why this database looks like a real client's site (null if it's empty or demo-only). */
    protected function clientDataReason(): ?string
    {
        $foreignProducts = Product::query()->whereNotIn('slug', array_keys(DemoProductSeeder::products()))->count();
        if ($foreignProducts > 0) {
            return "found {$foreignProducts} product(s) that are not part of the demo.";
        }

        $foreignCategories = Category::query()->whereNotIn('slug', array_keys(DemoCategorySeeder::categories()))->count();
        if ($foreignCategories > 0) {
            return "found {$foreignCategories} category(ies) that are not part of the demo.";
        }

        $name = BusinessSetting::query()->value('business_name');
        if ($name !== null && ! in_array($name, ['My Business', DemoBusinessSeeder::NAME], true)) {
            return "the business name is already set to \"{$name}\".";
        }

        return null;
    }
}
