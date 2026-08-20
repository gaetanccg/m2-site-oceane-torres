<?php

return [

    'token' => env('HEALTH_CHECK_TOKEN'),

    'alerts' => [
        'enabled' => (bool) env('SUPERVISION_ALERTS_ENABLED', true),
        'recipient' => env('SUPERVISION_ALERT_EMAIL'),
        'cooldown_minutes' => (int) env('SUPERVISION_ALERT_COOLDOWN_MINUTES', 60),
    ],

    'thresholds' => [
        'database_slow_ms' => (int) env('SUPERVISION_DATABASE_SLOW_MS', 1000),
        'database_connect_slow_ms' => (int) env('SUPERVISION_DATABASE_CONNECT_SLOW_MS', 2500),
        'queue_depth' => (int) env('SUPERVISION_QUEUE_DEPTH', 100),
        'queue_oldest_pending_minutes' => (int) env('SUPERVISION_QUEUE_OLDEST_PENDING_MINUTES', 15),
        'queue_worker_stale_minutes' => (int) env('SUPERVISION_QUEUE_WORKER_STALE_MINUTES', 10),
        'scheduler_stale_minutes' => (int) env('SUPERVISION_SCHEDULER_STALE_MINUTES', 120),
    ],

    'storage' => [
        'disk' => env('SUPERVISION_STORAGE_DISK') ?: env('FILESYSTEM_DISK', 'minio'),
        'witness' => env('SUPERVISION_STORAGE_WITNESS'),
        'probe_prefix' => '.supervision',
    ],

    'healthchecks' => [
        'rgpd_cleanup_url' => env('HEALTHCHECKS_RGPD_URL'),
        'reconcile_orders_url' => env('HEALTHCHECKS_RECONCILE_URL'),
    ],

    'heartbeat' => [
        'ttl_minutes' => (int) env('SUPERVISION_HEARTBEAT_TTL_MINUTES', 1440),
        'write_interval_seconds' => (int) env('SUPERVISION_HEARTBEAT_WRITE_INTERVAL', 60),
    ],

];
