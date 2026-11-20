<?php

use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Services\Agent\Agent;
use App\Services\Agent\AgentException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Visitor statistics: refresh the free geolocation database monthly and keep 25 months of data.
// (Requires the Laravel scheduler cron: * * * * * php /path/to/artisan schedule:run)
Schedule::command('stats:geoip-update')->monthlyOn(3, '03:00')->withoutOverlapping();
Schedule::command('stats:prune')->dailyAt('03:30');

/*
| Update agent (Admin → Updates → Update server). Runs every minute but only
| contacts the update server every few minutes (the interval it asks for),
| unless an update is in progress. Does nothing when not connected.
*/
Artisan::command('agent:sync {--force : Contact the update server now} {--no-work : Report in only, do not install}', function (Agent $agent) {
    $result = $agent->sync((bool) $this->option('force'), ! $this->option('no-work'));
    if ($result['status'] !== 'idle' && $result['status'] !== 'not_connected') {
        $this->line("[{$result['status']}] {$result['message']}");
    }

    return in_array($result['status'], ['failed'], true) ? 1 : 0;
})->purpose('Check in with the central update server and carry out any update it sends');

Artisan::command('agent:finish', function (Agent $agent) {
    $result = $agent->finishPending();
    $this->line($result['message']);

    return $result['status'] === 'failed' ? 1 : 0;
})->purpose('Finish an installed update (runs on the new code, in a fresh process)');

Artisan::command('agent:connect {server} {code}', function (Agent $agent) {
    try {
        $c = $agent->connect($this->argument('server'), $this->argument('code'));
    } catch (AgentException $e) {
        return $this->error($e->getMessage());
    }
    $this->info("Connected as installation {$c->installation_id}.");
    $this->line("Signing key fingerprint: {$c->signing_fingerprint} (compare it with the update server's dashboard)");
})->purpose('Connect this website to a central update server with a one-time code');

/*
| Build an update package for the central update server:
|   php artisan release:package core                → core zip (production: use the GitHub release workflow,
|                                                      which installs PHP packages without dev tools)
|   php artisan release:package module gift-wrap    → zip of modules/gift-wrap
|   php artisan release:package extension acme-pay  → zip of extensions/acme-pay
*/
Artisan::command('release:package {type : core, module or extension} {slug?} {--out=}', function () {
    $type = $this->argument('type');
    $out = rtrim($this->option('out') ?: storage_path('app/releases'), '/\\');
    @mkdir($out, 0755, true);

    if ($type === 'core') {
        $root = base_path();
        $version = trim((string) file_get_contents(base_path('VERSION')));
        $zipPath = "{$out}/catalog-v{$version}.zip";
        $exclude = '~^(\.git|\.github|node_modules|tests|modules|extensions|storage|\.env(\..*)?$|public/storage$|public/hot$|bootstrap/cache/.+\.php$|\.phpunit)~';
    } elseif (in_array($type, ['module', 'extension'], true)) {
        $root = base_path(($type === 'module' ? 'modules' : 'extensions').'/'.$this->argument('slug'));
        $meta = json_decode((string) @file_get_contents($root.'/module.json'), true);
        if (! $meta) {
            return $this->error("No module.json in {$root}");
        }
        $version = $meta['version'];
        $zipPath = "{$out}/{$meta['slug']}-{$version}.zip";
        $exclude = '~^(\.git|node_modules)~';
    } else {
        return $this->error('Type must be core, module or extension.');
    }

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $count = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($file->isDir() || preg_match($exclude, $relative)) {
            continue;
        }
        $zip->addFile($file->getPathname(), $relative);
        $count++;
    }
    if ($type === 'core') {
        foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
            $zip->addEmptyDir($dir);
        }
    }
    $zip->close();
    $this->info("{$zipPath} ({$count} files, ".round(filesize($zipPath) / 1048576, 1).' MB)');
})->purpose('Build an update package (core, module or client extension)');

Schedule::command('agent:sync')->everyMinute()->withoutOverlapping(120)->when(fn () => AgentConnection::current() !== null);
Schedule::call(fn () => AgentLog::query()->where('created_at', '<', now()->subDays(config('agent.log_days')))->delete())->dailyAt('04:10')->name('agent-log-prune');
