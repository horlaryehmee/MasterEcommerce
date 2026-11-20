<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\Concerns\ImportsDemoImages;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    use ImportsDemoImages;

    /**
     * slug => product data. "days" = how many days ago it was added
     * (newest products appear first under "Latest" and "New Arrivals").
     */
    public static function products(): array
    {
        return [
            'aurora-x5-smartphone-128gb' => [
                'image' => 'aurora-x5-smartphone', 'category' => 'smartphones', 'days' => 1,
                'name' => 'Aurora X5 Smartphone 128GB',
                'price' => 385000, 'compare_at_price' => 420000, 'availability' => 'in_stock', 'featured' => true, 'new' => true,
                'short' => 'A sleek 6.5" AMOLED smartphone with a 50MP triple camera, 128GB storage and all-day battery life.',
                'description' => "The Aurora X5 combines a vivid edge-to-edge display with a fast, efficient processor, so everything from video calls to gaming feels smooth.\n\n## Key features\n\n- 6.5\" FHD+ AMOLED display, 120Hz refresh rate\n- 50MP main camera with ultra-wide and macro lenses\n- 128GB storage, 8GB RAM\n- 5,000mAh battery with 33W fast charging\n- Dual SIM, 5G ready\n- In-display fingerprint sensor\n\n## In the box\n\nPhone, USB-C charging cable, 33W adapter, protective case and SIM tool.",
            ],
            'vertex-lite-5g-smartphone-64gb' => [
                'image' => 'vertex-lite-5g-smartphone', 'category' => 'smartphones', 'days' => 4,
                'name' => 'Vertex Lite 5G Smartphone 64GB',
                'price' => 189000, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => true,
                'short' => 'Affordable 5G performance in a lightweight matte sage finish, with a 48MP camera and 64GB storage.',
                'description' => "Fast 5G connectivity doesn't have to be expensive. The Vertex Lite delivers dependable everyday performance in a slim, comfortable design.\n\n## Key features\n\n- 6.4\" HD+ display\n- 48MP main camera, 8MP selfie camera\n- 64GB storage (expandable with microSD), 4GB RAM\n- 4,500mAh battery\n- Dual SIM, 5G\n- Side-mounted fingerprint sensor",
            ],
            'nimbus-book-14-laptop' => [
                'image' => 'nimbus-book-14-laptop', 'category' => 'laptops', 'days' => 9,
                'name' => 'Nimbus Book 14 Laptop – Core i5, 16GB RAM, 512GB SSD',
                'price' => 685000, 'compare_at_price' => 750000, 'availability' => 'in_stock', 'featured' => true, 'new' => false,
                'short' => 'A light 14" laptop for work and study, with a fast SSD, 16GB memory and up to 10 hours of battery.',
                'description' => "Weighing just 1.3kg, the Nimbus Book 14 goes wherever you do. A crisp Full HD display and backlit keyboard make long working sessions comfortable.\n\n## Specifications\n\n- 14\" Full HD IPS anti-glare display\n- Intel Core i5 processor (12th generation)\n- 16GB DDR4 RAM, 512GB NVMe SSD\n- Backlit keyboard, fingerprint reader\n- Wi-Fi 6, Bluetooth 5.2, USB-C, 2× USB-A, HDMI\n- Up to 10 hours battery life\n- Windows 11 Home",
            ],
            'strata-pro-16-creator-laptop' => [
                'image' => 'strata-pro-16-laptop', 'category' => 'laptops', 'days' => 16,
                'name' => 'Strata Pro 16 Creator Laptop',
                'price' => 1450000, 'compare_at_price' => null, 'availability' => 'limited', 'featured' => false, 'new' => false,
                'short' => 'A powerful 16" laptop for designers, editors and power users, with dedicated graphics and a colour-accurate display.',
                'description' => "Built for demanding creative work, the Strata Pro 16 pairs a high-performance processor with dedicated graphics and a 100% sRGB display.\n\n## Specifications\n\n- 16\" 2.5K display, 100% sRGB, 165Hz\n- Intel Core i7 processor\n- 32GB RAM, 1TB NVMe SSD\n- Dedicated 8GB graphics card\n- Thunderbolt 4, HDMI 2.1, SD card reader\n- Aluminium chassis, backlit keyboard\n- Windows 11 Pro",
            ],
            'pulse-anc-wireless-headphones' => [
                'image' => 'pulse-anc-wireless-headphones', 'category' => 'electronics', 'days' => 2,
                'name' => 'Pulse ANC Wireless Headphones',
                'price' => 78500, 'compare_at_price' => 95000, 'availability' => 'in_stock', 'featured' => true, 'new' => true,
                'short' => 'Over-ear Bluetooth headphones with active noise cancellation, plush cushions and 40-hour battery life.',
                'description' => "Shut out the noise and enjoy rich, detailed sound. The Pulse ANC headphones are designed for long listening sessions, travel and focused work.\n\n## Key features\n\n- Active noise cancellation with transparency mode\n- 40mm drivers for deep, balanced sound\n- Up to 40 hours playback (30 hours with ANC)\n- Fast charge: 10 minutes for 5 hours of playback\n- Built-in microphones for clear calls\n- Foldable design with travel pouch",
            ],
            'orbit-mini-bluetooth-speaker' => [
                'image' => 'orbit-mini-bluetooth-speaker', 'category' => 'electronics', 'days' => 20,
                'name' => 'Orbit Mini Bluetooth Speaker',
                'price' => 32000, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'A compact, water-resistant speaker with 360° sound and up to 12 hours of playtime.',
                'description' => "Small enough to carry anywhere, loud enough to fill the room. The Orbit Mini is perfect for the beach, the kitchen or your next get-together.\n\n- 360° sound with passive bass radiator\n- IPX6 water and dust resistance\n- Up to 12 hours playtime\n- Bluetooth 5.3, pair two speakers for stereo\n- Built-in carry strap",
            ],
            'halo-43-inch-4k-smart-tv' => [
                'image' => 'halo-43-inch-4k-smart-tv', 'category' => 'electronics', 'days' => 12,
                'name' => 'Halo 43" 4K UHD Smart TV',
                'price' => 365000, 'compare_at_price' => 399000, 'availability' => 'in_stock', 'featured' => true, 'new' => false,
                'short' => 'Stunning 4K picture with HDR, built-in streaming apps and a slim, frameless design.',
                'description' => "Bring movies, sport and shows to life with sharp 4K resolution and vivid HDR colour.\n\n## Key features\n\n- 43\" 4K Ultra HD (3840 × 2160) display\n- HDR10 support\n- Smart TV with popular streaming apps\n- Screen mirroring from your phone\n- 3× HDMI, 2× USB, Wi-Fi and Ethernet\n- Wall-mountable (VESA 200 × 200)\n- Includes remote and table stand",
            ],
            'classic-leather-chelsea-boots' => [
                'image' => 'classic-leather-chelsea-boots', 'category' => 'fashion', 'days' => 3,
                'name' => 'Classic Leather Chelsea Boots',
                'price' => 58000, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => true,
                'short' => 'Handcrafted genuine leather Chelsea boots with elastic side panels and a durable rubber sole.',
                'description' => "A wardrobe essential that pairs effortlessly with jeans, chinos or tailored trousers.\n\n- Genuine full-grain leather upper\n- Elastic side gussets and rear pull tab for easy wear\n- Cushioned insole for all-day comfort\n- Durable, slip-resistant rubber sole\n- Available in sizes 40–46 (ask for your size on WhatsApp)",
            ],
            'relaxed-fit-linen-shirt' => [
                'image' => 'relaxed-fit-linen-shirt', 'category' => 'fashion', 'days' => 18,
                'name' => 'Relaxed-Fit Linen Shirt',
                'price' => 24500, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'A breathable short-sleeve linen shirt in natural oatmeal — light, cool and effortlessly smart.',
                'description' => "Made from soft, breathable linen, this relaxed-fit shirt keeps you cool in warm weather while looking put-together.\n\n- 100% linen\n- Relaxed fit with chest pocket\n- Short sleeves, classic collar\n- Sizes S – XXL\n- Machine washable",
            ],
            'ankara-print-wrap-dress' => [
                'image' => 'ankara-print-wrap-dress', 'category' => 'fashion', 'days' => 5,
                'name' => 'Ankara Print Wrap Dress',
                'price' => 38000, 'compare_at_price' => 45000, 'availability' => 'in_stock', 'featured' => true, 'new' => true,
                'short' => 'A vibrant, flattering wrap dress in bold Ankara print with a tie waist and flowing midi skirt.',
                'description' => "Celebrate colour and craftsmanship in this beautifully tailored wrap dress, made from premium cotton Ankara fabric.\n\n- 100% cotton wax print fabric\n- Wrap-style bodice with adjustable tie waist\n- Flowing midi-length skirt\n- Sizes 8 – 18; custom sizing available on request\n- Hand wash or gentle machine wash",
            ],
            'breeze-1-7l-electric-kettle' => [
                'image' => 'breeze-electric-kettle', 'category' => 'home-appliances', 'days' => 22,
                'name' => 'Breeze 1.7L Stainless Steel Electric Kettle',
                'price' => 21500, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'A fast-boiling 1.7 litre stainless steel kettle with a water window and automatic shut-off.',
                'description' => "Boil water for tea, coffee and cooking in minutes.\n\n- 1.7 litre capacity\n- 2200W rapid boil\n- Brushed stainless steel body\n- Illuminated water level window\n- Automatic shut-off and boil-dry protection\n- 360° cordless base",
            ],
            'problend-1000w-blender' => [
                'image' => 'problend-1000w-blender', 'category' => 'home-appliances', 'days' => 7,
                'name' => 'ProBlend 1000W Blender',
                'price' => 54000, 'compare_at_price' => 62000, 'availability' => 'in_stock', 'featured' => true, 'new' => false,
                'short' => 'A powerful 1000W blender with a 1.5L jar for smoothies, soups, pepper mixes and more.',
                'description' => "From morning smoothies to blending pepper for stew, the ProBlend handles it all with ease.\n\n- 1000W motor with stainless steel blades\n- 1.5 litre BPA-free jar\n- Variable speed dial plus pulse function\n- Ice-crushing capability\n- Non-slip base\n- Dishwasher-safe jar and lid",
            ],
            'coolwave-18-inch-standing-fan' => [
                'image' => 'coolwave-18-inch-standing-fan', 'category' => 'home-appliances', 'days' => 25,
                'name' => 'Coolwave 18" Standing Fan',
                'price' => 46500, 'compare_at_price' => null, 'availability' => 'out_of_stock', 'featured' => false, 'new' => false,
                'short' => 'A quiet, energy-efficient 18" standing fan with three speeds, oscillation and adjustable height.',
                'description' => "Stay cool with strong, steady airflow.\n\n- 18\" blades with wide-angle oscillation\n- 3 speed settings\n- Adjustable height and tilt\n- Energy-efficient copper motor\n- Sturdy round base\n\n*Currently out of stock — message us on WhatsApp to be notified when it's back.*",
            ],
            'chrono-steel-analogue-watch' => [
                'image' => 'chrono-steel-analogue-watch', 'category' => 'accessories', 'days' => 6,
                'name' => 'Chrono Steel Analogue Watch',
                'price' => 72000, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => true, 'new' => true,
                'short' => 'A refined analogue watch with a deep navy dial, stainless steel case and genuine leather strap.',
                'description' => "Classic style for the office, weekends and special occasions.\n\n- 42mm stainless steel case\n- Navy sunburst dial with date window\n- Scratch-resistant mineral glass\n- Japanese quartz movement\n- Genuine brown leather strap\n- Water resistant to 50m",
            ],
            'powergo-20000mah-power-bank' => [
                'image' => 'powergo-20000mah-power-bank', 'category' => 'accessories', 'days' => 8,
                'name' => 'PowerGo 20,000mAh Power Bank',
                'price' => 26000, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => true,
                'short' => 'High-capacity fast-charging power bank that charges a phone up to 4 times. USB-C and USB-A.',
                'description' => "Never run out of battery again — at home, at work or on the road.\n\n- 20,000mAh capacity\n- 22.5W fast charging\n- USB-C (input/output) and USB-A ports\n- Charge two devices at once\n- LED battery level indicator\n- Includes USB-C cable",
            ],
            'solace-polarised-sunglasses' => [
                'image' => 'solace-polarised-sunglasses', 'category' => 'accessories', 'days' => 27,
                'name' => 'Solace Polarised Sunglasses',
                'price' => 18500, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'Tortoiseshell sunglasses with polarised UV400 lenses that cut glare and protect your eyes.',
                'description' => "Timeless frames with modern lens protection.\n\n- Polarised lenses reduce glare\n- UV400 protection\n- Lightweight tortoiseshell acetate frame\n- Comes with protective case and cleaning cloth",
            ],
            'glow-vitamin-c-face-serum-30ml' => [
                'image' => 'glow-vitamin-c-face-serum', 'category' => 'beauty-and-personal-care', 'days' => 2,
                'name' => 'Glow Vitamin C Face Serum 30ml',
                'price' => 15500, 'compare_at_price' => 18000, 'availability' => 'in_stock', 'featured' => true, 'new' => true,
                'short' => 'A brightening vitamin C serum with hyaluronic acid to even skin tone and boost radiance.',
                'description' => "Reveal brighter, smoother-looking skin with this lightweight daily serum.\n\n- 15% vitamin C for a brighter, more even complexion\n- Hyaluronic acid for lasting hydration\n- Suitable for all skin types\n- Fragrance-free, fast absorbing\n\n## How to use\n\nApply 3–4 drops to clean skin every morning before moisturiser. Always follow with sunscreen.",
            ],
            'shea-butter-body-lotion-400ml' => [
                'image' => 'shea-butter-body-lotion', 'category' => 'beauty-and-personal-care', 'days' => 14,
                'name' => 'Shea Butter Body Lotion 400ml',
                'price' => 9800, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'A rich, non-greasy body lotion made with natural shea butter for soft, deeply moisturised skin.',
                'description' => "Nourish dry skin with the goodness of natural shea butter.\n\n- Enriched with unrefined shea butter and vitamin E\n- 24-hour moisture\n- Absorbs quickly without a greasy feel\n- Suitable for the whole family\n- Convenient 400ml pump bottle",
            ],
            'sage-ceramic-table-lamp' => [
                'image' => 'sage-ceramic-table-lamp', 'category' => 'home-and-living', 'days' => 3,
                'name' => 'Sage Ceramic Table Lamp',
                'price' => 42000, 'compare_at_price' => null, 'availability' => 'on_request', 'featured' => false, 'new' => true,
                'short' => 'A handcrafted sage-green ceramic lamp with a linen drum shade that casts a warm, inviting glow.',
                'description' => "Add soft, ambient light to your bedroom, living room or office.\n\n- Glazed ceramic base in sage green\n- Natural linen drum shade\n- E27 bulb holder (warm LED bulb included)\n- Inline on/off switch, 1.8m cable\n- Height: 52cm\n\n*Made to order — please allow 5–7 days.*",
            ],
            'amber-soy-candle-set' => [
                'image' => 'amber-soy-candle-set', 'category' => 'home-and-living', 'days' => 11,
                'name' => 'Amber Glass Soy Candle Set (3 Pieces)',
                'price' => 19500, 'compare_at_price' => null, 'availability' => 'in_stock', 'featured' => false, 'new' => false,
                'short' => 'Three hand-poured soy wax candles in amber glass jars — vanilla, sandalwood and fresh linen.',
                'description' => "Create a calm, cosy atmosphere with clean-burning natural soy candles.\n\n- Set of 3: vanilla, sandalwood and fresh linen\n- 100% soy wax with cotton wicks\n- Up to 40 hours burn time each\n- Reusable amber glass jars\n- Gift-ready packaging",
            ],
        ];
    }

    public function run(): void
    {
        $categories = Category::query()->pluck('id', 'slug');

        foreach (self::products() as $slug => $p) {
            if (Product::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $createdAt = now()->subDays($p['days'])->setTime(10, 0);

            $product = new Product([
                'name' => $p['name'],
                'slug' => $slug,
                'short_description' => $p['short'],
                'description' => $p['description'],
                'price' => $p['price'],
                'compare_at_price' => $p['compare_at_price'],
                'category_id' => $categories[$p['category']] ?? null,
                'availability' => $p['availability'],
                'is_featured' => $p['featured'],
                'is_new_arrival' => $p['new'],
                'status' => Product::STATUS_PUBLISHED,
                'published_at' => $createdAt,
                'seo_title' => $p['name'],
                'seo_description' => $p['short'],
            ]);
            $product->created_at = $createdAt;
            $product->updated_at = $createdAt;
            $product->save();

            $files = glob(database_path('seeders/demo-images/products/'.$p['image'].'-*.webp')) ?: [];
            sort($files);
            foreach (array_values($files) as $i => $file) {
                $path = $this->importImage('products/'.basename($file), 'products');
                if ($path) {
                    $product->images()->create([
                        'path' => $path,
                        'alt' => $p['name'],
                        'sort_order' => $i,
                        'is_primary' => $i === 0,
                    ]);
                }
            }
        }
    }
}
