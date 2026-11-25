<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    |
    | Debug Monitor is available in local by default. In non-local
    | environments, only users with matching email addresses may access it.
    |
    */
    'allow_in_local' => env('DEBUG_MONITOR_ALLOW_IN_LOCAL', true),
    'allowed_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DEBUG_MONITOR_ALLOWED_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Define who receives error / mismatch alerts from Debug Monitor.
    |
    */
    'notify_email' => env('DEBUG_MONITOR_NOTIFY_EMAIL', 'admin@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Scheduled Runs
    |--------------------------------------------------------------------------
    |
    | When true, the scheduler queues one job per due rule (a queue worker must
    | be running), so a slow query only delays its own rule. Set to false to run
    | due rules one after another inside the scheduler process instead.
    |
    */
    'queue_runs' => env('DEBUG_MONITOR_QUEUE_RUNS', true),

    /*
    |--------------------------------------------------------------------------
    | Log Retention (in days)
    |--------------------------------------------------------------------------
    |
    | Define how many days debug logs should be kept before being deleted.
    |
    */
    'log_retention_days' => env('DEBUG_MONITOR_LOG_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Query Execution Safety
    |--------------------------------------------------------------------------
    |
    | read_only  Reject anything that isn't a single read-only statement
    |            (SELECT / WITH / SHOW / EXPLAIN) so a rule can never mutate data.
    | query_timeout  Per-statement timeout in seconds (0 = no limit). Applied on
    |            supported drivers (MySQL/MariaDB, PostgreSQL) so a runaway query
    |            aborts instead of hanging the run.
    | max_rows   Cap on the number of result rows kept in memory / stored in the
    |            logs. Rules are streamed, so the true row count is always exact;
    |            only the retained sample is capped.
    |
    */
    'read_only' => env('DEBUG_MONITOR_READ_ONLY', true),
    'query_timeout' => (int) env('DEBUG_MONITOR_QUERY_TIMEOUT', 30),
    'max_rows' => (int) env('DEBUG_MONITOR_MAX_ROWS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Rule Files Directory
    |--------------------------------------------------------------------------
    |
    | Directory containing version-controlled rule definitions (one JSON file
    | per rule). Files are synced into the database — like migrations — and are
    | never executed directly. See DebugMonitor's sync command / UI button.
    |
    */
    'rules_path' => env('DEBUG_MONITOR_RULES_PATH', base_path('debug-monitor-rules')),
];
