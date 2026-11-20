<?php

namespace App\Services\Stats;

use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Country / region / city for a visitor, resolved on this server (the IP is
 * never stored or sent anywhere):
 *  1. Cloudflare's CF-IPCountry header, when the site is behind Cloudflare;
 *  2. the free DB-IP "Lite" database (storage/app/geoip, `php artisan stats:geoip-update`).
 */
class GeoLocator
{
    public const DIR = 'geoip';

    public const CITY_DB = 'dbip-city-lite.mmdb';

    public const COUNTRY_DB = 'dbip-country-lite.mmdb';

    protected ?Reader $reader = null;

    protected bool $opened = false;

    public static function databasePath(?string $file = null): string
    {
        return storage_path('app/'.self::DIR.($file ? '/'.$file : ''));
    }

    /** The installed database: city (preferred) or country. */
    public static function installed(): ?array
    {
        foreach ([self::CITY_DB => 'city', self::COUNTRY_DB => 'country'] as $file => $level) {
            $path = self::databasePath($file);
            if (is_file($path)) {
                return ['level' => $level, 'path' => $path, 'updated_at' => filemtime($path)];
            }
        }

        return null;
    }

    /** @return array{country: ?string, region: ?string, city: ?string} */
    public function locate(Request $request): array
    {
        $result = ['country' => null, 'region' => null, 'city' => null];

        $cf = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $cf) && ! in_array($cf, ['XX', 'T1'], true)) {
            $result['country'] = $cf;
        }

        $ip = $request->ip();
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $result;
        }

        try {
            $record = $this->reader()?->get($ip);
        } catch (Throwable) {
            $record = null;
        }
        if (! is_array($record)) {
            return $result;
        }

        $result['country'] ??= $record['country']['iso_code'] ?? null;
        // Only trust the city/region when the database agrees on the country.
        if (($record['country']['iso_code'] ?? null) === $result['country']) {
            $result['region'] = $record['subdivisions'][0]['names']['en'] ?? null;
            $result['city'] = $record['city']['names']['en'] ?? null;
        }

        return $result;
    }

    protected function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;
            $db = self::installed();
            $this->reader = $db ? new Reader($db['path']) : null;
        }

        return $this->reader;
    }
}
