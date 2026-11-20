<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Builds public URLs (with responsive variants) for stored images. */
class Media
{
    public static function url(?string $path, string $size = 'lg'): ?string
    {
        if (! $path) {
            return null;
        }

        if ($size !== 'lg') {
            $variant = self::variantPath($path, $size);
            // Fall back to the full-size file if this variant doesn't exist (e.g. older uploads).
            $path = Storage::disk('public')->exists($variant) ? $variant : $path;
        }

        return Storage::disk('public')->url($path);
    }

    /** Path of a size variant: "products/abc.webp" -> "products/abc-sm.webp". */
    public static function variantPath(string $path, string $size): string
    {
        $info = pathinfo($path);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return $dir.$info['filename'].'-'.$size.(isset($info['extension']) ? '.'.$info['extension'] : '');
    }

    /** Image payload for the frontend: src, small src, srcset and alt text. */
    public static function image(?string $path, ?string $alt = null): ?array
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $profile = config('catalog.images.'.strtok($path, '/'), ['lg' => 1200]);
        asort($profile);

        // Only list variants that exist, so srcset never points at a missing file.
        $srcset = [];
        foreach ($profile as $size => $width) {
            $file = $size === 'lg' ? $path : self::variantPath($path, $size);
            if ($size === 'lg' || $disk->exists($file)) {
                $srcset[] = $disk->url($file).' '.$width.'w';
            }
        }

        $lg = $disk->url($path);

        return [
            'src' => $lg,
            'sm' => self::url($path, 'sm'),
            'srcset' => count($srcset) > 1 ? implode(', ', $srcset) : null,
            'alt' => (string) $alt,
        ];
    }
}
