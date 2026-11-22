<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Email-safe copies of stored images. Uploads are WebP, which Outlook for
 * Windows can't show, so emails use a small JPEG (photos) or PNG (logos, to
 * keep transparency). Made once and reused; SVG or unreadable files give null.
 */
class EmailImage
{
    /** Absolute URL of a JPEG/PNG copy at most $width px wide, or null. */
    public static function url(?string $path, int $width = 160, bool $png = false): ?string
    {
        if (! $path || str_ends_with(strtolower($path), '.svg')) {
            return null;
        }

        $disk = Storage::disk('public');
        $target = 'email/'.substr(sha1($path.'|'.$width.'|'.(int) $png), 0, 20).($png ? '.png' : '.jpg');

        if (! $disk->exists($target) && ! self::make($path, $target, $width, $png)) {
            return null;
        }

        return self::absolute($disk->url($target));
    }

    protected static function make(string $path, string $target, int $width, bool $png): bool
    {
        $disk = Storage::disk('public');

        try {
            if (! $disk->exists($path) || $disk->size($path) > 15 * 1024 * 1024) {
                return false;
            }
            $source = @imagecreatefromstring($disk->get($path));
            if (! $source) {
                return false;
            }

            $w = imagesx($source);
            $h = imagesy($source);
            // Twice the shown size for sharp images on high-density screens.
            $scale = min(1, ($width * 2) / max(1, $w));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));

            $out = imagecreatetruecolor($nw, $nh);
            if ($png) {
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            } else {
                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            }
            imagecopyresampled($out, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

            ob_start();
            $png ? imagepng($out, null, 8) : imagejpeg($out, null, 82);
            $disk->put($target, (string) ob_get_clean(), 'public');
            imagedestroy($source);
            imagedestroy($out);

            return true;
        } catch (Throwable $e) {
            Log::notice('Email image could not be made', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Mail clients need full URLs. */
    public static function absolute(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }
}
