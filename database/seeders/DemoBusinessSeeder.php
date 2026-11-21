<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

/** Business profile for the fictional demo business "Nexa Stores". */
class DemoBusinessSeeder extends Seeder
{
    use ImportsDemoImages;

    public const NAME = 'Nexa Stores';

    public function run(): void
    {
        $settings = BusinessSetting::current();

        // Only fill a fresh (default) profile — never overwrite edited details.
        if ($settings->business_name !== 'My Business') {
            return;
        }

        // About page: sample headline numbers and a founder's note (client sites start without them).
        \App\Models\Page::query()->where('slug', 'about')->whereNull('extras')->update(['extras' => json_encode([
            'stats' => [
                ['value' => '8+', 'label' => 'Years in business'],
                ['value' => '12,000+', 'label' => 'Happy customers'],
                ['value' => '24h', 'label' => 'Lagos delivery'],
                ['value' => '4.9★', 'label' => 'Average rating'],
            ],
            'quote' => [
                'text' => 'We started with one promise: sell only what we would happily buy ourselves, and answer every message like it came from a friend. That promise still guides every order.',
                'name' => 'Ada Okafor',
                'role' => 'Founder',
            ],
        ])]);

        $settings->update([
            'business_name' => self::NAME,
            'business_description' => 'Discover quality products, carefully selected to bring convenience, style and value to your everyday life.',
            'email' => 'hello@nexastores.example',
            'phone' => '+234 800 000 0000',
            'address' => '12 Admiralty Way, Lekki Phase 1, Lagos, Nigeria',
            'opening_hours' => "Monday – Friday: 9:00am – 6:00pm\nSaturday: 10:00am – 4:00pm\nSunday: Closed",
            'logo_path' => $settings->logo_path ?: $this->importImage('branding/logo.png', 'branding'),
            'favicon_path' => $settings->favicon_path ?: $this->importImage('branding/favicon.png', 'branding', 'png'),
            'footer_description' => 'Quality smartphones, electronics, fashion, home essentials and more — with friendly service and easy enquiries on WhatsApp.',
            'copyright_text' => '© {year} Nexa Stores. All rights reserved.',
        ]);
    }
}
