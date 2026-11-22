<?php

namespace App\Services;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores uploaded images safely and optimises them automatically while
 * keeping them visually identical to the original.
 *
 * Every image is decoded and re-encoded with GD, which:
 *  - guarantees the file really is an image (anything else fails to decode),
 *  - removes hidden metadata such as GPS location and any embedded payloads,
 *  - corrects phone rotation (EXIF orientation).
 *
 * Quality is preserved by:
 *  - high-quality WebP (quality 90 by default) for photos — visually lossless,
 *  - lossless WebP for logos, graphics and screenshots, so text and edges stay crisp,
 *  - never enlarging an image, and keeping a large full-size version,
 *  - light sharpening after downsizing (resizing slightly softens detail),
 *  - keeping the photo's colour profile (e.g. iPhone "Display P3") so colours stay vivid.
 *
 * Smaller copies ("-md", "-sm") are created for phones and thumbnails and used via srcset.
 * Files get random names, so user-supplied file names never reach the disk.
 */
class ImageStorage
{
    /** Running totals for this request: original vs optimised (full-size) bytes. */
    protected array $stats = ['count' => 0, 'before' => 0, 'after' => 0];

    /**
     * @param  string  $profile  Key of config('catalog.images'), also used as the directory.
     * @param  string  $format  "webp" (default), "png" (favicons) or "jpg" (social sharing image).
     * @return string Stored path of the full-size image, relative to the public disk.
     */
    public function store(UploadedFile|string $source, string $profile, string $format = 'webp', string $disk = 'public'): string
    {
        $sizes = $this->sizes($profile);
        $sourcePath = $source instanceof UploadedFile ? $source->getRealPath() : $source;
        $bytes = (string) @file_get_contents($sourcePath);

        if ($format === 'webp' && ! function_exists('imagewebp')) {
            $format = 'jpg';
        }

        $name = $profile.'/'.Str::lower((string) Str::ulid()).'.'.$format;
        $after = $this->writeVariants($bytes, $name, $sizes, $format, $profile === 'branding', disk: $disk);

        $this->stats['count']++;
        $this->stats['before'] += strlen($bytes);
        $this->stats['after'] += $after;

        return $name;
    }

    /**
     * (Re)create the smaller size variants of an already stored image from its
     * full-size file. Used after image sizes change: php artisan catalog:regenerate-images
     *
     * @return int Number of variant files written.
     */
    public function regenerateVariants(string $path, bool $force = false): int
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return 0;
        }

        $sizes = array_filter($this->sizes(strtok($path, '/')), fn ($s) => $s !== 'lg', ARRAY_FILTER_USE_KEY);
        $missing = $force ? $sizes : array_filter($sizes, fn ($s) => ! $disk->exists(Media::variantPath($path, $s)), ARRAY_FILTER_USE_KEY);
        if (! $missing) {
            return 0;
        }

        $format = pathinfo($path, PATHINFO_EXTENSION) ?: 'webp';
        $this->writeVariants($disk->get($path), $path, $missing, $format, strtok($path, '/') === 'branding', onlyVariants: true);

        return count($missing);
    }

    /** Delete an image and all of its size variants. */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $paths = [$path];
        foreach (array_keys($this->sizes(strtok($path, '/'), false)) as $size) {
            if ($size !== 'lg') {
                $paths[] = Media::variantPath($path, $size);
            }
        }
        Storage::disk('public')->delete($paths);
    }

    /** Totals for images stored during this request. */
    public function stats(): array
    {
        return $this->stats;
    }

    /** e.g. "3 images optimised: 8.4 MB → 1.2 MB" (null if nothing was uploaded). */
    public function summary(): ?string
    {
        if ($this->stats['count'] === 0) {
            return null;
        }

        $n = $this->stats['count'];

        return $n.' '.Str::plural('image', $n).' optimised: '
            .self::humanBytes($this->stats['before']).' → '.self::humanBytes($this->stats['after']);
    }

    public static function humanBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * Decode once, then write every requested size.
     *
     * @return int Bytes written for the full-size ("lg") file.
     */
    protected function writeVariants(string $bytes, string $name, array $sizes, string $format, bool $forceLossless, bool $onlyVariants = false, string $disk = 'public'): int
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The PHP GD extension is required for image uploads.');
        }
        $this->raiseMemoryLimit();

        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Unsupported image. Please upload a JPEG, PNG or WebP file.');
        }
        if ($info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > 12000 || $info[0] * $info[1] > 24000000) {
            throw new RuntimeException('Resize the image to at most 24 megapixels and 12,000 pixels per side.');
        }
        $type = $info[2];

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw new RuntimeException('The image could not be read. It may be corrupted.');
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        if ($type === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $bytes);
        }

        $icc = ColorProfile::extract($bytes, $type);
        $hasAlpha = ColorProfile::hasAlpha($bytes, $type);
        $isGraphicSource = $type === IMAGETYPE_PNG || ($type === IMAGETYPE_WEBP && ColorProfile::isLosslessWebp($bytes));

        $storage = Storage::disk($disk);
        $lossless = $forceLossless ? true : null; // null = decide on the first (largest) size
        $lgBytes = 0;

        // Largest first, so the lossless/lossy decision is made on the full-size image.
        arsort($sizes);
        $originalWidth = imagesx($image);
        $current = $image; // each smaller size is resized from the previous (unsharpened) one — faster

        try {
            foreach ($sizes as $size => $maxWidth) {
                // Big photos take a moment each; don't let a batch upload hit the host's time limit.
                @set_time_limit(60);

                $resized = $this->resize($current, (int) $maxWidth);

                // Restore crispness lost when shrinking. Mild, to avoid halos; skipped for
                // transparent images because convolution would disturb the alpha edges.
                $output = $resized;
                if ($resized !== $current && ! $hasAlpha && $maxWidth / $originalWidth < 0.85) {
                    $output = $this->copy($resized);
                    imageconvolution($output, [[-1, -1, -1], [-1, 32, -1], [-1, -1, -1]], 24, 0);
                }

                if ($format === 'webp' && $lossless === null) {
                    $lossless = $isGraphicSource && $this->losslessIsWorthIt($output);
                }

                $data = $this->encode($output, $format, (bool) $lossless);

                if ($icc !== null) {
                    $data = match ($format) {
                        'webp' => ColorProfile::embedInWebp($data, $icc, imagesx($output), imagesy($output)),
                        'jpg' => ColorProfile::embedInJpeg($data, $icc),
                        default => $data,
                    };
                }

                if ($size === 'lg') {
                    $lgBytes = strlen($data);
                }
                if (! ($onlyVariants && $size === 'lg')) {
                    if (! $storage->put($size === 'lg' ? $name : Media::variantPath($name, $size), $data)) {
                        throw new RuntimeException('Could not save the image.');
                    }
                }

                if ($output !== $resized) {
                    imagedestroy($output);
                }
                if ($current !== $image && $current !== $resized) {
                    imagedestroy($current);
                }
                $current = $resized;
            }
        } finally {
            if ($current !== $image) {
                imagedestroy($current);
            }
            imagedestroy($image);
        }

        return $lgBytes;
    }

    /**
     * Logos, illustrations and screenshots compress almost as well losslessly,
     * and lossless keeps text and sharp edges perfect. Photos saved as PNG
     * would be several times larger, so those use high-quality lossy WebP.
     */
    protected function losslessIsWorthIt($image): bool
    {
        $lossless = strlen($this->encode($image, 'webp', true));
        $lossy = strlen($this->encode($image, 'webp', false));

        return $lossless <= $lossy * 1.3;
    }

    /** @param \GdImage $image */
    protected function copy($image)
    {
        $copy = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $copy;
    }

    /** @param \GdImage $image */
    protected function resize($image, int $maxWidth)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= $maxWidth) {
            return $image; // never enlarge
        }

        $newHeight = max(1, (int) round($height * ($maxWidth / $width)));
        $canvas = imagecreatetruecolor($maxWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /** @param \GdImage $image */
    protected function encode($image, string $format, bool $lossless = false): string
    {
        $quality = max(60, min(100, (int) config('catalog.image_quality', 90)));
        ob_start();

        switch ($format) {
            case 'png':
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, null, 9); // PNG is always lossless
                break;
            case 'jpg':
                // JPEG has no transparency: flatten onto white.
                $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
                imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                imageinterlace($flat, true); // progressive
                imagejpeg($flat, null, $quality);
                imagedestroy($flat);
                break;
            default:
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagewebp($image, null, $lossless ? (defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 100) : $quality);
        }

        return (string) ob_get_clean();
    }

    /** @param \GdImage $image */
    protected function applyExifOrientation($image, string $bytes)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    protected function sizes(string $profile, bool $strict = true): array
    {
        $sizes = config('catalog.images.'.$profile);
        if (! $sizes && $strict) {
            throw new RuntimeException("Unknown image profile [{$profile}].");
        }

        return $sizes ?: [];
    }

    protected function raiseMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ($current === '-1') {
            return;
        }

        $bytes = (int) $current * match (strtoupper(substr((string) $current, -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        if ($bytes < 512 * 1024 ** 2) {
            @ini_set('memory_limit', '512M');
        }
    }
}
