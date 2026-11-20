<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\SocialLink;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

/** Branding, currency, WhatsApp, social links and SEO defaults for the demo. */
class DemoSettingsSeeder extends Seeder
{
    use ImportsDemoImages;

    public function run(): void
    {
        $settings = BusinessSetting::current();

        // Only fill settings that are still empty.
        $settings->update(array_filter([
            'whatsapp_number' => $settings->whatsapp_number ? null : '2348000000000', // fictional number
            'whatsapp_general_message' => $settings->whatsapp_general_message ?: BusinessSetting::DEFAULT_GENERAL_MESSAGE,
            'whatsapp_product_message' => $settings->whatsapp_product_message ?: BusinessSetting::DEFAULT_PRODUCT_MESSAGE,
            'seo_title' => $settings->seo_title ? null : 'Nexa Stores — Quality Electronics, Fashion & Home Essentials',
            'seo_description' => $settings->seo_description ? null : 'Shop smartphones, laptops, electronics, fashion, home appliances and beauty products at Nexa Stores. Browse online and enquire instantly on WhatsApp.',
            'og_image_path' => $settings->og_image_path ? null : $this->importImage('banners/og-image.webp', 'banners', 'jpg'),
        ]));

        // Brand colours and currency are left at the defaults (deep ink, warm stone, burnt orange; NGN).

        $links = [
            'instagram' => 'https://instagram.com/nexastores.example',
            'facebook' => 'https://facebook.com/nexastores.example',
            'tiktok' => 'https://tiktok.com/@nexastores.example',
            'x' => 'https://x.com/nexastores_example',
        ];

        $order = 0;
        foreach ($links as $platform => $url) {
            SocialLink::query()->firstOrCreate(['platform' => $platform], ['url' => $url, 'sort_order' => $order++]);
        }
    }
}
