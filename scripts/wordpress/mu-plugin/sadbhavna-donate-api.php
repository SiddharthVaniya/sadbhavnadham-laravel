<?php

/**
 * Plugin Name: Sadbhavna Donate API Proxy
 * Description: Proxies WordPress donation checkouts to Laravel /api/wp-razorpay without exposing the API token in the browser.
 * Author: Sadbhavna
 * Version: 1.0.0
 *
 * Install: copy this file to wp-content/mu-plugins/sadbhavna-donate-api.php
 *
 * wp-config.php:
 *   define('SADBHAVNA_DONATE_API_URL', 'https://donate.sadbhavnadham.org/api/wp-razorpay');
 *   define('SADBHAVNA_DONATE_API_TOKEN', 'same-as-laravel-WP_API_TOKEN');
 *   define('SADBHAVNA_DONATE_BRAND_NAME', 'Sadbhavna');
 */
if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function (): void {
    if (is_admin()) {
        return;
    }

    $scriptPath = __DIR__.'/assets/sadbhavna-forminator-donate.js';
    $scriptUrl = plugin_dir_url(__FILE__).'assets/sadbhavna-forminator-donate.js';

    if (is_readable($scriptPath)) {
        wp_enqueue_script(
            'sadbhavna-forminator-donate',
            $scriptUrl,
            [],
            (string) filemtime($scriptPath),
            true
        );
    } else {
        wp_register_script('sadbhavna-forminator-donate', false, [], '1.0.0', true);
        wp_enqueue_script('sadbhavna-forminator-donate');
    }

    wp_localize_script('sadbhavna-forminator-donate', 'SadbhavnaDonate', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sadbhavna_donate'),
        'brandName' => defined('SADBHAVNA_DONATE_BRAND_NAME')
            ? (string) SADBHAVNA_DONATE_BRAND_NAME
            : 'Sadbhavna',
        'panThreshold' => 100000,
    ]);
});

add_action('wp_ajax_sadbhavna_create_donation', 'sadbhavna_create_donation');
add_action('wp_ajax_nopriv_sadbhavna_create_donation', 'sadbhavna_create_donation');
add_action('wp_ajax_sadbhavna_pan_requirement', 'sadbhavna_pan_requirement');
add_action('wp_ajax_nopriv_sadbhavna_pan_requirement', 'sadbhavna_pan_requirement');

/**
 * Forward mapped donation payload to Laravel.
 */
function sadbhavna_create_donation(): void
{
    if (! check_ajax_referer('sadbhavna_donate', 'nonce', false)) {
        wp_send_json(['message' => 'Invalid security token. Please refresh and try again.'], 403);
    }

    $apiUrl = sadbhavna_donate_api_url();
    $apiToken = sadbhavna_donate_api_token();

    if ($apiUrl === '' || $apiToken === '') {
        wp_send_json([
            'message' => 'Donation API is not configured. Set SADBHAVNA_DONATE_API_URL and SADBHAVNA_DONATE_API_TOKEN in wp-config.php.',
        ], 500);
    }

    $raw = isset($_POST['payload']) ? wp_unslash((string) $_POST['payload']) : '';
    $payload = json_decode($raw, true);

    if (! is_array($payload)) {
        wp_send_json(['message' => 'Invalid donation payload.'], 422);
    }

    $payload = sadbhavna_normalize_donation_payload($payload);

    $response = wp_remote_post($apiUrl, [
        'timeout' => 45,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-WP-TOKEN' => $apiToken,
        ],
        'body' => wp_json_encode($payload),
    ]);

    sadbhavna_forward_laravel_json_response($response);
}

/**
 * Same PAN rules as Laravel donate site (₹1,00,000 + FY total).
 */
function sadbhavna_pan_requirement(): void
{
    if (! check_ajax_referer('sadbhavna_donate', 'nonce', false)) {
        wp_send_json(['message' => 'Invalid security token. Please refresh and try again.'], 403);
    }

    $apiUrl = sadbhavna_pan_requirement_api_url();
    $apiToken = sadbhavna_donate_api_token();

    if ($apiUrl === '' || $apiToken === '') {
        wp_send_json([
            'message' => 'Donation API is not configured.',
        ], 500);
    }

    $raw = isset($_POST['payload']) ? wp_unslash((string) $_POST['payload']) : '';
    $payload = json_decode($raw, true);

    if (! is_array($payload)) {
        wp_send_json(['message' => 'Invalid payload.'], 422);
    }

    $body = [
        'cause' => isset($payload['cause']) ? trim((string) $payload['cause']) : '',
        'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
        'quantity' => isset($payload['quantity']) ? (int) $payload['quantity'] : 1,
        'package_id' => isset($payload['package_id']) && $payload['package_id'] !== ''
            ? (int) $payload['package_id']
            : null,
        'donor_email' => isset($payload['donor_email']) ? strtolower(trim((string) $payload['donor_email'])) : '',
        'donor_phone' => isset($payload['donor_phone'])
            ? preg_replace('/\D+/', '', (string) $payload['donor_phone'])
            : '',
    ];

    if (is_string($body['donor_phone']) && strlen($body['donor_phone']) > 10) {
        $body['donor_phone'] = substr($body['donor_phone'], -10);
    }

    $response = wp_remote_post($apiUrl, [
        'timeout' => 20,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-WP-TOKEN' => $apiToken,
        ],
        'body' => wp_json_encode(array_filter(
            $body,
            static fn ($value) => $value !== null && $value !== ''
        )),
    ]);

    sadbhavna_forward_laravel_json_response($response);
}

function sadbhavna_donate_api_url(): string
{
    return defined('SADBHAVNA_DONATE_API_URL') ? trim((string) SADBHAVNA_DONATE_API_URL) : '';
}

function sadbhavna_donate_api_token(): string
{
    $token = defined('SADBHAVNA_DONATE_API_TOKEN') ? trim((string) SADBHAVNA_DONATE_API_TOKEN) : '';

    if ($token === '' || $token === 'YOUR_WP_API_TOKEN_HERE') {
        return '';
    }

    return $token;
}

function sadbhavna_pan_requirement_api_url(): string
{
    if (defined('SADBHAVNA_DONATE_PAN_URL') && trim((string) SADBHAVNA_DONATE_PAN_URL) !== '') {
        return trim((string) SADBHAVNA_DONATE_PAN_URL);
    }

    $donateUrl = sadbhavna_donate_api_url();
    if ($donateUrl === '') {
        return '';
    }

    if (str_contains($donateUrl, '/wp-razorpay')) {
        return str_replace('/wp-razorpay', '/wp-pan-requirement', $donateUrl);
    }

    return 'https://donate.sadbhavnadham.org/api/wp-pan-requirement';
}

/**
 * @param  array<string, mixed>|\WP_Error  $response
 */
function sadbhavna_forward_laravel_json_response($response): void
{
    if (is_wp_error($response)) {
        wp_send_json([
            'message' => 'Could not reach donation server: '.$response->get_error_message(),
        ], 502);
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (! is_array($data)) {
        wp_send_json([
            'message' => 'Unexpected response from donation server.',
            'raw' => $body,
        ], $status >= 400 ? $status : 502);
    }

    if ($status >= 400) {
        $message = sadbhavna_first_error_message($data) ?? 'Request failed. Please check the form and try again.';
        wp_send_json([
            'message' => $message,
            'errors' => $data['errors'] ?? $data,
        ], $status);
    }

    wp_send_json($data, 200);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function sadbhavna_normalize_donation_payload(array $payload): array
{
    $bool = static function (mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'accepted'], true);
    };

    $string = static function (mixed $value): string {
        return trim((string) $value);
    };

    $phone = preg_replace('/\D+/', '', $string($payload['donor_phone'] ?? '')) ?? '';
    if (strlen($phone) > 10) {
        $phone = substr($phone, -10);
    }

    $pan = strtoupper($string($payload['pan_number'] ?? ''));

    $out = [
        'cause' => $string($payload['cause'] ?? ''),
        'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
        'quantity' => isset($payload['quantity']) ? (int) $payload['quantity'] : 1,
        'package_id' => isset($payload['package_id']) && $payload['package_id'] !== ''
            ? (int) $payload['package_id']
            : null,
        'donor_name' => $string($payload['donor_name'] ?? ''),
        'donor_email' => strtolower($string($payload['donor_email'] ?? '')),
        'donor_phone' => $phone,
        'address' => $string($payload['address'] ?? ''),
        'pincode' => $string($payload['pincode'] ?? ''),
        'city' => $string($payload['city'] ?? ''),
        'state' => $string($payload['state'] ?? ''),
        'country' => $string($payload['country'] ?? '') ?: 'INDIA',
        'donor_country' => strtoupper($string($payload['donor_country'] ?? '') ?: 'IN'),
        'consent_indian_citizen' => $bool($payload['consent_indian_citizen'] ?? false),
        'pan_number' => $pan !== '' ? $pan : null,
        'date_of_birth' => $string($payload['date_of_birth'] ?? '') ?: null,
        'title' => $string($payload['title'] ?? '') ?: null,
        'campaign_slug' => $string($payload['campaign_slug'] ?? '') ?: null,
        'source_channel' => 'wordpress',
        'utm_source' => $string($payload['utm_source'] ?? '') ?: 'wordpress',
        'utm_medium' => $string($payload['utm_medium'] ?? '') ?: 'website',
        'utm_campaign' => $string($payload['utm_campaign'] ?? '') ?: null,
        'utm_content' => $string($payload['utm_content'] ?? '') ?: null,
        'landing_path' => $string($payload['landing_path'] ?? '') ?: null,
    ];

    return array_filter(
        $out,
        static fn ($value) => $value !== null && $value !== ''
    );
}

/**
 * @param  array<string, mixed>  $data
 */
function sadbhavna_first_error_message(array $data): ?string
{
    if (isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
        return $data['message'];
    }

    if (! isset($data['errors']) || ! is_array($data['errors'])) {
        return null;
    }

    foreach ($data['errors'] as $messages) {
        if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
            return $messages[0];
        }

        if (is_string($messages) && $messages !== '') {
            return $messages;
        }
    }

    return null;
}
