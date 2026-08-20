<?php

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE', env('APP_VERSION', 'dev')),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    'send_default_pii' => false,

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0),

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0),

    'enable_logs' => false,

    'enable_metrics' => false,

];
