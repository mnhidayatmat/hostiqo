<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure system monitoring intervals and data retention.
    |
    */

    // Monitoring interval in minutes (how often to collect metrics)
    'interval_minutes' => env('MONITORING_INTERVAL_MINUTES', 2),

    // Data retention period in hours (how long to keep historical data)
    'retention_hours' => env('MONITORING_RETENTION_HOURS', 24),

    // Enable/disable system monitoring
    'enabled' => env('MONITORING_ENABLED', true),

    // Dashboard chart time range in hours
    'chart_hours' => env('MONITORING_CHART_HOURS', 6),
];
