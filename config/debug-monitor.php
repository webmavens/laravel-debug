<?php

return [
    'auth_key' => env('DEBUG_MONITOR_AUTH_KEY', 'webmavens'),
    'notify_email' => env('DEBUG_MONITOR_NOTIFY_EMAIL', 'admin@example.com'),
    /*
    |--------------------------------------------------------------------------
    | Log Retention (in days)
    |--------------------------------------------------------------------------
    |
    | Define how many days debug logs should be kept before being deleted.
    | Example: 30 means logs older than 30 days will be removed.
    |
    */
    'log_retention_days' => env('DEBUG_MONITOR_LOG_RETENTION_DAYS', 1),
];
