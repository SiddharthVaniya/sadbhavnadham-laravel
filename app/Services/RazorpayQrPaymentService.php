<?php

namespace App\Services;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\PaymentEvent;
use App\Models\RazorpayQrCode;
use Illuminate\Support\Facades\Log;

class RazorpayQrPaymentService
{
    /**
     * Whether this unmatched Razorpay payment should become a donation row.
     *
     * @param  array<string, mixed>  $payment
     */
    public function shouldAutoCreate(array $payment, ?string $qrCodeId = null): bool
    {
        $qrCodeId = $this->resolveQrCodeId($payment, $qrCodeId);

        if ($qrCodeId === null || $qrCodeId === '') {
            return false;
        }

        $allowedIds = $this->configuredQrCodeIds();

        if ($allowedIds === []) {
            return true;
        }

        return in_array($qrCodeId, $allowedIds, true);
    }

    /**
     * Create a paid offline-style donation from a Razorpay QR payment.
     * Caller must run inside a DB transaction and handle post-payment jobs.
     *
     * @param  array<string, mixed>  $payment
     */
    public function createPaidOrderFromPayment(array $payment, ?string $qrCodeId = null): ?DonationOrder
    {
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId === '') {
            Log::warning('Razorpay QR payment missing id; cannot auto-create donation', $payment);

            return null;
        }

        $existing = DonationOrder::query()
            ->where('provider_payment_id', $paymentId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $qrCodeId = $this->resolveQrCodeId($payment, $qrCodeId);

        if (! $this->shouldAutoCreate($payment, $qrCodeId)) {
            Log::info('Skipping Razorpay QR auto-create (not a known QR payment)', [
                'payment_id' => $paymentId,
                'qr_code_id' => $qrCodeId,
            ]);

            return null;
        }

        $amountPaise = (int) ($payment['amount'] ?? 0);
        $amount = round($amountPaise / 100, 2);

        if ($amount < 1) {
            Log::warning('Razorpay QR payment amount too low; skipping', [
                'payment_id' => $paymentId,
                'amount_paise' => $amountPaise,
            ]);

            return null;
        }

        $donorSnapshot = $this->donorSnapshotFromPayment($payment);
        $donor = Donor::resolveFromDonationSnapshot($donorSnapshot);

        $paidAt = DonationPaymentService::resolvePaymentCapturedAt($payment);

        $order = DonationOrder::query()->create([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
            'source_channel' => DonationAttributionService::CHANNEL_RAZORPAY_QR,
            'provider_order_id' => 'qr-'.$paymentId,
            'provider_payment_id' => $paymentId,
            'donor_id' => $donor->id,
            'donor_name' => $donorSnapshot['donor_name'],
            'donor_email' => $donorSnapshot['donor_email'],
            'donor_phone' => $donorSnapshot['donor_phone'],
            'address' => $donorSnapshot['address'],
            'pincode' => $donorSnapshot['pincode'],
            'city' => $donorSnapshot['city'],
            'state' => $donorSnapshot['state'],
            'country' => $donorSnapshot['country'],
            'donor_country_code' => $donorSnapshot['donor_country_code'],
            'consent_indian_citizen' => true,
            'currency' => strtoupper((string) ($payment['currency'] ?? 'INR')),
            'total_amount' => $amount,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        $this->attachMappedCauseItem($order, $qrCodeId, $amount);

        PaymentEvent::query()->create([
            'donation_order_id' => $order->id,
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
            'event' => 'payment.captured',
            'provider_payment_id' => $paymentId,
            'amount' => $amount,
            'payload' => [
                'payment' => $payment,
                'qr_code_id' => $qrCodeId,
            ],
            'created_at' => now(),
        ]);

        Log::info('Auto-created donation from Razorpay QR payment', [
            'donation_order_id' => $order->id,
            'payment_id' => $paymentId,
            'qr_code_id' => $qrCodeId,
            'amount' => $amount,
        ]);

        return $order;
    }

    private function attachMappedCauseItem(DonationOrder $order, ?string $qrCodeId, float $amount): void
    {
        if ($qrCodeId === null || $qrCodeId === '') {
            return;
        }

        $qr = RazorpayQrCode::query()
            ->with(['cause:id,title,slug', 'package:id,title,amount'])
            ->where('razorpay_qr_code_id', $qrCodeId)
            ->first();

        if (! $qr?->cause_id || ! $qr->cause) {
            return;
        }

        $title = $qr->package?->title
            ?: ($qr->name ?: 'QR Donation');

        DonationItem::query()->create([
            'donation_order_id' => $order->id,
            'cause_id' => $qr->cause_id,
            'cause_package_id' => $qr->cause_package_id,
            'cause' => $qr->cause->slug ?: $qr->cause->title,
            'title' => $title,
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'meta' => [
                'cause_title' => $qr->cause->title,
                'cause_slug' => $qr->cause->slug,
                'razorpay_qr_code_id' => $qr->razorpay_qr_code_id,
                'qr_uuid' => $qr->qr_uuid,
            ],
        ]);
    }

    /**
     * Whitelist for webhook auto-create.
     * Empty env = accept any QR (legacy default).
     * Non-empty env = env ids + admin Active ids (env acts as optional fallback/extra list).
     *
     * @return list<string>
     */
    public function configuredQrCodeIds(): array
    {
        $fromEnv = $this->envQrCodeIds();

        if ($fromEnv === []) {
            return [];
        }

        return array_values(array_unique([...$fromEnv, ...$this->localActiveQrCodeIds()]));
    }

    /**
     * Ids to poll during donations:reconcile.
     * Primary: admin Active QRs. Fallback/extra: RAZORPAY_QR_IDS from .env.
     *
     * @return list<string>
     */
    public function reconcileQrCodeIds(): array
    {
        return array_values(array_unique([
            ...$this->localActiveQrCodeIds(),
            ...$this->envQrCodeIds(),
        ]));
    }

    /**
     * @return list<string>
     */
    public function envQrCodeIds(): array
    {
        $configured = config('payments.razorpay.qr_code_ids', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            $configured
        )));
    }

    /**
     * @return list<string>
     */
    public function localActiveQrCodeIds(): array
    {
        return RazorpayQrCode::query()
            ->active()
            ->pluck('razorpay_qr_code_id')
            ->map(static fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    public function resolveQrCodeId(array $payment, ?string $qrCodeId = null): ?string
    {
        if (is_string($qrCodeId) && $qrCodeId !== '') {
            return $qrCodeId;
        }

        $fromPayment = $payment['qr_code_id'] ?? null;

        return is_string($fromPayment) && $fromPayment !== '' ? $fromPayment : null;
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{donor_name: string, donor_email: string, donor_phone: string, address: null, pincode: null, city: null, state: null, country: string, donor_country_code: string}
     */
    private function donorSnapshotFromPayment(array $payment): array
    {
        $name = trim((string) (
            $payment['notes']['donor_name']
            ?? $payment['notes']['name']
            ?? ''
        ));

        if ($name === '') {
            $name = 'Unknown Donor';
        }

        $email = mb_strtolower(trim((string) ($payment['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $phone = preg_replace('/\D+/', '', (string) ($payment['contact'] ?? '')) ?? '';
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        if ($phone === '') {
            $vpa = trim((string) ($payment['vpa'] ?? ''));
            $phone = $vpa !== ''
                ? 'upi-'.substr(md5($vpa), 0, 10)
                : 'u-'.substr(md5((string) ($payment['id'] ?? uniqid())), 0, 12);
        }

        return [
            'donor_name' => $name,
            'donor_email' => $email,
            'donor_phone' => $phone,
            'address' => null,
            'pincode' => null,
            'city' => null,
            'state' => null,
            'country' => 'INDIA',
            'donor_country_code' => 'IN',
        ];
    }
}
