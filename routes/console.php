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
// Online-payment orders never paid: re-check with Paystack, then cancel (frees held coupon uses).
Schedule::command('orders:cancel-unpaid')->hourly()->withoutOverlapping();

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
        $this->error($e->getMessage());

        return 1;
    }
    $this->info("Connected as installation {$c->installation_id}.");
    $this->line("Signing key fingerprint: {$c->signing_fingerprint} (compare it with the update server's dashboard)");
})->purpose('Connect this website to a central update server with a one-time code');

Schedule::command('agent:sync')->everyMinute()->withoutOverlapping(120)->when(fn () => AgentConnection::current() !== null);
Schedule::call(fn () => AgentLog::query()->where('created_at', '<', now()->subDays(config('agent.log_days')))->delete())->dailyAt('04:10')->name('agent-log-prune');
