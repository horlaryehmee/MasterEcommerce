<?php

namespace App\Support;

class Color
{
    public static function isHex(?string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value);
    }

    public static function sanitize(?string $value, string $fallback): string
    {
        return self::isHex($value) ? strtoupper($value) : $fallback;
    }

    /** Black or white, whichever reads better on the given background colour. */
    public static function contrast(string $hex): string
    {
        if (! self::isHex($hex)) {
            return '#FFFFFF';
        }

        [$r, $g, $b] = array_map(
            fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            sscanf($hex, '#%02x%02x%02x')
        );
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.4 ? '#111111' : '#FFFFFF';
    }
}
