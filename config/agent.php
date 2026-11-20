<?php

/*
 * Update agent: connects this website to your central update server
 * (Admin → Updates → Update server). All traffic is outgoing HTTPS from this
 * site; the update server never logs in to this site or its database.
 * The shop works normally whether or not the update server is reachable.
 */
return [
    // Plain-HTTP update server addresses are refused unless this is true (local testing only).
    'allow_insecure' => (bool) env('AGENT_ALLOW_INSECURE', false),

    // Seconds to wait for the update server (package downloads get longer).
    'timeout' => 20,
    'download_timeout' => 900,

    // Hold an install while customers are checking out: wait if an order was placed
    // in the last N minutes, but never hold an update longer than max_defer_minutes.
    'quiet_minutes' => (int) env('AGENT_QUIET_MINUTES', 3),
    'max_defer_minutes' => (int) env('AGENT_MAX_DEFER_MINUTES', 30),

    // Local update log entries are kept this many days.
    'log_days' => 90,

    // Where the agent keeps its small state files (current job, history, outbox).
    'state_path' => storage_path('app/agent'),

    // From cron, finish an installed update in a fresh PHP process right away (needs proc_open).
    // If that isn't possible, the next scheduled run (a minute later) finishes it.
    'spawn_finish' => true,
];
