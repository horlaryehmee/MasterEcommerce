<?php

namespace Database\Seeders;

use App\Models\HomepageSetting;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

class DemoHomepageSeeder extends Seeder
{
    use ImportsDemoImages;

    public function run(): void
    {
        $home = HomepageSetting::current();

        // Only populate a homepage that hasn't been customised (no hero image yet).
        if ($home->hero_image_path) {
            return;
        }

        $home->update([
            'announcement_enabled' => true,
            'announcement_text' => 'New arrivals every week — chat with us on WhatsApp for fast enquiries.',
            'announcement_link' => '/products',
            'announcement_bg_color' => '#111827',
            'announcement_text_color' => '#FFFFFF',

            'hero_enabled' => true,
            'hero_eyebrow' => 'New season collection',
            'hero_heading' => 'Quality products for everyday living',
            'hero_description' => 'Discover smartphones, electronics, fashion and home essentials — carefully selected for style, convenience and value.',
            'hero_image_path' => $this->importImage('banners/hero.webp', 'banners'),
            'hero_primary_text' => 'Shop Now',
            'hero_primary_link' => '/products',
            'hero_secondary_text' => 'Chat on WhatsApp',
            'hero_secondary_link' => 'whatsapp',

            'categories_enabled' => true,
            'categories_heading' => 'Shop by Category',
            'categories_subheading' => 'Find exactly what you need across our carefully curated collections.',

            'featured_enabled' => true,
            'featured_heading' => 'Featured Products',
            'featured_subheading' => 'Hand-picked favourites our customers love.',

            'new_arrivals_enabled' => true,
            'new_arrivals_heading' => 'New Arrivals',
            'new_arrivals_subheading' => 'The latest additions to our store.',
            'new_arrivals_count' => 8,

            'promo_enabled' => true,
            'promo_eyebrow' => 'Home essentials',
            'promo_badge' => 'Up to 30% off',
            // A demo offer running for two weeks from installation (the countdown shows on the banner).
            'promo_ends_at' => now()->addDays(14)->endOfDay(),
            // Matches the backdrop of the demo photo, so the card and photo read as one scene.
            'promo_bg_color' => '#E9DCCD',
            'promo_heading' => 'Upgrade your home for less',
            'promo_description' => 'Save on blenders, kettles, fans and lighting. Limited stock available — enquire today to reserve yours.',
            'promo_image_path' => $this->importImage('banners/promo.webp', 'banners'),
            'promo_button_text' => 'Shop Home Appliances',
            'promo_button_link' => '/categories/home-appliances',

            'about_enabled' => true,
            'about_heading' => 'Shopping made simple',
            'about_body' => "At Nexa Stores, we believe great products should be easy to find and easy to buy. We carefully select every item in our store for quality, usefulness and value.\n\nBrowse our collection, then chat with us directly on WhatsApp. Our friendly team will answer your questions, confirm availability and help you get your order quickly.",
            'about_image_path' => $this->importImage('banners/about.webp', 'banners'),

            'why_enabled' => true,
            'why_heading' => 'Why Choose Us',
            'why_subheading' => 'We are committed to making every purchase a great experience.',
            'why_items' => [
                ['icon' => 'badge-check', 'title' => 'Quality Products', 'text' => 'Every item is carefully selected and checked before it reaches you.'],
                ['icon' => 'tag', 'title' => 'Affordable Prices', 'text' => 'Fair, transparent pricing with regular offers on popular items.'],
                ['icon' => 'headset', 'title' => 'Reliable Service', 'text' => 'A friendly team ready to help before and after your purchase.'],
                ['icon' => 'message-circle', 'title' => 'Easy Enquiries', 'text' => 'Chat with us instantly on WhatsApp — no forms, no waiting.'],
            ],
        ]);
    }
}
