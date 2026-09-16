<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://sadbhavnadham.org',
        'https://www.sadbhavnadham.org',
        'https://dev.sadbhavnadham.org',
        'https://madspire.site',
        'https://www.madspire.site',
        'https://admin.sadbhavnadham.org',
        'https://admin.madspire.site',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://195.35.23.88:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
