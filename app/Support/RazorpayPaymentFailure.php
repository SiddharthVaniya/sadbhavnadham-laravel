<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\PaymentEvent;
use Illuminate\Support\Collection;

class RazorpayPaymentFailure
{
    /**
     * @var array<int, array{label: string, detail: ?string}|null>
     */
    private static array $cacheByOrderId = [];

    /**
     * Short labels for Razorpay payment.failed `error_reason` values.
     * Covers cards, UPI, netbanking, wallets, and gateway failures.
     *
     * @var array<string, string>
     */
    private const REASONS = [
        'amount_less_than_minimum_amount' => 'Amount too low',
        'authentication_failed' => 'Authentication failed',
        'authorisation_declined_by_psp' => 'UPI app declined',
        'bank_account_invalid' => 'Invalid bank account',
        'bank_account_validation_failed' => 'Bank account check failed',
        'bank_cutoff_in_progress' => 'Bank cutoff',
        'bank_not_available' => 'Bank unavailable',
        'bank_not_enabled' => 'Bank not enabled',
        'bank_technical_error' => 'Bank error',
        'beneficiary_account_does_not_exist' => 'Beneficiary account missing',
        'beneficiary_account_dormant' => 'Beneficiary account dormant',
        'capture_failed' => 'Capture failed',
        'card_declined' => 'Card declined',
        'card_disabled_for_online_payments' => 'Card disabled online',
        'card_expired' => 'Card expired',
        'card_network_not_enabled' => 'Card network not enabled',
        'card_not_enrolled' => 'Card not enrolled',
        'card_number_invalid' => 'Invalid card number',
        'card_type_invalid' => 'Invalid card type',
        'collect_on_mcc_blocked' => 'UPI collect blocked',
        'collect_request_pending' => 'UPI collect pending',
        'compliance_violation' => 'Compliance check failed',
        'credit_failed' => 'Credit failed',
        'credit_limit_exceeded' => 'Credit limit exceeded',
        'credit_limit_expired' => 'Credit limit expired',
        'credit_limit_inactive' => 'Credit limit inactive',
        'credit_limit_not_approved' => 'Credit limit not approved',
        'credit_not_permitted' => 'Credit not allowed',
        'debit_declined' => 'Debit declined',
        'debit_instrument_blocked' => 'Card blocked',
        'debit_instrument_inactive' => 'Card inactive',
        'deemed_transaction' => 'UPI deemed failure',
        'duplicate_request' => 'Duplicate request',
        'duplicate_rrn_found' => 'Duplicate bank reference',
        'emi_greater_than_max_amount' => 'EMI amount too high',
        'emi_plan_unavailable' => 'EMI plan unavailable',
        'funds_blocked_by_mandate' => 'Funds blocked by mandate',
        'gateway_technical_error' => 'Gateway error',
        'incorrect_atm_pin' => 'Incorrect ATM PIN',
        'incorrect_card_details' => 'Invalid card',
        'incorrect_card_expiry_date' => 'Incorrect expiry',
        'incorrect_cardholder_name' => 'Incorrect card name',
        'incorrect_cvv' => 'Incorrect CVV',
        'incorrect_otp' => 'Incorrect OTP',
        'incorrect_pin' => 'Incorrect PIN',
        'input_validation_failed' => 'Invalid payment request',
        'insufficient_funds' => 'Insufficient funds',
        'international_transaction_not_allowed' => 'International card blocked',
        'invalid_amount' => 'Invalid amount',
        'invalid_currency' => 'Invalid currency',
        'invalid_email' => 'Invalid email',
        'invalid_mobile_number' => 'Invalid mobile number',
        'invalid_order_id' => 'Invalid order',
        'invalid_response_from_gateway' => 'Gateway error',
        'invalid_user_details' => 'Invalid donor details',
        'invalid_vpa' => 'Invalid UPI ID',
        'issuer_technical_error' => 'Issuer bank error',
        'live_mode_not_enabled' => 'Live mode not enabled',
        'mandate_creation_declined' => 'Mandate declined',
        'mandate_creation_expired' => 'Mandate expired',
        'mandate_creation_failed' => 'Mandate failed',
        'mandate_creation_timeout' => 'Mandate timed out',
        'mcc_amount_limit_exceeded' => 'Amount limit exceeded',
        'merchant_not_activated' => 'Merchant not activated',
        'mobile_number_invalid' => 'Invalid mobile number',
        'order_already_paid' => 'Order already paid',
        'order_amount_mismatch' => 'Amount mismatch',
        'order_payment_method_mismatch' => 'Method mismatch',
        'otp_attempts_exceeded' => 'OTP attempts exceeded',
        'otp_expired' => 'OTP expired',
        'payment_amount_tampered' => 'Amount tampered',
        'payment_cancelled' => 'Cancelled by donor',
        'payment_collect_request_expired' => 'UPI request expired',
        'payment_declined' => 'Payment declined',
        'payment_declined_due_to_high_traffic' => 'Gateway busy',
        'payment_failed' => 'Payment failed',
        'payment_method_not_enabled' => 'Method not enabled',
        'payment_pending' => 'Payment pending',
        'payment_pending_approval' => 'Pending approval',
        'payment_risk_check_failed' => 'Risk check failed',
        'payment_session_expired' => 'Session expired',
        'payment_timed_out' => 'Timed out',
        'pin_attempts_exceeded' => 'PIN attempts exceeded',
        'pin_not_set' => 'UPI PIN not set',
        'psp_app_not_available' => 'UPI app unavailable',
        'psp_app_not_supported' => 'UPI app not supported',
        'psp_not_available' => 'UPI provider unavailable',
        'psp_not_registered' => 'UPI provider not registered',
        'request_timed_out' => 'Timed out',
        'server_error' => 'Razorpay error',
        'transaction_daily_count_exceeded' => 'Daily count exceeded',
        'transaction_daily_limit_exceeded' => 'Daily limit exceeded',
        'transaction_frequency_limit_exceeded' => 'UPI limit exceeded',
        'transaction_limit_exceeded' => 'Limit exceeded',
        'transaction_on_vpa_restricted' => 'UPI ID restricted',
        'upi_app_technical_error' => 'UPI app error',
        'upi_autopay_not_supported_on_psp' => 'UPI Autopay unsupported',
        'upi_collect_not_enabled' => 'UPI collect not enabled',
        'upi_intent_not_enabled' => 'UPI intent not enabled',
        'user_not_eligible' => 'Donor not eligible',
        'user_not_registered_for_netbanking' => 'Netbanking not registered',
        'verification_failed' => 'Verification failed',
        'vpa_resolution_failed' => 'UPI ID check failed',
    ];

    public static function clearCache(): void
    {
        self::$cacheByOrderId = [];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{
     *     error_code: ?string,
     *     error_description: ?string,
     *     error_source: ?string,
     *     error_step: ?string,
     *     error_reason: ?string
     * }
     */
    public static function fromPayment(array $payment): array
    {
        return [
            'error_code' => self::text($payment['error_code'] ?? null),
            'error_description' => self::text($payment['error_description'] ?? null),
            'error_source' => self::text($payment['error_source'] ?? $payment['source'] ?? null),
            'error_step' => self::text($payment['error_step'] ?? $payment['step'] ?? null),
            'error_reason' => self::text($payment['error_reason'] ?? $payment['reason'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function label(?array $payload): ?string
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $reason = strtolower((string) ($payload['error_reason'] ?? ''));
        $description = (string) ($payload['error_description'] ?? '');
        $short = self::phraseFromDescription($description) ?? (self::REASONS[$reason] ?? null);

        if ($short === null) {
            $short = self::humanize($reason !== '' ? $reason : (string) ($payload['error_code'] ?? ''));
        }

        if ($short === null) {
            return null;
        }

        $source = self::sourceLabel($payload['error_source'] ?? null);

        return $source !== null ? $source.' · '.$short : $short;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function detail(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $description = self::text($payload['error_description'] ?? null);

        if ($description !== null) {
            return $description;
        }

        return self::label($payload);
    }

    /**
     * @return array{label: string, detail: ?string}|null
     */
    public static function summaryFor(DonationOrder $order): ?array
    {
        if (! $order->isFailed()) {
            return null;
        }

        if (! array_key_exists($order->id, self::$cacheByOrderId)) {
            self::warm(collect([$order]));
        }

        return self::$cacheByOrderId[$order->id] ?? null;
    }

    /**
     * @param  Collection<int, DonationOrder>|iterable<int, DonationOrder>  $orders
     */
    public static function warm(iterable $orders): void
    {
        $failedIds = collect($orders)
            ->filter(fn (DonationOrder $order): bool => $order->isFailed())
            ->map(fn (DonationOrder $order): int => $order->id)
            ->unique()
            ->values();

        foreach ($failedIds as $orderId) {
            if (! array_key_exists($orderId, self::$cacheByOrderId)) {
                self::$cacheByOrderId[$orderId] = null;
            }
        }

        $missingIds = $failedIds
            ->filter(fn (int $orderId): bool => self::$cacheByOrderId[$orderId] === null && array_key_exists($orderId, self::$cacheByOrderId))
            ->all();

        if ($missingIds === []) {
            return;
        }

        $events = PaymentEvent::query()
            ->whereIn('donation_order_id', $missingIds)
            ->where('event', 'payment.failed')
            ->orderByDesc('id')
            ->get(['donation_order_id', 'payload']);

        foreach ($events as $event) {
            if (self::$cacheByOrderId[$event->donation_order_id] !== null) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $label = self::label($payload);

            if ($label === null) {
                continue;
            }

            self::$cacheByOrderId[$event->donation_order_id] = [
                'label' => $label,
                'detail' => self::detail($payload),
            ];
        }
    }

    private static function sourceLabel(mixed $source): ?string
    {
        return match (strtolower(trim((string) $source))) {
            'customer' => 'Donor',
            'business' => 'Business',
            'gateway' => 'Gateway',
            'issuer_bank', 'bank', 'issuer' => 'Bank',
            'internal', 'razorpay' => 'Razorpay',
            default => null,
        };
    }

    private static function phraseFromDescription(string $description): ?string
    {
        $text = strtolower($description);

        if ($text === '') {
            return null;
        }

        $phrases = [
            'insufficient fund' => 'Insufficient funds',
            'invalid card' => 'Invalid card',
            'incorrect card' => 'Invalid card',
            'card details' => 'Invalid card',
            'incorrect cvv' => 'Incorrect CVV',
            'wrong cvv' => 'Incorrect CVV',
            'incorrect otp' => 'Incorrect OTP',
            'invalid otp' => 'Incorrect OTP',
            'card expired' => 'Card expired',
            'expired card' => 'Card expired',
            'invalid vpa' => 'Invalid UPI ID',
            'incorrect vpa' => 'Invalid UPI ID',
            'invalid upi' => 'Invalid UPI ID',
            'collect request' => 'UPI request expired',
            'not registered for netbanking' => 'Netbanking not registered',
            'not registered for net banking' => 'Netbanking not registered',
            'bank technical' => 'Bank error',
            'gateway technical' => 'Gateway error',
            'timed out' => 'Timed out',
            'timeout' => 'Timed out',
            'cancelled' => 'Cancelled by donor',
            'canceled' => 'Cancelled by donor',
            'daily limit' => 'Daily limit exceeded',
            'limit exceeded' => 'Limit exceeded',
        ];

        foreach ($phrases as $needle => $label) {
            if (str_contains($text, $needle)) {
                return $label;
            }
        }

        return null;
    }

    private static function humanize(string $value): ?string
    {
        $value = strtolower(trim($value));

        if ($value === '' || in_array($value, ['bad_request_error', 'gateway_error', 'server_error'], true)) {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $value));
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
