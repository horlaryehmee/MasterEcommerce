<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Safe rich-text rendering. Content is written in simple Markdown in the
 * admin; any raw HTML is stripped and unsafe links (javascript:, data:)
 * are removed, so rendered output cannot inject scripts.
 */
class Markdown
{
    public static function toHtml(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        return (string) Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);
    }

    /** Plain-text excerpt (for meta descriptions). */
    public static function excerpt(?string $text, int $limit = 160): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(self::toHtml($text))));

        return Str::limit($plain, $limit, '…');
    }
}
