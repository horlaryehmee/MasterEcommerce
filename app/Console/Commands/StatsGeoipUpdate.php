<?php

namespace App\Console\Commands;

use App\Services\Stats\GeoLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads the free DB-IP "IP to Country/City Lite" database (CC BY 4.0,
 * https://db-ip.com) used to show visitor locations. It is refreshed monthly
 * by the scheduler. Only this public file is downloaded; no visitor data is sent.
 */
class StatsGeoipUpdate extends Command
{
    protected $signature = 'stats:geoip-update {--city : Download the city-level database (~130 MB) instead of country-level (~8 MB)}';

    protected $description = 'Download or refresh the free DB-IP geolocation database for visitor statistics';

    public function handle(): int
    {
        $city = $this->option('city') || is_file(GeoLocator::databasePath(GeoLocator::CITY_DB));
        $kind = $city ? 'city' : 'country';
        $target = GeoLocator::databasePath($city ? GeoLocator::CITY_DB : GeoLocator::COUNTRY_DB);
        @mkdir(dirname($target), 0755, true);

        // The file for the current month is published at the start of the month; fall back to last month.
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = "https://download.db-ip.com/free/dbip-{$kind}-lite-{$month->format('Y-m')}.mmdb.gz";
            $this->line("Downloading {$url} …");
            $gz = $target.'.gz.tmp';

            try {
                $response = Http::timeout(600)->withOptions(['sink' => $gz])->get($url);
            } catch (Throwable $e) {
                $this->warn('Download failed: '.$e->getMessage());
                @unlink($gz);

                continue;
            }
            if (! $response->successful()) {
                @unlink($gz);

                continue;
            }

            $tmp = $target.'.tmp';
            $in = gzopen($gz, 'rb');
            $out = fopen($tmp, 'wb');
            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);
            @unlink($gz);

            // Sanity check: a valid database opens and finds a well-known address.
            try {
                $reader = new \MaxMind\Db\Reader($tmp);
                $ok = ($reader->get('8.8.8.8')['country']['iso_code'] ?? null) !== null;
                $reader->close();
            } catch (Throwable) {
                $ok = false;
            }
            if (! $ok) {
                @unlink($tmp);
                $this->error('The downloaded file is not a valid database.');

                return self::FAILURE;
            }

            rename($tmp, $target);
            if ($city) {
                @unlink(GeoLocator::databasePath(GeoLocator::COUNTRY_DB));
            }
            $this->info("Installed the {$kind} database ({$month->format('F Y')}). IP geolocation by DB-IP (https://db-ip.com).");

            return self::SUCCESS;
        }

        $this->error('Could not download the database. Check the server can reach download.db-ip.com.');

        return self::FAILURE;
    }
}
