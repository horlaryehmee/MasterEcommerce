<?php

namespace App\Services\Stats;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;

/**
 * Device, browser and OS from the user agent, plus bot detection, using
 * Matomo's DeviceDetector (thousands of browsers, devices and crawlers).
 * Results are cached per user agent.
 */
class UserAgent
{
    /** @return array{bot: bool, device: ?string, browser: ?string, os: ?string} */
    public static function parse(?string $userAgent, array $headers = []): array
    {
        $userAgent = trim((string) $userAgent);
        if ($userAgent === '') {
            return ['bot' => true, 'device' => null, 'browser' => null, 'os' => null];
        }

        return Cache::remember('stats.ua.'.md5($userAgent), now()->addDay(), function () use ($userAgent, $headers) {
            $detector = new DeviceDetector($userAgent, ClientHints::factory($headers));
            $detector->discardBotInformation();
            $detector->parse();

            // Headless browsers and automation tools aren't real visitors either.
            if ($detector->isBot() || preg_match('/headless|phantomjs|puppeteer|playwright|selenium|lighthouse|pagespeed|gtmetrix|pingdom|uptime/i', $userAgent)) {
                return ['bot' => true, 'device' => null, 'browser' => null, 'os' => null];
            }

            $device = match (true) {
                $detector->isTablet() => 'tablet',
                $detector->isSmartphone() || $detector->isFeaturePhone() || $detector->isPhablet() => 'mobile',
                $detector->isDesktop() => 'desktop',
                $detector->isTV() => 'tv',
                default => $detector->getDeviceName() ?: 'other',
            };

            return [
                'bot' => false,
                'device' => substr($device, 0, 20),
                'browser' => substr((string) ($detector->getClient('name') ?: 'Unknown'), 0, 40),
                'os' => substr((string) ($detector->getOs('name') ?: 'Unknown'), 0, 40),
            ];
        });
    }
}
