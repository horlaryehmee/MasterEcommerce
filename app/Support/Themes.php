<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Storefront templates (Admin → Templates).
 *
 * A template has two parts:
 * - layout: how the storefront is built (header, footer, category section,
 *   section headings, homepage order). Always follows the active template.
 * - style: colours, fonts, button shape and product card. Copied into the
 *   branding settings when the template is applied (optional), so the owner
 *   can still fine-tune them in Website Settings → Branding.
 *
 * A preview shows a template on this site, with this site's own products and
 * content, without changing anything. Preview links are signed, so they can be
 * shared (with a client or a colleague) but not guessed.
 */
class Themes
{
    public const DEFAULT = 'essential';

    /** Session key holding the template being previewed. */
    public const PREVIEW_KEY = 'theme_preview';

    public const ALL = [
        'essential' => [
            'name' => 'Essential',
            'tagline' => 'Clean and versatile — suits any kind of shop.',
            'best_for' => ['General stores', 'Mixed products', 'Services'],
            'layout' => [
                'header' => 'classic',
                'footer' => 'dark',
                'categories' => 'cards',
                'headings' => 'left',
                'why' => 'cards',
                'home' => ['hero', 'marquee', 'categories', 'featured', 'promo', 'new_arrivals', 'why', 'about'],
            ],
            'style' => [
                'primary_color' => '#111827', 'secondary_color' => '#F4F1EC', 'accent_color' => '#C2410C',
                'heading_font' => null, 'body_font' => null, 'heading_weight' => null,
                'button_shape' => 'pill', 'product_card_style' => 'modern',
            ],
        ],
        'atelier' => [
            'name' => 'Atelier',
            'tagline' => 'Editorial and minimal, with tall photos that let clothes shine.',
            'best_for' => ['Fashion & clothing', 'Shoes & bags', 'Jewellery'],
            'layout' => [
                'header' => 'centered',
                'footer' => 'light',
                'categories' => 'editorial',
                'headings' => 'center',
                'why' => 'strip',
                'home' => ['hero', 'marquee', 'categories', 'new_arrivals', 'promo', 'featured', 'about', 'why'],
            ],
            'style' => [
                'primary_color' => '#111111', 'secondary_color' => '#F3EFE8', 'accent_color' => '#A0784A',
                'heading_font' => 'Cormorant Garamond', 'body_font' => 'Jost', 'heading_weight' => 500,
                'button_shape' => 'square', 'product_card_style' => 'portrait',
            ],
        ],
        'glow' => [
            'name' => 'Glow',
            'tagline' => 'Soft and feminine, with round category bubbles and gentle cards.',
            'best_for' => ['Hair & wigs', 'Beauty & skincare', 'Salons & spas'],
            'layout' => [
                'header' => 'centered',
                'footer' => 'tinted',
                'categories' => 'circles',
                'headings' => 'center',
                'why' => 'cards',
                'home' => ['hero', 'marquee', 'categories', 'featured', 'why', 'promo', 'new_arrivals', 'about'],
            ],
            'style' => [
                'primary_color' => '#7A2E4D', 'secondary_color' => '#FBEFF2', 'accent_color' => '#C9806B',
                'heading_font' => 'Playfair Display', 'body_font' => 'DM Sans', 'heading_weight' => 600,
                'button_shape' => 'pill', 'product_card_style' => 'soft',
            ],
        ],
        'volt' => [
            'name' => 'Volt',
            'tagline' => 'A busy-store layout: big search, quick category tiles and spec-style cards.',
            'best_for' => ['Electronics & gadgets', 'Phones & accessories', 'Appliances'],
            'layout' => [
                'header' => 'marketplace',
                'footer' => 'dark',
                'categories' => 'tiles',
                'headings' => 'left',
                'why' => 'strip',
                'home' => ['hero', 'why', 'categories', 'featured', 'new_arrivals', 'promo', 'marquee', 'about'],
            ],
            'style' => [
                'primary_color' => '#0F172A', 'secondary_color' => '#EEF2FF', 'accent_color' => '#2563EB',
                'heading_font' => 'Manrope', 'body_font' => 'Inter', 'heading_weight' => 800,
                'button_shape' => 'rounded', 'product_card_style' => 'boxed',
            ],
        ],
        'bloom' => [
            'name' => 'Bloom',
            'tagline' => 'Romantic and warm, with arched images — made for gifting.',
            'best_for' => ['Flowers & gifts', 'Cakes & bakeries', 'Events & decor'],
            'layout' => [
                'header' => 'classic',
                'footer' => 'tinted',
                'categories' => 'arches',
                'headings' => 'center',
                'why' => 'cards',
                'home' => ['hero', 'marquee', 'categories', 'featured', 'promo', 'new_arrivals', 'why', 'about'],
            ],
            'style' => [
                'primary_color' => '#D6338A', 'secondary_color' => '#FFF4F8', 'accent_color' => '#D6338A',
                'heading_font' => 'Cormorant Garamond', 'body_font' => 'Poppins', 'heading_weight' => 600,
                'button_shape' => 'rounded', 'product_card_style' => 'classic',
            ],
        ],
    ];

    public static function exists(?string $key): bool
    {
        return is_string($key) && isset(self::ALL[$key]);
    }

    /** The template being previewed in this browser, if any. */
    public static function previewing(?Request $request = null): ?string
    {
        $request ??= request();
        $key = $request->attributes->get(self::PREVIEW_KEY) ?? ($request->hasSession() ? $request->session()->get(self::PREVIEW_KEY) : null);

        return self::exists($key) ? $key : null;
    }

    /** The template the storefront shows for this request (a preview wins over the saved one). */
    public static function active(?BusinessSetting $s = null, ?Request $request = null): string
    {
        if ($preview = self::previewing($request)) {
            return $preview;
        }
        $s ??= BusinessSetting::current();

        return self::exists($s->theme) ? $s->theme : self::DEFAULT;
    }

    public static function layout(?string $key = null): array
    {
        return self::ALL[self::exists($key) ? $key : self::active()]['layout'];
    }

    /**
     * Branding values to use for this request: the saved settings, or, while
     * previewing, the previewed template's own colours and fonts (so the preview
     * shows the template as designed). Nothing is saved.
     */
    public static function effectiveSettings(?BusinessSetting $s = null, ?Request $request = null): BusinessSetting
    {
        $s ??= BusinessSetting::current();
        $preview = self::previewing($request);
        if (! $preview || $preview === $s->theme) {
            return $s;
        }

        return (clone $s)->forceFill(self::ALL[$preview]['style']);
    }

    /**
     * Switch to a template. With `$withStyle`, its colours, fonts, button shape
     * and product card replace the current branding (the business's logo,
     * content and products are never touched).
     */
    public static function apply(BusinessSetting $s, string $key, bool $withStyle): void
    {
        $s->update(['theme' => $key, ...($withStyle ? self::ALL[$key]['style'] : [])]);
    }

    /** Shareable link that opens the storefront in preview mode for one template. */
    public static function previewUrl(string $key): string
    {
        return URL::signedRoute('template.preview', ['template' => $key]);
    }

    /** Signed homepage URL showing a template for that one request (admin thumbnails); doesn't start a preview. */
    public static function thumbnailUrl(string $key): string
    {
        return URL::signedRoute('home', ['template_preview' => $key, 'embed' => 1]);
    }

    /** For the storefront: the active template's layout and, while previewing, the preview bar. */
    public static function shared(Request $request): array
    {
        $key = self::active(null, $request);
        $preview = self::previewing($request);

        return [
            'key' => $key,
            ...self::ALL[$key]['layout'],
            // No bar inside the admin's thumbnails.
            'preview' => $preview && ! $request->attributes->get('theme_embed') ? [
                'name' => self::ALL[$preview]['name'],
                'can_apply' => (bool) $request->user(),
                'apply_url' => route('admin.templates.index', ['apply' => $preview]),
                'exit_url' => route('template.exit'),
            ] : null,
        ];
    }
}
