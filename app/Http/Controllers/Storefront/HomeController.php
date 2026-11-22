<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Support\Markdown;
use App\Support\Media;
use App\Support\Seo;
use Inertia\Response;

class HomeController extends StorefrontController
{
    public function __invoke(): Response
    {
        $home = HomepageSetting::current();

        return $this->page('Storefront/Home', [
            'hero' => $this->hero($home),
            'marquee' => $this->marquee($home),
            'categories' => $this->categories($home),
            'featured' => $this->featured($home),
            'newArrivals' => $this->newArrivals($home),
            'promo' => $this->promo($home),
            'about' => $this->about($home),
            'why' => $this->why($home),
            'reviews' => $this->reviews(),
            // Section order comes from the template (Admin → Templates).
            'order' => \App\Support\Themes::layout()['home'],
        ], Seo::make()->home());
    }

    /**
     * "What our customers say": approved 4–5 star reviews, once there are at
     * least Review::HOME_MINIMUM of them. Can be switched off in Admin → Reviews.
     */
    protected function reviews(): ?array
    {
        $s = \App\Models\BusinessSetting::current();
        if (! $s->reviews_enabled || ! $s->reviews_home_enabled) {
            return null;
        }

        return \App\Support\StorefrontCache::remember('home.reviews', function () {
            $base = \App\Models\Review::query()->approved()->where('rating', '>=', 4)
                ->whereHas('product', fn ($q) => $q->visible());
            $count = (clone $base)->count();
            if ($count < \App\Models\Review::HOME_MINIMUM) {
                return null;
            }
            $avg = \App\Models\Review::query()->approved()->avg('rating');

            return [
                'average' => round((float) $avg, 1),
                'total' => \App\Models\Review::query()->approved()->count(),
                'items' => $base->with(['product:id,name,slug', 'product.primaryImage'])
                    ->latest('approved_at')->limit(12)->get()
                    ->map(fn ($r) => $r->toPublic() + [
                        'product' => ['name' => $r->product->name, 'url' => $r->product->url(), 'image' => Media::url($r->product->primaryImage?->path, 'sm')],
                    ])->values(),
            ];
        });
    }

    /**
     * The hero can use a different design on desktop and on phones. Each choice
     * falls back gracefully: a slider with no slides shows the single hero, a
     * banner without an image becomes the split design, and so on.
     */
    protected function hero(HomepageSetting $h): ?array
    {
        if (! $h->hero_enabled) {
            return null;
        }

        $slides = HeroSlide::query()->active()->ordered()->get()->map->toSlide()->values();
        $hasSingle = filled($h->hero_heading);

        $resolve = function (?string $type) use ($slides, $hasSingle, $h): ?string {
            $type = array_key_exists((string) $type, HomepageSetting::HERO_TYPES) ? $type : 'split';
            if ($type === 'slider' && $slides->isEmpty()) {
                $type = 'split';
            }
            if ($type === 'banner' && ! $h->hero_image_path) {
                $type = 'split';
            }
            if ($type !== 'slider' && ! $hasSingle) {
                return $slides->isNotEmpty() ? 'slider' : null;
            }

            return $type;
        };

        $desktop = $resolve($h->hero_type_desktop);
        $mobile = $resolve($h->hero_type_mobile);
        if (! $desktop && ! $mobile) {
            return null;
        }

        return [
            'desktop' => $desktop,
            'mobile' => $mobile,
            'width' => $h->hero_width === 'full' ? 'full' : 'boxed',
            'content' => $hasSingle ? [
                'eyebrow' => $h->hero_eyebrow,
                'heading' => $h->hero_heading,
                'description' => $h->hero_description,
                'image' => Media::image($h->hero_image_path, $h->hero_heading),
                'mobile_image' => Media::image($h->hero_mobile_image_path, $h->hero_heading),
                'primary' => $this->button($h->hero_primary_text, $h->hero_primary_link),
                'align' => $h->hero_text_align === 'center' ? 'center' : 'left',
            ] : null,
            'slides' => in_array('slider', [$desktop, $mobile], true) ? $slides : [],
            'slider' => [
                'autoplay' => $h->hero_autoplay,
                'interval' => max(3, min(15, $h->hero_interval ?: 6)),
                'desktop_height' => array_key_exists($h->hero_desktop_height, HomepageSetting::DESKTOP_HEIGHTS) ? $h->hero_desktop_height : 'standard',
                'mobile_height' => array_key_exists($h->hero_mobile_height, HomepageSetting::MOBILE_HEIGHTS) ? $h->hero_mobile_height : 'portrait',
                'mobile_text' => $h->hero_mobile_text === 'overlay' ? 'overlay' : 'below',
                // Phones: hero height as a share of the screen height.
                'mobile_vh' => max(40, min(100, (int) ($h->hero_mobile_vh ?: 75))),
                // Phones, text on the image: at the bottom or in the middle (always centred left to right).
                'mobile_valign' => $h->hero_mobile_valign === 'middle' ? 'middle' : 'bottom',
                'dots' => array_key_exists($h->hero_dots_position, HomepageSetting::DOTS_POSITIONS) ? $h->hero_dots_position : 'center',
                'shade' => array_key_exists($h->hero_shade, HomepageSetting::SHADES) ? $h->hero_shade : 'strong',
            ],
        ];
    }

    /** The scrolling text strip under the hero. */
    protected function marquee(HomepageSetting $h): ?array
    {
        $items = collect($h->marquee_items ?? [])->filter(fn ($t) => filled($t))->values();
        if (! $h->marquee_enabled || $items->isEmpty()) {
            return null;
        }

        return [
            'items' => $items->all(),
            'separator' => $h->marquee_separator,
            'bg' => $h->marquee_bg_color,
            'color' => $h->marquee_text_color,
            // Separators and borders; the brand accent when not set.
            'accent' => $h->marquee_accent_color,
            'border' => $h->marquee_border,
            'uppercase' => $h->marquee_uppercase,
            'speed' => array_key_exists($h->marquee_speed, HomepageSetting::MARQUEE_SPEEDS) ? $h->marquee_speed : 'normal',
        ];
    }

    protected function categories(HomepageSetting $h): ?array
    {
        if (! $h->categories_enabled) {
            return null;
        }

        $base = Category::query()->published()->ordered()
            ->withCount(['products as published_products_count' => fn ($q) => $q->published()]);

        // Show the categories the admin selected; fall back to the first few if none are selected.
        $categories = (clone $base)->where('is_featured', true)->get();
        if ($categories->isEmpty()) {
            $categories = $base->limit(8)->get();
        }

        return $categories->isEmpty() ? null : [
            'heading' => $h->categories_heading ?: 'Shop by Category',
            'subheading' => $h->categories_subheading,
            'items' => $categories->map->toCard()->values(),
        ];
    }

    protected function featured(HomepageSetting $h): ?array
    {
        if (! $h->featured_enabled) {
            return null;
        }

        $products = Product::query()->visible()->where('is_featured', true)
            ->with(['primaryImage', 'category:id,name'])
            ->latest()->limit(8)->get();

        return $products->isEmpty() ? null : [
            'heading' => $h->featured_heading ?: 'Featured Products',
            'subheading' => $h->featured_subheading,
            'items' => $products->map->toCard()->values(),
        ];
    }

    protected function newArrivals(HomepageSetting $h): ?array
    {
        if (! $h->new_arrivals_enabled) {
            return null;
        }

        $limit = max(4, min(12, $h->new_arrivals_count ?: 8));
        $base = Product::query()->visible()->with(['primaryImage', 'category:id,name'])->latest()->latest('id');

        // Products marked "new arrival"; if none are marked, show the most recently added.
        $products = (clone $base)->where('is_new_arrival', true)->limit($limit)->get();
        if ($products->isEmpty()) {
            $products = $base->limit($limit)->get();
        }

        return $products->isEmpty() ? null : [
            'heading' => $h->new_arrivals_heading ?: 'New Arrivals',
            'subheading' => $h->new_arrivals_subheading,
            'items' => $products->map->toCard()->values(),
        ];
    }

    protected function promo(HomepageSetting $h): ?array
    {
        // An offer with an end date disappears by itself once it's over.
        if (! $h->promo_enabled || blank($h->promo_heading) || $h->promo_ends_at?->isPast()) {
            return null;
        }

        return [
            'eyebrow' => $h->promo_eyebrow,
            'badge' => $h->promo_badge,
            'ends_at' => $h->promo_ends_at?->toIso8601String(),
            'bg' => $h->promo_bg_color,
            'heading' => $h->promo_heading,
            'description' => $h->promo_description,
            'image' => Media::image($h->promo_image_path, $h->promo_heading),
            'button' => $this->button($h->promo_button_text, $h->promo_button_link),
        ];
    }

    protected function about(HomepageSetting $h): ?array
    {
        if (! $h->about_enabled || blank($h->about_body)) {
            return null;
        }

        return [
            'heading' => $h->about_heading ?: 'About Us',
            'body_html' => Markdown::toHtml($h->about_body),
            'image' => Media::image($h->about_image_path, $h->about_heading),
        ];
    }

    protected function why(HomepageSetting $h): ?array
    {
        $items = collect($h->why_items ?? [])
            ->filter(fn ($i) => filled($i['title'] ?? null))
            ->map(fn ($i) => [
                'icon' => array_key_exists($i['icon'] ?? '', HomepageSetting::WHY_ICONS) ? $i['icon'] : 'badge-check',
                'title' => $i['title'],
                'text' => $i['text'] ?? '',
            ])->values();

        if (! $h->why_enabled || $items->isEmpty()) {
            return null;
        }

        return [
            'heading' => $h->why_heading ?: 'Why Choose Us',
            'subheading' => $h->why_subheading,
            'items' => $items,
        ];
    }

    protected function button(?string $text, ?string $link): ?array
    {
        return filled($text) && filled($link) ? ['text' => $text, 'link' => $link] : null;
    }
}
