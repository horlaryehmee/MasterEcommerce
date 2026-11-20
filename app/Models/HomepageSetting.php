<?php

namespace App\Models;

use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Single-row table holding editable homepage sections. */
class HomepageSetting extends Model
{
    /** Icons the admin can choose for "Why Choose Us" items (Lucide icon names). */
    public const WHY_ICONS = [
        'badge-check' => 'Quality badge',
        'tag' => 'Price tag',
        'headset' => 'Customer support',
        'message-circle' => 'Chat',
        'truck' => 'Delivery',
        'shield-check' => 'Shield',
        'sparkles' => 'Sparkles',
        'clock' => 'Clock',
        'heart' => 'Heart',
        'star' => 'Star',
    ];

    /** Hero designs; chosen separately for desktop/tablet and phones. */
    public const HERO_TYPES = [
        'split' => 'Split — text beside an image',
        'banner' => 'Full banner — one large image with text on top',
        'slider' => 'Banner slider — several rotating slides',
    ];

    public const HERO_WIDTHS = ['boxed' => 'Boxed (rounded corners)', 'full' => 'Full width (edge to edge)'];

    /** Slider/banner height on desktop: key => [label, recommended image size]. */
    public const DESKTOP_HEIGHTS = [
        'slim' => ['Slim (3:1)', '1920 × 640 px'],
        'standard' => ['Standard (12:5)', '1920 × 800 px'],
        'tall' => ['Tall (2:1)', '1920 × 960 px'],
    ];

    /** Slider/banner height on phones: key => [label, recommended image size]. */
    public const MOBILE_HEIGHTS = [
        'portrait' => ['Portrait (4:5)', '1080 × 1350 px'],
        'square' => ['Square (1:1)', '1080 × 1080 px'],
        'landscape' => ['Landscape (16:9)', '1080 × 608 px'],
    ];

    /** Where banner/slide text goes on phones. */
    public const MOBILE_TEXT = [
        'overlay' => 'On the image (recommended)',
        'below' => 'Below the image (image stays fully visible)',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'hero_mobile_text' => 'overlay',
        'hero_type_desktop' => 'split',
        'hero_type_mobile' => 'split',
        'hero_width' => 'full',
        'hero_text_align' => 'left',
        'hero_autoplay' => true,
        'hero_interval' => 6,
        'hero_desktop_height' => 'standard',
        'hero_mobile_height' => 'portrait',
        'announcement_enabled' => false,
        'announcement_bg_color' => '#111827',
        'announcement_text_color' => '#FFFFFF',
        'hero_enabled' => true,
        'categories_enabled' => true,
        'featured_enabled' => true,
        'new_arrivals_enabled' => true,
        'new_arrivals_count' => 8,
        'promo_enabled' => false,
        'about_enabled' => true,
        'why_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'announcement_enabled' => 'boolean',
            'hero_enabled' => 'boolean',
            'hero_autoplay' => 'boolean',
            'hero_interval' => 'integer',
            'categories_enabled' => 'boolean',
            'featured_enabled' => 'boolean',
            'new_arrivals_enabled' => 'boolean',
            'new_arrivals_count' => 'integer',
            'promo_enabled' => 'boolean',
            'about_enabled' => 'boolean',
            'why_enabled' => 'boolean',
            'why_items' => 'array',
        ];
    }

    protected static ?self $resolved = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::$resolved = null;
            Cache::forget('settings.homepage');
            StorefrontCache::flush();
        });
    }

    public static function current(): self
    {
        return static::$resolved ??= Cache::rememberForever('settings.homepage', function () {
            return static::query()->first() ?? static::query()->create([
                'hero_heading' => 'Welcome to our store',
                'hero_description' => 'Browse our products and chat with us on WhatsApp to place an enquiry.',
                'hero_primary_text' => 'Shop Products',
                'hero_primary_link' => '/products',
                'categories_heading' => 'Shop by Category',
                'featured_heading' => 'Featured Products',
                'new_arrivals_heading' => 'New Arrivals',
                'why_heading' => 'Why Choose Us',
                'why_items' => [],
            ]);
        });
    }

    public static function forgetResolved(): void
    {
        static::$resolved = null;
    }
}
