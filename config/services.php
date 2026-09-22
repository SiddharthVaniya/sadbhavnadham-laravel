<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'sheet_id' => env('GOOGLE_SHEET_ID'),
        'failed_sheet_id' => env('GOOGLE_FAILED_SHEET_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'aisensy' => [

        'endpoint' => env('AISENSY_CAMPAIGN_ENDPOINT', 'https://backend.aisensy.com/campaign/t1/api/v2'),
        'project_api_base' => env('AISENSY_PROJECT_API_BASE', 'https://apis.aisensy.com/project-apis/v1'),
        'project_templates_path' => env('AISENSY_PROJECT_TEMPLATES_PATH'),
        'campaign_max_audience' => (int) env('AISENSY_CAMPAIGN_MAX_AUDIENCE', 10000),
        'campaign_rate_per_minute' => (int) env('AISENSY_CAMPAIGN_RATE_PER_MINUTE', 60),

        'default' => [
            'key' => env('AISENSY_API_KEY'),
            'country_code' => '91',

            // payment_failed_retry_payment — Hi {{1}}, Order {{2}}, Amount ₹{{3}}, link {{4}}
            'payment_link_campaign' => env('AISENSY_PAYMENT_LINK_CAMPAIGN', 'payment_failed_retry_payment'),

            // certificate_of_donation_old_age_home_new — IMAGE header, 0 body params
            'certificate_campaign' => env('AISENSY_CERTIFICATE_CAMPAIGN', 'certificate_of_donation_old_age_home_new'),

            'thank_you_general_campaign' => env('AISENSY_THANKYOU_GENERAL'),
            'thank_you_general_image' => env('AISENSY_THANKYOU_GENERAL_IMAGE'),

            'thank_you_vrudhhashram_campaign' => env('AISENSY_THANKYOU_VRUDHHASHRAM'),
        ],

        'tree' => [
            'key' => env('AISENSY_TREE_API_KEY'),
            'country_code' => '91',

            'thank_you_tree_campaign' => env('AISENSY_THANKYOU_TREE'),
            'thank_you_tree_image' => env('AISENSY_THANKYOU_TREE_IMAGE'),
        ],

        'otp' => [
            'key' => env('AISENSY_OTP_API_KEY'),
            'campaign' => env('AISENSY_OTP_CAMPAIGN'),
            'country_code' => env('AISENSY_OTP_COUNTRY_CODE', '91'),
            'source' => env('AISENSY_OTP_SOURCE', 'donate website login'),
        ],

        'receipt_campaign' => env('AISENSY_RECEIPT_CAMPAIGN', 'donation_receipt_pdf'),
        'receipt_source' => env('AISENSY_RECEIPT_SOURCE', 'donate website receipt'),
        'certificate_source' => env('AISENSY_CERTIFICATE_SOURCE', 'donate website certificate'),
    ],

];
