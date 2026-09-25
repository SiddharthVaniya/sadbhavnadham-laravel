<?php

return [
    'enabled' => (bool) env('GEOIP_ENABLED', true),

    'city_mmdb' => env('GEOIP_CITY_MMDB', storage_path('geoip/dbip-city-lite.mmdb')),

    'asn_mmdb' => env('GEOIP_ASN_MMDB', storage_path('geoip/dbip-asn-lite.mmdb')),

    'cache_store' => env('GEOIP_CACHE_STORE', 'redis'),

    'cache_ttl' => (int) env('GEOIP_CACHE_TTL', 86400),

    'cache_prefix' => env('GEOIP_CACHE_PREFIX', 'geoip:v1:'),

    /*
    |--------------------------------------------------------------------------
    | Monthly Lite database downloads (DB-IP free editions)
    |--------------------------------------------------------------------------
    |
    | URLs use {YYYY-MM}. Override in .env if DB-IP changes their CDN paths.
    |
    */
    'city_download_url' => env(
        'GEOIP_CITY_DOWNLOAD_URL',
        'https://download.db-ip.com/free/dbip-city-lite-{YYYY-MM}.mmdb.gz'
    ),

    'asn_download_url' => env(
        'GEOIP_ASN_DOWNLOAD_URL',
        'https://download.db-ip.com/free/dbip-asn-lite-{YYYY-MM}.mmdb.gz'
    ),
];
