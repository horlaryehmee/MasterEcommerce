<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\HomepageSetting;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Required application setup for every installation (demo or real client):
 * the settings rows and the standard informational pages.
 *
 * Safe to run any number of times: existing records are never modified.
 */
class EssentialSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::forgetResolved();
        HomepageSetting::forgetResolved();
        cache()->forget('settings.business');
        cache()->forget('settings.homepage');

        // current() creates the single settings rows with defaults if missing.
        BusinessSetting::current();
        HomepageSetting::current();

        foreach (Page::DEFAULTS as $slug => $title) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => DefaultPageContent::for($slug),
                'faq_items' => $slug === 'faq' ? DefaultPageContent::faqItems() : null,
                'is_published' => true,
            ]);
        }
    }
}
