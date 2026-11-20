<?php

namespace App\Services\Stats;

/**
 * Traffic source and channel, following Google Analytics' default channel
 * grouping: UTM tags win, then the referring site, otherwise Direct.
 */
class Channel
{
    public const CHANNELS = ['Direct', 'Organic Search', 'Organic Social', 'Paid Search', 'Paid Social', 'Email', 'Referral', 'Display', 'Other'];

    protected const SEARCH = ['google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia', 'ask.com', 'naver', 'seznam', 'qwant', 'startpage', 'brave'];

    protected const SOCIAL = [
        'facebook' => 'Facebook', 'fb.me' => 'Facebook', 'fb.com' => 'Facebook', 'instagram' => 'Instagram', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'x.com' => 'X (Twitter)',
        'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 'pinterest' => 'Pinterest', 'youtube' => 'YouTube', 'youtu.be' => 'YouTube',
        'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'snapchat' => 'Snapchat', 'reddit' => 'Reddit', 'threads.net' => 'Threads', 'telegram' => 'Telegram', 't.me' => 'Telegram',
    ];

    /** @return array{channel: string, source: ?string, medium: ?string, campaign: ?string, referrer_host: ?string} */
    public static function classify(?string $referrer, array $utm, string $siteHost): array
    {
        $host = $referrer ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $host = $host ? preg_replace('/^(www\.|m\.|l\.|lm\.|mobile\.)/', '', $host) : null;
        // Links within this site aren't a source.
        if ($host && ($host === preg_replace('/^www\./', '', strtolower($siteHost)))) {
            $host = null;
        }

        $source = self::clean($utm['source'] ?? null);
        $medium = self::clean($utm['medium'] ?? null);
        $campaign = self::clean($utm['campaign'] ?? null);

        $social = $host ? self::socialName($host) : null;
        $search = $host && self::isSearch($host);
        $source ??= $social ?? ($search ? self::searchName($host) : $host);

        $m = strtolower((string) $medium);
        $sourceIsSocial = $social || ($source && self::socialName(strtolower($source)));
        $sourceIsSearch = $search || ($source && self::isSearch(strtolower($source)));

        $channel = match (true) {
            in_array($m, ['cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'paid-search'], true) || str_starts_with($m, 'paid') => $sourceIsSocial ? 'Paid Social' : 'Paid Search',
            in_array($m, ['paid_social', 'paidsocial', 'paid-social', 'social_paid', 'cpm'], true) => 'Paid Social',
            in_array($m, ['display', 'banner', 'cpv'], true) => 'Display',
            in_array($m, ['email', 'e-mail', 'newsletter'], true) => 'Email',
            in_array($m, ['social', 'social-network', 'social-media', 'sm', 'social network', 'social media'], true) || $sourceIsSocial => 'Organic Social',
            $m === 'organic' || $sourceIsSearch => 'Organic Search',
            $m === 'referral' || $host !== null => 'Referral',
            $source === null && $medium === null => 'Direct',
            default => 'Other',
        };

        return [
            'channel' => $channel,
            'source' => $source ? mb_substr($source, 0, 120) : null,
            'medium' => $medium ? mb_substr($medium, 0, 80) : ($channel === 'Direct' ? null : strtolower(str_replace(['Organic ', 'Paid '], ['', ''], $channel))),
            'campaign' => $campaign ? mb_substr($campaign, 0, 120) : null,
            'referrer_host' => $host ? mb_substr($host, 0, 120) : null,
        ];
    }

    protected static function clean(?string $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : $value;
    }

    protected static function isSearch(string $host): bool
    {
        foreach (self::SEARCH as $engine) {
            if (preg_match('/(^|\.)'.preg_quote($engine, '/').'(\.|$)/', $host)) {
                return true;
            }
        }

        return false;
    }

    protected static function searchName(string $host): string
    {
        foreach (self::SEARCH as $engine) {
            if (str_contains($host, $engine)) {
                return ucfirst(explode('.', $engine)[0]);
            }
        }

        return $host;
    }

    protected static function socialName(string $host): ?string
    {
        foreach (self::SOCIAL as $needle => $name) {
            if ($host === $needle || str_starts_with($host, $needle.'.') || str_ends_with($host, '.'.$needle) || str_contains($host, $needle.'.') || strtolower($host) === strtolower($name)) {
                return $name;
            }
        }

        return null;
    }
}
