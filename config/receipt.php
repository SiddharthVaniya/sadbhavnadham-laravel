<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Receipt asset base URL
    |--------------------------------------------------------------------------
    |
    | Public base URL for receipt email/preview images. Defaults to APP_URL.
    | Override with RECEIPT_ASSET_BASE_URL if assets are served elsewhere.
    |
    */
    'asset_base_url' => env('RECEIPT_ASSET_BASE_URL', env('APP_URL', 'https://donate.sadbhavnadham.org')),

    /**
     * Public Blade view for HTML preview and PDF receipts.
     * Legacy Gujarati layout: receipts.donation
     */
    'view' => env('RECEIPT_VIEW', 'receipts.donation-minimal'),

    'storage_directory' => env('RECEIPT_STORAGE_DIRECTORY', 'receipts'),

    'public_base_url' => env('RECEIPT_PUBLIC_BASE_URL', env('APP_URL', 'https://donate.sadbhavnadham.org')),

    'images' => [
        'favicon' => env('BRAND_FAVICON', '/assets/img/logo/favicon.png'),
        'logo' => env('BRAND_LOGO', '/assets/img/logo/logo-black.png'),
        'photo1' => '/images/reciept/old-age.webp',
        'photo2' => '/images/reciept/old-age-2.jpg',
        'photo3' => '/images/reciept/bull.jpg',
        'photo4' => '/images/reciept/dog-shelter.jpg',
        'photo5' => '/images/reciept/bird.webp',
    ],
];
