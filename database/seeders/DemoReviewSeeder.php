<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewGuard;
use Illuminate\Database\Seeder;

/**
 * Customer reviews for the demo products: published ones (so product pages show
 * stars and the homepage shows "What our customers say") plus a few waiting in
 * Admin → Reviews. Products that already have reviews are left alone.
 */
class DemoReviewSeeder extends Seeder
{
    /**
     * slug => reviews: [rating, name, title, body, hours ago, verified, status?].
     * Status defaults to approved.
     */
    public static function reviews(): array
    {
        return [
            'aurora-x5-smartphone-128gb' => [
                [5, 'Chinedu Okafor', 'Best phone I have owned', "Screen is very bright and smooth, and the battery easily lasts me a full day with WhatsApp and Instagram. Delivery to Enugu took two days.", 14, true],
                [4, 'Aisha Bello', 'Great camera', 'Pictures in daylight are excellent. Night shots are good but not perfect. Fast charging is a big plus.', 20, false],
            ],
            'vertex-lite-5g-smartphone-64gb' => [
                [4, 'Tunde Adeyemi', 'Good value for money', 'Bought it for my mum. Light, simple to use and the 5G is fast in Lagos. Storage is a bit small but the memory card slot helps.', 60, true],
                [5, 'Grace Eze', 'Love the colour', 'The sage colour is beautiful in person and the phone feels solid. No complaints so far.', 80, false],
            ],
            'nimbus-book-14-laptop' => [
                [5, 'Ifeoma Nwosu', 'Perfect for school', 'Light enough to carry around campus all day and the battery lasts through my lectures. Boots up in seconds.', 150, true],
                [4, 'David Mensah', 'Solid work laptop', "Fast SSD and the keyboard is comfortable for long typing. I'd love a slightly brighter screen for outdoor use.", 190, false],
                [5, 'Kemi Alabi', 'Very happy', 'Arrived well packed with the charger and everything working. Customer service answered my questions on WhatsApp quickly.', 120, true],
            ],
            'strata-pro-16-creator-laptop' => [
                [5, 'Segun Bakare', 'A beast for video editing', 'Premiere Pro runs smoothly with 4K footage and the screen colours are accurate. Worth every naira.', 300, true],
                [4, 'Halima Yusuf', 'Powerful but heavy', 'Performance is amazing. It is a bit heavy to carry daily, but for a desk setup it is perfect.', 260, false],
            ],
            'pulse-anc-wireless-headphones' => [
                [5, 'Emeka Obi', 'Noise cancelling really works', 'I use them on the BRT every morning and the noise just disappears. Comfortable for hours too.', 30, true],
                [5, 'Funke Ade', 'Great sound', 'Deep bass without being muddy, and the battery lasts all week for me.', 40, false],
            ],
            'orbit-mini-bluetooth-speaker' => [
                [4, 'Bola Ogunleye', 'Small but loud', 'Surprisingly loud for its size. I take it to the pool and it survived a few splashes.', 400, false],
                [5, 'Yemi Johnson', 'Perfect gift', 'Bought two as birthday gifts and both people loved them. Pairs with the phone in seconds.', 350, true],
            ],
            'halo-43-inch-4k-smart-tv' => [
                [5, 'Ngozi Ibe', 'Picture quality is lovely', 'Netflix and YouTube look sharp in 4K and the setup took less than ten minutes.', 250, true],
                [4, 'Musa Abdullahi', 'Good TV, okay speakers', 'Great picture for the price. The speakers are fine for the news but I added a soundbar for movies.', 220, false],
                [3, 'Peter Okon', 'Decent', 'Picture is good but the remote feels cheap and the menus are a little slow to respond.', 200, false],
            ],
            'classic-leather-chelsea-boots' => [
                [5, 'Ibrahim Sule', 'Quality leather', 'Real leather, well stitched and comfortable from day one. I ordered my usual size and it fits perfectly.', 50, true],
            ],
            'relaxed-fit-linen-shirt' => [
                [5, 'Oluwaseun Ajayi', 'Perfect for the heat', 'Light, breathable and looks smart for church or work. Already ordered a second colour.', 330, true],
                [4, 'Tobi Williams', 'Nice shirt', 'Fabric is great. It creases easily, as linen does, so keep an iron nearby.', 300, false],
            ],
            'ankara-print-wrap-dress' => [
                [5, 'Chiamaka Udeh', 'So beautiful', 'I got so many compliments at a wedding. The print is bright and the wrap style is flattering.', 70, true],
                [5, 'Blessing Okoro', 'Lovely fabric', 'The colours have not faded after washing and the sizing guide was accurate.', 90, false],
            ],
            'breeze-1-7l-electric-kettle' => [
                [5, 'Amaka Nnaji', 'Boils so fast', 'Water boils in about three minutes and it switches off by itself. Looks nice in my kitchen too.', 450, true],
                [4, 'Femi Lawal', 'Works well', 'Does the job and the handle stays cool. The cable could be a little longer.', 420, false],
            ],
            'problend-1000w-blender' => [
                [5, 'Mama Tolu', 'Blends pepper perfectly', 'I use it for pepper and tomatoes for stew every week and it is very smooth. Easy to clean.', 130, true],
                [4, 'Sani Garba', 'Strong motor', 'Crushes ice for smoothies without stress. A bit noisy, but that is normal for a powerful blender.', 110, false],
            ],
            'coolwave-18-inch-standing-fan' => [
                [5, 'Esther Akpan', 'Very strong breeze', 'Keeps my whole room cool and it is quiet on the lowest speed at night.', 550, true],
            ],
            'chrono-steel-analogue-watch' => [
                [5, 'Kunle Adebayo', 'Classy watch', 'Looks more expensive than it is. The strap was easy to adjust and it keeps good time.', 100, true],
            ],
            'powergo-20000mah-power-bank' => [
                [5, 'Victor Etim', 'Lifesaver during NEPA wahala', 'Charges my phone about four times. Fast charging works with my phone. Every home needs one.', 160, true],
                [4, 'Hauwa Ali', 'Reliable', 'Holds charge well for days. It is a bit heavy in a small bag but that is expected for 20000mAh.', 140, false],
            ],
            'glow-vitamin-c-face-serum-30ml' => [
                [5, 'Zainab Lawal', 'My skin is glowing', 'After three weeks my dark spots are lighter. Not sticky and works well under makeup.', 36, true],
            ],
            'shea-butter-body-lotion-400ml' => [
                [5, 'Patience Obi', 'Smells amazing', 'Keeps my skin soft all day even in harmattan. The big bottle lasts a long time.', 280, true],
            ],
            'sage-ceramic-table-lamp' => [
                [4, 'Daniel Oyelaran', 'Pretty lamp', 'Gives a warm soft light, perfect for my bedside. Bulb was not included, so buy one with it.', 45, false],
            ],
            'amber-soy-candle-set' => [
                [5, 'Ruth Danjuma', 'Lovely scents', 'Burns evenly and the smell fills the living room without being too strong.', 230, false],
            ],
        ];
    }

    /** Reviews waiting in Admin → Reviews, so the moderation queue has something to show. */
    public static function waiting(): array
    {
        return [
            'aurora-x5-smartphone-128gb' => [3, 'Samuel Udo', 'Good but gets warm', 'Phone gets a little warm when gaming for a long time. Otherwise everything works fine.', 3, false],
            'problend-1000w-blender' => [5, 'Joy Effiong', 'Excellent blender', 'Best blender I have used. Very strong and the jar is easy to wash.', 5, true],
        ];
    }

    public function run(): void
    {
        $products = Product::query()->whereIn('slug', array_keys(static::reviews()))->get()->keyBy('slug');

        foreach (static::reviews() as $slug => $reviews) {
            $product = $products[$slug] ?? null;
            if (! $product || $product->reviews()->exists()) {
                continue;
            }

            foreach ($reviews as $review) {
                $this->create($product, $review, 'approved');
            }
            if ($waiting = static::waiting()[$slug] ?? null) {
                $this->create($product, $waiting, 'pending');
            }
        }
    }

    protected function create(Product $product, array $r, string $status): void
    {
        [$rating, $name, $title, $body, $hoursAgo, $verified] = $r;

        // Never dated before the product itself was added.
        $at = now()->subHours($hoursAgo);
        if ($product->created_at && $at->lt($product->created_at)) {
            $at = $product->created_at->copy()->addHours(2);
        }

        $review = new Review([
            'product_id' => $product->id,
            'name' => $name,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'is_verified' => $verified,
            'body_hash' => ReviewGuard::bodyHash($body),
            'approved_at' => $status === 'approved' ? $at->copy()->addHours(1) : null,
        ]);
        $review->created_at = $at;
        $review->updated_at = $at;
        $review->save();
    }
}
