<?php

namespace App\Services;

/**
 * Reads and writes embedded ICC colour profiles.
 *
 * GD discards colour profiles when it decodes an image. Modern phones save
 * photos in wide-gamut colour spaces (e.g. Display P3); without their profile,
 * browsers show those photos duller and less saturated than the original.
 * This class copies the profile from the uploaded file into the optimised one.
 */
class ColorProfile
{
    /** Extract an RGB ICC profile from JPEG, PNG or WebP bytes (null if none or not worth keeping). */
    public static function extract(string $bytes, int $type): ?string
    {
        $icc = match ($type) {
            IMAGETYPE_JPEG => self::fromJpeg($bytes),
            IMAGETYPE_PNG => self::fromPng($bytes),
            IMAGETYPE_WEBP => self::fromWebp($bytes),
            default => null,
        };

        if ($icc === null || strlen($icc) < 132 || substr($icc, 36, 4) !== 'acsp') {
            return null;
        }

        // GD always produces RGB pixels, so only RGB profiles are valid to re-attach.
        if (substr($icc, 16, 4) !== 'RGB ') {
            return null;
        }

        // Browsers assume sRGB already; skip it to save a few KB per image.
        if (str_contains($icc, 'sRGB')) {
            return null;
        }

        return $icc;
    }

    /** Embed a profile into a GD-produced WebP file (converts it to the extended VP8X format). */
    public static function embedInWebp(string $webp, string $icc, int $width, int $height): string
    {
        if (substr($webp, 0, 4) !== 'RIFF' || substr($webp, 8, 4) !== 'WEBP') {
            return $webp;
        }

        $chunks = substr($webp, 12);
        $iccChunk = 'ICCP'.pack('V', strlen($icc)).$icc.(strlen($icc) % 2 ? "\0" : '');
        $first = substr($chunks, 0, 4);

        if ($first === 'VP8X') {
            // Already extended (e.g. lossy with alpha): set the ICC flag and insert after VP8X.
            $size = unpack('V', substr($chunks, 4, 4))[1];
            $vp8x = substr($chunks, 0, 8 + $size);
            $vp8x[8] = chr(ord($vp8x[8]) | 0x20);
            $body = $vp8x.$iccChunk.substr($chunks, 8 + $size);
        } else {
            $alpha = $first === 'VP8L' && self::vp8lHasAlpha($chunks);
            $flags = 0x20 | ($alpha ? 0x10 : 0);
            $vp8x = 'VP8X'.pack('V', 10).chr($flags)."\0\0\0"
                .substr(pack('V', $width - 1), 0, 3)
                .substr(pack('V', $height - 1), 0, 3);
            $body = $vp8x.$iccChunk.$chunks;
        }

        return 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
    }

    /** Embed a profile into a JPEG as APP2 "ICC_PROFILE" segments. */
    public static function embedInJpeg(string $jpeg, string $icc): string
    {
        if (substr($jpeg, 0, 2) !== "\xFF\xD8") {
            return $jpeg;
        }

        $parts = str_split($icc, 65519);
        $segments = '';
        foreach ($parts as $i => $part) {
            $payload = "ICC_PROFILE\0".chr($i + 1).chr(count($parts)).$part;
            $segments .= "\xFF\xE2".pack('n', strlen($payload) + 2).$payload;
        }

        // Keep the JFIF APP0 segment first if present.
        $offset = 2;
        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $offset += 2 + unpack('n', substr($jpeg, 4, 2))[1];
        }

        return substr($jpeg, 0, $offset).$segments.substr($jpeg, $offset);
    }

    /** Whether the PNG/WebP source uses transparency (read from headers, no pixel scan). */
    public static function hasAlpha(string $bytes, int $type): bool
    {
        if ($type === IMAGETYPE_PNG) {
            $colorType = ord($bytes[25] ?? "\0");

            return in_array($colorType, [4, 6], true) || str_contains(substr($bytes, 0, strpos($bytes, 'IDAT') ?: 0), 'tRNS');
        }

        if ($type === IMAGETYPE_WEBP) {
            $first = substr($bytes, 12, 4);
            if ($first === 'VP8X') {
                return (bool) (ord($bytes[20] ?? "\0") & 0x10);
            }

            return $first === 'VP8L' && self::vp8lHasAlpha(substr($bytes, 12));
        }

        return false;
    }

    public static function isLosslessWebp(string $bytes): bool
    {
        return str_contains(substr($bytes, 12, 64), 'VP8L');
    }

    protected static function vp8lHasAlpha(string $chunks): bool
    {
        // VP8L data: 1 signature byte, then 14 bits width, 14 bits height, 1 bit alpha_is_used.
        $bits = unpack('V', substr($chunks, 9, 4))[1] ?? 0;

        return (bool) (($bits >> 28) & 1);
    }

    protected static function fromJpeg(string $bytes): ?string
    {
        $pieces = [];
        $pos = 2;
        $len = strlen($bytes);

        while ($pos + 4 <= $len && $bytes[$pos] === "\xFF") {
            $marker = ord($bytes[$pos + 1]);
            if ($marker === 0xDA || $marker === 0xD9) {
                break; // start of scan / end of image
            }
            $size = unpack('n', substr($bytes, $pos + 2, 2))[1];
            if ($marker === 0xE2 && substr($bytes, $pos + 4, 12) === "ICC_PROFILE\0") {
                $seq = ord($bytes[$pos + 16]);
                $pieces[$seq] = substr($bytes, $pos + 18, $size - 16);
            }
            $pos += 2 + $size;
        }

        if (! $pieces) {
            return null;
        }
        ksort($pieces);

        return implode('', $pieces);
    }

    protected static function fromPng(string $bytes): ?string
    {
        $pos = 8;
        $len = strlen($bytes);

        while ($pos + 8 <= $len) {
            $size = unpack('N', substr($bytes, $pos, 4))[1];
            $type = substr($bytes, $pos + 4, 4);
            if ($type === 'IDAT' || $type === 'IEND') {
                break;
            }
            if ($type === 'iCCP') {
                $data = substr($bytes, $pos + 8, $size);
                $nul = strpos($data, "\0");
                if ($nul === false) {
                    return null;
                }
                $icc = @gzuncompress(substr($data, $nul + 2));

                return $icc === false ? null : $icc;
            }
            $pos += 12 + $size;
        }

        return null;
    }

    protected static function fromWebp(string $bytes): ?string
    {
        $pos = 12;
        $len = strlen($bytes);

        while ($pos + 8 <= $len) {
            $type = substr($bytes, $pos, 4);
            $size = unpack('V', substr($bytes, $pos + 4, 4))[1];
            if ($type === 'ICCP') {
                return substr($bytes, $pos + 8, $size);
            }
            if (in_array($type, ['VP8 ', 'VP8L'], true)) {
                break;
            }
            $pos += 8 + $size + ($size % 2);
        }

        return null;
    }
}
