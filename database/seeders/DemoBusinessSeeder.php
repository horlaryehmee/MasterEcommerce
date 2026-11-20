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
