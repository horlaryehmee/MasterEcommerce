<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Support\Tracking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviewAnalyticsLinks extends Command
{
    protected $signature = 'security:review-analytics-links {--redact : Replace historical private URLs with /private}';

    protected $description = 'Find historical private URLs in local analytics without printing credentials';

    public function handle(): int
    {
        $tables = [
            'stats_pageviews' => ['path', 'title'],
            'stats_sessions' => ['entry_path', 'exit_path', 'referrer_url', 'source', 'medium', 'campaign'],
            'stats_events' => ['label'],
        ];
        foreach ($tables as $table => $columns) {
            $matches = 0;
            foreach (DB::table($table)->select(['id', ...$columns])->lazyById(500) as $row) {
                $updates = [];
                foreach ($columns as $column) {
                    $value = (string) $row->$column;
                    if (! Tracking::containsPrivateUrl($value)) {
                        continue;
                    }
                    $updates[$column] = '/private';
                    if (preg_match('~/order/([A-Za-z0-9]{40})(?![A-Za-z0-9])~', rawurldecode($value), $match)) {
                        $number = Order::query()->where('token', $match[1])->value('number');
                        if ($number !== null) {
                            $this->line("Order #{$number} appears in {$table}, row {$row->id}.");
                        }
                    }
                }
                if ($updates) {
                    $matches++;
                    if ($this->option('redact')) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            }
            $this->info("{$table}: {$matches} affected rows.");
        }

        return self::SUCCESS;
    }
}
