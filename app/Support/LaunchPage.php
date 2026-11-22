<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;

/**
 * Coming soon / maintenance page: who sees it and what it says.
 * See App\Http\Middleware\ShowLaunchPage for where it is shown.
 */
class LaunchPage
{
    public const MODES = [
        'coming_soon' => 'Coming soon',
        'maintenance' => 'Down for maintenance',
    ];

    public const PREVIEW_COOKIE = 'launch_preview';

    /** Always reachable: the admin, setup, existing customers' orders and payments, and background files. */
    public const ALWAYS_OPEN = [
        'admin', 'admin/*', 'install', 'install/*', 'order/*', 'payments/*', 'hello', 'up',
        'build/*', 'storage/*', 'sw.js', 'manifest.webmanifest', 'offline', 'pwa/*', 'robots.txt', 'sitemap.xml',
    ];

    /** Switched on, and not opened yet (the countdown can open the shop by itself). */
    public static function active(?BusinessSetting $s = null): bool
    {
        $s ??= BusinessSetting::current();
        if (! $s->launch_enabled) {
            return false;
        }

        return ! ($s->launch_auto_open && $s->launch_at && $s->launch_at->isPast());
    }

    /** May this visitor see the shop anyway? (signed-in admin, or the secret preview link) */
    public static function canBypass(Request $request, BusinessSetting $s): bool
    {
        if ($request->user()) {
            return true;
        }
        $token = (string) $s->launch_token;

        return strlen($token) >= 20 && hash_equals($token, (string) $request->cookie(self::PREVIEW_COOKIE));
    }

    public static function heading(BusinessSetting $s): string
    {
        return $s->launch_heading ?: ($s->launch_mode === 'maintenance' ? 'We’ll be back soon' : 'Something great is coming');
    }

    public static function message(BusinessSetting $s): string
    {
        return $s->launch_message ?: ($s->launch_mode === 'maintenance'
            ? 'We’re making some improvements to our website. Please check back shortly.'
            : 'Our new online shop is almost ready. Check back soon, or chat with us on WhatsApp in the meantime.');
    }

    /** Everything resources/views/launch.blade.php shows. */
    public static function viewData(BusinessSetting $s): array
    {
        $fonts = Fonts::forSettings($s);
        $socials = $s->launch_show_contact
            ? \App\Models\SocialLink::query()->orderBy('sort_order')->get()
                ->filter(fn ($l) => isset(\App\Models\SocialLink::PLATFORMS[$l->platform]) && filled($l->url))
                ->map(fn ($l) => ['label' => \App\Models\SocialLink::PLATFORMS[$l->platform]['label'], 'url' => $l->url])->values()->all()
            : [];

        return [
            'name' => $s->business_name,
            'logo' => Media::url($s->logo_path),
            'favicon' => Media::url($s->favicon_path),
            'mode' => $s->launch_mode,
            'heading' => self::heading($s),
            'message' => self::message($s),
            'image' => Media::url($s->launch_image_path),
            'launchAt' => $s->launch_countdown && $s->launch_at && $s->launch_at->isFuture() ? $s->launch_at->toIso8601String() : null,
            'autoOpen' => (bool) $s->launch_auto_open,
            'whatsapp' => $s->launch_show_contact ? WhatsApp::generalLink($s) : null,
            'email' => $s->launch_show_contact ? $s->email : null,
            'phone' => $s->launch_show_contact ? $s->phone : null,
            'socials' => $socials,
            'primary' => Color::sanitize($s->primary_color, '#111827'),
            'accent' => Color::sanitize($s->accent_color, '#C2410C'),
            'fontStylesheet' => $fonts['stylesheet'],
            'fontVars' => $fonts['vars'],
        ];
    }

    /** Secret preview link, e.g. https://shop.example.com/?preview=abc… */
    public static function previewUrl(BusinessSetting $s): ?string
    {
        return $s->launch_token ? url('/').'?preview='.$s->launch_token : null;
    }
}
