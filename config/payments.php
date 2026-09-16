<?php

return [
    'providers' => [
        'razorpay',
        'danamojo',
    ],

    'statuses' => [
        'pending',
        'paid',
        'failed',
        'refunded',
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('WEBHOOK_SECRET'),
        // Comma-separated Razorpay QR ids (qr_xxx). Empty = accept any QR payment.
        'qr_code_ids' => array_values(array_filter(array_map(
            static fn (string $id): string => trim($id),
            explode(',', (string) env('RAZORPAY_QR_IDS', ''))
        ))),
        'subscriptions_enabled' => env('RAZORPAY_SUBSCRIPTIONS_ENABLED', false),
        // Enabled frequencies for checkout. Campaigns currently use monthly + daily.
        'subscription_frequencies' => ['monthly', 'weekly'],
        'subscription_min_amount' => (float) env('RAZORPAY_SUBSCRIPTION_MIN_AMOUNT', 100),
        'subscription_max_amount' => (float) env('RAZORPAY_SUBSCRIPTION_MAX_AMOUNT', 15000),
        // Razorpay allows subscription validity up to 30 years from today.
        'subscription_max_validity_years' => (int) env('RAZORPAY_SUBSCRIPTION_MAX_VALIDITY_YEARS', 30),
        // Billing cycles when no end date is set (360 = 30 years monthly).
        // Daily defaults use max validity instead (see RazorpaySubscriptionService).
        'subscription_total_count' => (int) env('RAZORPAY_SUBSCRIPTION_TOTAL_COUNT', 360),
        // Razorpay hard limit on subscription billing cycles.
        'subscription_max_total_count' => (int) env('RAZORPAY_SUBSCRIPTION_MAX_TOTAL_COUNT', 10000),
    ],
];
