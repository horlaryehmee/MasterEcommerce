<?php

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

class DemoCategorySeeder extends Seeder
{
    use ImportsDemoImages;

    /** slug => [name, description, featured on homepage] */
    public static function categories(): array
    {
        return [
            'smartphones' => ['Smartphones', 'The latest smartphones with brilliant displays, long-lasting batteries and capable cameras — for every budget.', true],
            'laptops' => ['Laptops', 'Reliable laptops for work, study and creative projects, from lightweight everyday machines to powerful performers.', true],
            'electronics' => ['Electronics', 'Smart TVs, headphones, speakers and everyday electronics to upgrade your home and lifestyle.', true],
            'fashion' => ['Fashion', 'Timeless clothing, footwear and statement pieces designed for comfort and style.', true],
            'home-appliances' => ['Home Appliances', 'Dependable kitchen and home appliances that make everyday tasks quicker and easier.', true],
            'accessories' => ['Accessories', 'Watches, eyewear, power banks and the finishing touches that complete your look and setup.', true],
            'beauty-and-personal-care' => ['Beauty and Personal Care', 'Skincare and body care essentials made with nourishing ingredients for healthy, glowing skin.', true],
            'home-and-living' => ['Home and Living', 'Lighting, candles and décor that bring warmth and personality to any space.', true],
        ];
    }

    /** Category slug => bundled image file. */
    protected const IMAGES = [
        'smartphones' => 'smartphones',
        'laptops' => 'laptops',
        'electronics' => 'electronics',
        'fashion' => 'fashion',
        'home-appliances' => 'home-appliances',
        'accessories' => 'accessories',
        'beauty-and-personal-care' => 'beauty',
        'home-and-living' => 'home-living',
    ];

    public function run(): void
    {
        $order = 1;
        foreach (self::categories() as $slug => [$name, $description, $featured]) {
            if (Category::query()->where('slug', $slug)->exists()) {
                $order++;

                continue;
            }

            Category::query()->create([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'image_path' => $this->importImage('categories/'.self::IMAGES[$slug].'.webp', 'categories'),
                'sort_order' => $order++,
                'is_published' => true,
                'is_featured' => $featured,
                'seo_title' => $name.' — Shop Online',
                'seo_description' => $description,
            ]);
        }
    }
}
