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
    | Log Retention (in days)
    |--------------------------------------------------------------------------
    |
    | Define how many days debug logs should be kept before being deleted.
    |
    */
    'log_retention_days' => env('DEBUG_MONITOR_LOG_RETENTION_DAYS', 1),
];
