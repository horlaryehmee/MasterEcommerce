<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Home-screen icons for the installable apps, made from the favicon (or logo).
 *
 *  - shop:  the image on white
 *  - admin: the image on a white tile over a dark background, so the two apps
 *           are easy to tell apart on a phone
 *
 * "maskable" icons keep the image inside the safe zone Android crops to.
 * Without a usable image, an SVG with the business initial is used instead.
 */
class AppIcon
{
    public const APPS = ['shop', 'admin'];

    public const SIZES = [180, 192, 512];

    /** Manifest "icons" entries for an app. */
    public static function manifestIcons(string $app): array
    {
        $icons = [];
        foreach ([192, 512] as $size) {
            foreach (['any', 'maskable'] as $purpose) {
                if ($url = self::url($app, $size, $purpose === 'maskable')) {
                    $icons[] = ['src' => $url, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => $purpose];
                }
            }
        }

        // Fallback (no image, or it couldn't be read): the initial, as SVG.
        return $icons ?: [
            ['src' => route('pwa.icon', ['app' => $app]), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ['src' => route('pwa.icon', ['app' => $app]), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
        ];
    }

    /** URL of a PNG icon, made on first use; null without a usable source image. */
    public static function url(string $app, int $size, bool $maskable = false): ?string
    {
        $source = self::source();
        if (! $source) {
            return null;
        }

        $disk = Storage::disk('public');
        $target = 'pwa/'.$app.'-'.$size.($maskable ? '-maskable' : '').'-'.substr(sha1($source.'|'.self::background($app)), 0, 12).'.png';
        if (! $disk->exists($target) && ! self::make($source, $target, $app, $size, $maskable)) {
            return null;
        }

        return EmailImage::absolute($disk->url($target));
    }

    public static function background(string $app): string
    {
        return $app === 'admin' ? '#18181B' : '#FFFFFF';
    }

    /** The favicon, else the logo (SVG can't be turned into PNG here). */
    protected static function source(): ?string
    {
        $s = BusinessSetting::current();
        foreach ([$s->favicon_path, $s->logo_path] as $path) {
            if ($path && ! str_ends_with(strtolower($path), '.svg') && Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function make(string $path, string $target, string $app, int $size, bool $maskable): bool
    {
        $disk = Storage::disk('public');

        try {
            if ($disk->size($path) > 15 * 1024 * 1024 || ! ($src = @imagecreatefromstring($disk->get($path)))) {
                return false;
            }

            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, true);
            imagesavealpha($canvas, true);
            [$r, $g, $b] = sscanf(self::background($app), '#%02x%02x%02x');
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

            if ($app === 'admin') {
                // White rounded tile in the middle; the image sits on it.
                $tile = (int) round($size * ($maskable ? 0.62 : 0.78));
                $x = intdiv($size - $tile, 2);
                self::roundedRect($canvas, $x, $x, $tile, (int) round($tile * 0.22), imagecolorallocate($canvas, 255, 255, 255));
                $box = (int) round($tile * 0.76);
            } else {
                // Maskable icons are cropped to a circle: keep the image in the middle 80%.
                $box = (int) round($size * ($maskable ? 0.62 : 0.84));
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min($box / max(1, $w), $box / max(1, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            imagecopyresampled($canvas, $src, intdiv($size - $nw, 2), intdiv($size - $nh, 2), 0, 0, $nw, $nh, $w, $h);

            ob_start();
            imagepng($canvas, null, 8);
            $disk->put($target, (string) ob_get_clean(), 'public');
            imagedestroy($src);
            imagedestroy($canvas);

            return true;
        } catch (Throwable $e) {
            Log::notice('App icon could not be made', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected static function roundedRect($im, int $x, int $y, int $size, int $radius, int $color): void
    {
        imagefilledrectangle($im, $x + $radius, $y, $x + $size - $radius, $y + $size, $color);
        imagefilledrectangle($im, $x, $y + $radius, $x + $size, $y + $size - $radius, $color);
        foreach ([[$x + $radius, $y + $radius], [$x + $size - $radius, $y + $radius], [$x + $radius, $y + $size - $radius], [$x + $size - $radius, $y + $size - $radius]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }

    /** SVG icon with the business initial (no image uploaded). */
    public static function svg(string $app): string
    {
        $s = BusinessSetting::current();
        $color = Color::sanitize($s->primary_color, '#111827');
        $bg = $app === 'admin' ? '#18181B' : $color;
        $fg = Color::contrast($bg);
        $letter = htmlspecialchars(mb_strtoupper(mb_substr(trim($s->business_name ?: 'S'), 0, 1)), ENT_XML1);
        $badge = $app === 'admin'
            ? '<rect x="300" y="300" width="150" height="150" rx="40" fill="'.$color.'"/><circle cx="375" cy="375" r="28" fill="none" stroke="'.Color::contrast($color).'" stroke-width="14"/>'
            : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" fill="'.$bg.'"/>'
            .'<text x="256" y="256" dy=".35em" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="260" font-weight="700" fill="'.$fg.'">'.$letter.'</text>'
            .$badge.'</svg>';
    }
}
