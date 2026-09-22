<?php

if (! function_exists('donation_env_hex_color')) {
    /**
     * Read a hex color from .env.
     *
     * Unquoted values like #840405 are treated as comments by dotenv, so use
     * DONATION_CERTIFICATE_NAME_COLOR="#840405" or 840405 (without #).
     */
    function donation_env_hex_color(string $key, string $default): string
    {
        $value = env($key);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($value === '') {
            return $default;
        }

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value) === 1) {
            return '#'.ltrim($value, '#');
        }

        return $value;
    }
}

return [
    // Legacy /{code}/donate/... vanity paths. Default false — use UTM query links instead.
    'staff_vanity_urls_enabled' => filter_var(env('DONATION_STAFF_VANITY_URLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Browser origins allowed to call /api/next-razorpay (Next.js on the main domain).
     * Attribution is sent in the JSON body; cookies are not shared across subdomains.
     *
     * @var list<string>
     */
    'frontend_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'DONATION_FRONTEND_ORIGINS',
            'https://sadbhavnadham.org,https://www.sadbhavnadham.org,https://dev.sadbhavnadham.org'
        ))
    ))),

    // Public Next.js site. Admin share/Meta links and optional Laravel 301s use this host.
    'public_frontend_url' => rtrim((string) env(
        'DONATION_PUBLIC_FRONTEND_URL',
        env('BRAND_WEBSITE_URL', 'https://sadbhavnadham.org')
    ), '/'),

    'redirect_public_donate_to_frontend' => filter_var(
        env('DONATION_REDIRECT_PUBLIC_DONATE_TO_FRONTEND', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'pan_threshold_inr' => (int) env('DONATION_PAN_THRESHOLD_INR', 100000),

    /**
     * Daily Excel donation report recipients (comma-separated in .env).
     * Sent at 01:00 for yesterday's donations. First address is To; rest are Cc.
     *
     * @var list<string>
     */
    'daily_report_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DONATION_DAILY_REPORT_EMAILS', ''))
    ))),

    /**
     * Causes that collect one optional dedication name per tree (quantity).
     *
     * @var list<string>
     */
    'tree_dedication_causes' => ['tree-plantation'],

    // Only re-queue missed receipt/WhatsApp/sheet jobs for recent paid donations.
    // Prevents donations:reconcile from blasting historical orders every 15 minutes.
    'reconcile_notification_retry_hours' => (int) env('DONATION_RECONCILE_NOTIFICATION_RETRY_HOURS', 48),

    // Max times reconcile may re-queue each notification channel per order.
    'reconcile_notification_max_attempts' => (int) env('DONATION_RECONCILE_NOTIFICATION_MAX_ATTEMPTS', 5),

    // Queue worker retries for a single dispatched job (AiSensy/mail/sheet API blips).
    'notification_job_tries' => (int) env('DONATION_NOTIFICATION_JOB_TRIES', 3),
    'notification_job_backoff_seconds' => (int) env('DONATION_NOTIFICATION_JOB_BACKOFF_SECONDS', 60),

    'certificate' => [
        'enabled' => env('DONATION_CERTIFICATE_ENABLED', true),
        // Gujarati sanman patra (default artwork for Gujarat donors).
        'template' => env('DONATION_CERTIFICATE_TEMPLATE', public_path('images/certificate-template.jpg')),
        // Optional shipped English artwork fallback. Prefer cause WhatsApp-tab upload
        // (`causes.certificate_template_english`) over this file.
        'template_english' => public_path('images/certificate-template-english.jpg'),
        'font' => public_path('fonts/NotoSansGujarati-Regular.ttf'),
        'font_bold' => env('DONATION_CERTIFICATE_FONT_BOLD', public_path('fonts/NotoSansGujarati-Bold.ttf')),
        'storage_directory' => 'certificates',
        'date_prefix' => 'તારીખ : ',
        'date_prefix_english' => env('DONATION_CERTIFICATE_DATE_PREFIX_ENGLISH', 'Date : '),
        'date_format' => 'd-m-Y',
        'name' => [
            'top_percent' => (float) env('DONATION_CERTIFICATE_NAME_TOP_PERCENT', 60.8),
            'size_pt' => (float) env('DONATION_CERTIFICATE_NAME_SIZE_PT', 44),
            'color' => donation_env_hex_color('DONATION_CERTIFICATE_NAME_COLOR', '#8B1538'),
            'font_weight' => env('DONATION_CERTIFICATE_NAME_FONT_WEIGHT'),
            'bold' => env('DONATION_CERTIFICATE_NAME_BOLD'),
            'min_size_pt' => (float) env('DONATION_CERTIFICATE_NAME_MIN_SIZE_PT', 32),
            'max_width_percent' => (float) env('DONATION_CERTIFICATE_NAME_MAX_WIDTH_PERCENT', 82),
        ],
        'date' => [
            // Larger value moves the date up inside the blue pill.
            'bottom_pt' => (float) env('DONATION_CERTIFICATE_DATE_BOTTOM_PT', 52),
            'box_height_pt' => (float) env('DONATION_CERTIFICATE_DATE_BOX_HEIGHT_PT', 34),
            'size_pt' => (float) env('DONATION_CERTIFICATE_DATE_SIZE_PT', 20),
            'color' => donation_env_hex_color('DONATION_CERTIFICATE_DATE_COLOR', '#ffffff'),
        ],
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', base_path('tools/poppler/poppler-24.08.0/Library/bin/pdftoppm.exe')),
        'public_base_url' => env('DONATION_CERTIFICATE_PUBLIC_BASE_URL', env('APP_URL', 'https://donate.sadbhavnadham.org')),
        'render_dpi' => (int) env('DONATION_CERTIFICATE_RENDER_DPI', 220),
        'whatsapp_max_width' => (int) env('DONATION_CERTIFICATE_WHATSAPP_MAX_WIDTH', 1920),
        'whatsapp_jpeg_quality' => (int) env('DONATION_CERTIFICATE_WHATSAPP_JPEG_QUALITY', 94),
        'whatsapp_max_bytes' => (int) env('DONATION_CERTIFICATE_WHATSAPP_MAX_BYTES', 4_500_000),
        'whatsapp_prefer_png' => env('DONATION_CERTIFICATE_WHATSAPP_PREFER_PNG', true),
    ],

    'birthday' => [
        'enabled' => env('DONATION_BIRTHDAY_ENABLED', true),
        'template' => env('DONATION_BIRTHDAY_TEMPLATE', public_path('images/birthday-template.jpg')),
        'font' => env('DONATION_BIRTHDAY_FONT', public_path('fonts/Poppins-Bold.ttf')),
        'font_regular' => env('DONATION_BIRTHDAY_FONT_REGULAR', public_path('fonts/Poppins-Regular.ttf')),
        'font_bold' => env('DONATION_BIRTHDAY_FONT_BOLD', public_path('fonts/Poppins-Bold.ttf')),
        'font_gujarati' => env('DONATION_BIRTHDAY_FONT_GUJARATI', public_path('fonts/NotoSansGujarati-Bold.ttf')),
        'font_gujarati_regular' => env('DONATION_BIRTHDAY_FONT_GUJARATI_REGULAR', public_path('fonts/NotoSansGujarati-Regular.ttf')),
        'font_devanagari' => env('DONATION_BIRTHDAY_FONT_DEVANAGARI', public_path('fonts/NotoSansDevanagari-Bold.ttf')),
        'font_devanagari_regular' => env('DONATION_BIRTHDAY_FONT_DEVANAGARI_REGULAR', public_path('fonts/NotoSansDevanagari-Regular.ttf')),
        'storage_directory' => 'birthdays',
        'public_base_url' => env('DONATION_BIRTHDAY_PUBLIC_BASE_URL', env('DONATION_CERTIFICATE_PUBLIC_BASE_URL', env('APP_URL', 'https://donate.sadbhavnadham.org'))),
        'name' => [
            // Just above the template gold divider (acts as name underline). Keep clear of "Happy Birthday" descenders.
            'top_percent' => (float) env('DONATION_BIRTHDAY_NAME_TOP_PERCENT', 42.8),
            'size_pt' => (float) env('DONATION_BIRTHDAY_NAME_SIZE_PT', 36),
            'color' => donation_env_hex_color('DONATION_BIRTHDAY_NAME_COLOR', '#C4A035'),
            'min_size_pt' => (float) env('DONATION_BIRTHDAY_NAME_MIN_SIZE_PT', 24),
            'max_width_percent' => (float) env('DONATION_BIRTHDAY_NAME_MAX_WIDTH_PERCENT', 78),
            'uppercase' => filter_var(env('DONATION_BIRTHDAY_NAME_UPPERCASE', false), FILTER_VALIDATE_BOOLEAN),
            'underline' => filter_var(env('DONATION_BIRTHDAY_NAME_UNDERLINE', false), FILTER_VALIDATE_BOOLEAN),
        ],
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', base_path('tools/poppler/poppler-24.08.0/Library/bin/pdftoppm.exe')),
        'render_dpi' => (int) env('DONATION_BIRTHDAY_RENDER_DPI', 220),
        'whatsapp_max_width' => (int) env('DONATION_BIRTHDAY_WHATSAPP_MAX_WIDTH', 1920),
        'whatsapp_jpeg_quality' => (int) env('DONATION_BIRTHDAY_WHATSAPP_JPEG_QUALITY', 94),
        'whatsapp_max_bytes' => (int) env('DONATION_BIRTHDAY_WHATSAPP_MAX_BYTES', 4_500_000),
        'whatsapp_prefer_png' => env('DONATION_BIRTHDAY_WHATSAPP_PREFER_PNG', true),
    ],

    'otp' => [
        'length' => (int) env('DONATION_OTP_LENGTH', 6),
        'ttl_seconds' => (int) env('DONATION_OTP_TTL_SECONDS', 300),
        'resend_seconds' => (int) env('DONATION_OTP_RESEND_SECONDS', 60),
        'max_attempts' => (int) env('DONATION_OTP_MAX_ATTEMPTS', 5),
        'daily_send_limit' => (int) env('DONATION_OTP_DAILY_SEND_LIMIT', 10),
    ],
];
