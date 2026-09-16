<?php

return [
    'geolocation' => [
        'enabled' => env('ANALYTICS_GEOLOCATION_ENABLED', true),
        'cache_ttl' => (int) env('ANALYTICS_GEO_CACHE_TTL', 86400),
        'lookup_timeout' => (int) env('ANALYTICS_GEO_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin analytics report cache
    |--------------------------------------------------------------------------
    |
    | Full dashboard aggregates are expensive on large analytics_events tables.
    | Cache the computed report briefly so repeated views stay fast.
    |
    */
    'admin_report_cache_ttl' => (int) env('ANALYTICS_ADMIN_CACHE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Daily rollups
    |--------------------------------------------------------------------------
    |
    | When enabled, the admin analytics dashboard prefers pre-aggregated daily
    | tables. Run `php artisan analytics:rollup` after deploy to backfill.
    |
    */
    'rollup_enabled' => (bool) env('ANALYTICS_ROLLUP_ENABLED', true),
];
