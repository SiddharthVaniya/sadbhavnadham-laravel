<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Danamojo API
    |--------------------------------------------------------------------------
    |
    | Pull verified donations from Danamojo into donation_orders so admins do
    | not need to re-enter them as offline donations.
    |
    */

    'enabled' => (bool) env('DANAMOJO_SYNC_ENABLED', true),

    'base_url' => rtrim((string) env('DANAMOJO_API_BASE_URL', 'https://api.danamojo.org'), '/'),

    'api_key_secret' => env('DANAMOJO_API_KEY_SECRET'),

    'timeout' => (int) env('DANAMOJO_API_TIMEOUT', 30),

    /*
    | Page size for /donation/v1.0/details (API allows 0–100).
    */
    'page_size' => (int) env('DANAMOJO_PAGE_SIZE', 100),

    /*
    | How many days back the scheduled sync looks (overlapping window avoids gaps).
    */
    'lookback_days' => (int) env('DANAMOJO_LOOKBACK_DAYS', 14),

    /*
    | Map Danamojo donationProductName → local cause slug.
    | Unmapped products fall back to title/slug fuzzy match, then default cause.
    */
    'product_cause_map' => [
        // 'Tree Plantation' => 'tree-plantation',
    ],

    'default_cause_slug' => env('DANAMOJO_DEFAULT_CAUSE_SLUG'),

    /*
    | Side effects when a new verified donation is imported.
    | Danamojo already emails its own receipt — keep our email/WA off by default.
    */
    'queue_sheet_on_import' => (bool) env('DANAMOJO_QUEUE_SHEET', true),

    'send_receipt_email_on_import' => (bool) env('DANAMOJO_SEND_RECEIPT_EMAIL', false),

    'send_whatsapp_on_import' => (bool) env('DANAMOJO_SEND_WHATSAPP', false),

    /*
    | Only these paymentStatus values are imported as paid.
    | Full Danamojo enum: Failed, Pending, Verified, Credited, Refunded,
    | Payout On-Hold, NGO Receipted, Subscription Activated, Bank Approval Pending.
    */
    'verified_statuses' => [
        'Verified',
        'Credited',
        'NGO Receipted',
        'Payout On-Hold',
        'Subscription Activated',
    ],

];
