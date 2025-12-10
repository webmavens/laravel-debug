<?php

return [
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
    | Log Retention (in days)
    |--------------------------------------------------------------------------
    |
    | Define how many days debug logs should be kept before being deleted.
    |
    */
    'log_retention_days' => env('DEBUG_MONITOR_LOG_RETENTION_DAYS', 1),
];
