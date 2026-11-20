<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

/** Banner slider for the demo, with separate desktop and phone images. */
class DemoHeroSlideSeeder extends Seeder
{
    use ImportsDemoImages;

    public function run(): void
    {
        // Never touch slides that already exist.
        if (HeroSlide::query()->exists()) {
            return;
        }

        $slides = [
            [
                'image' => 'tech',
                'eyebrow' => 'New season tech',
                'heading' => 'Upgrade your everyday tech',
                'description' => 'Smartphones, laptops and audio from trusted makers — at prices that make sense.',
                'button_text' => 'Shop electronics', 'button_link' => '/categories/electronics',
                'secondary_text' => 'Chat on WhatsApp', 'secondary_link' => 'whatsapp',
                'text_theme' => 'light', 'text_position' => 'left',
            ],
            [
                'image' => 'home',
                'eyebrow' => 'Home essentials',
                'heading' => 'Make home life easier',
                'description' => 'Blenders, kettles, fans and lighting picked for quality and value.',
                'button_text' => 'Shop home appliances', 'button_link' => '/categories/home-appliances',
                'text_theme' => 'dark', 'text_position' => 'left',
            ],
            [
                'image' => 'style',
                'eyebrow' => 'Style & beauty',
                'heading' => 'Look good, feel great',
                'description' => 'Statement fashion and skincare favourites, ready when you are.',
                'button_text' => 'Shop fashion', 'button_link' => '/categories/fashion',
                'secondary_text' => 'Beauty picks', 'secondary_link' => '/categories/beauty-and-personal-care',
                'text_theme' => 'dark', 'text_position' => 'left',
            ],
        ];

        foreach ($slides as $order => $s) {
            $desktop = $this->importImage("slides/slide-{$s['image']}-desktop.webp", 'banners');
            if (! $desktop) {
                continue;
            }

            HeroSlide::query()->create([
                'desktop_image_path' => $desktop,
                'mobile_image_path' => $this->importImage("slides/slide-{$s['image']}-mobile.webp", 'banners'),
                'content_mode' => 'text',
                'sort_order' => $order,
                'is_active' => true,
            ] + collect($s)->except('image')->all());
        }

        // Show off the slider on both desktop and phones (only when the demo just created the slides).
        HomepageSetting::current()->update([
            'hero_type_desktop' => 'slider',
            'hero_type_mobile' => 'slider',
            'hero_width' => 'full',
            'hero_desktop_height' => 'standard',
            'hero_mobile_height' => 'portrait',
        ]);
    }
}
