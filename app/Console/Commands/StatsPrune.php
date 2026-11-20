<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Deletes visitor statistics older than the retention period (default 25 months, like Google Analytics' longest option). */
class StatsPrune extends Command
{
    protected $signature = 'stats:prune {--months=25 : Keep this many months of data}';

    protected $description = 'Delete old visitor statistics';

    public function handle(): int
    {
        $cutoff = now()->subMonths(max(1, (int) $this->option('months')));

        // Page views and events go with their sessions (cascade).
        $sessions = DB::table('stats_sessions')->where('last_activity_at', '<', $cutoff)->delete();
        $visitors = DB::table('stats_visitors')->where('last_seen_at', '<', $cutoff)->delete();

        $this->info("Deleted {$sessions} old visits and {$visitors} inactive visitors.");

        return self::SUCCESS;
    }
}
