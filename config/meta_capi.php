<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta Conversions API (server-side)
    |--------------------------------------------------------------------------
    |
    | Pixel IDs and access tokens live in .env (never commit real tokens).
    | Admin → Meta → Pixels shows env-backed pixels as read-only.
    |
    */

    'enabled' => filter_var(env('META_CAPI_ENABLED', true), FILTER_VALIDATE_BOOL),

    /**
     * When true, extra pixels saved in the database (with DB-stored tokens) are
     * also used. Env-defined pixel IDs always win for credentials.
     */
    'allow_database_pixels' => filter_var(env('META_CAPI_ALLOW_DATABASE_PIXELS', false), FILTER_VALIDATE_BOOL),

    'pixels' => [
        [
            'label' => env('META_CAPI_PIXEL_1_LABEL', 'Primary pixel'),
            'pixel_id' => env('META_CAPI_PIXEL_1_ID'),
            'access_token' => env('META_CAPI_PIXEL_1_TOKEN'),
            'is_active' => filter_var(env('META_CAPI_PIXEL_1_ACTIVE', true), FILTER_VALIDATE_BOOL),
            'send_purchase' => filter_var(env('META_CAPI_PIXEL_1_SEND_PURCHASE', true), FILTER_VALIDATE_BOOL),
            'send_initiate_checkout' => filter_var(env('META_CAPI_PIXEL_1_SEND_INITIATE_CHECKOUT', true), FILTER_VALIDATE_BOOL),
            'test_event_code' => env('META_CAPI_PIXEL_1_TEST_EVENT_CODE'),
        ],
        [
            'label' => env('META_CAPI_PIXEL_2_LABEL', 'Secondary pixel'),
            'pixel_id' => env('META_CAPI_PIXEL_2_ID'),
            'access_token' => env('META_CAPI_PIXEL_2_TOKEN'),
            'is_active' => filter_var(env('META_CAPI_PIXEL_2_ACTIVE', true), FILTER_VALIDATE_BOOL),
            'send_purchase' => filter_var(env('META_CAPI_PIXEL_2_SEND_PURCHASE', true), FILTER_VALIDATE_BOOL),
            'send_initiate_checkout' => filter_var(env('META_CAPI_PIXEL_2_SEND_INITIATE_CHECKOUT', true), FILTER_VALIDATE_BOOL),
            'test_event_code' => env('META_CAPI_PIXEL_2_TEST_EVENT_CODE'),
        ],
    ],

];
