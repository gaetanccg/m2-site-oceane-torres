<?php

// Seuls les écarts aux défauts du SDK. Sans DSN, le SDK ne s'initialise pas.
// Le bloc `breadcrumbs` n'est volontairement pas redéfini : la fusion de config
// est peu profonde, un override partiel désactiverait les autres fils d'Ariane.
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
