<?php

namespace App\Services;

use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogDonationToSheetJob;
use App\Jobs\LogFailedDonationFollowUpSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendReceiptWhatsAppJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DonationPaymentService
{
    public function __construct(
        private AnalyticsService $analytics,
        private DonationAttributionService $donationAttribution,
        private DonationWhatsAppPolicy $donationWhatsAppPolicy,
        private RazorpayQrPaymentService $razorpayQrPaymentService,
        private LinkTrackingService $linkTracking,
    ) {}

    /* =====================================================
     |  PAYMENT CAPTURED
     ===================================================== */

    /**
     * @param  array<string, mixed>  $payment
     */
    public function handleCaptured(array $payment, ?string $qrCodeId = null): void
    {
        $order = DB::transaction(function () use ($payment, $qrCodeId) {
            $paymentId = (string) ($payment['id'] ?? '');

            if ($paymentId !== '') {
                $existingByPayment = DonationOrder::query()
                    ->where('provider_payment_id', $paymentId)
                    ->lockForUpdate()
                    ->first();

                if ($existingByPayment) {
                    if ($existingByPayment->isPaid()) {
                        if (! $existingByPayment->hasReceipt()) {
                            $existingByPayment->update([
                                'receipt_number' => $this->generateNextReceiptNumber((string) $existingByPayment->payment_provider),
                            ]);

                            return $existingByPayment;
                        }

                        return null;
                    }

                    if (! $existingByPayment->hasReceipt()) {
                        $existingByPayment->update([
                            'receipt_number' => $this->generateNextReceiptNumber((string) $existingByPayment->payment_provider),
                        ]);
                    }

                    $existingByPayment->markAsPaid($paymentId, self::resolvePaymentCapturedAt($payment));

                    return $existingByPayment->fresh();
                }
            }

            $order = null;

            if (! empty($payment['order_id'])) {
                $order = DonationOrder::where(
                    'provider_order_id',
                    $payment['order_id']
                )->lockForUpdate()->first();
            }

            $paymentNotesOrderId = $payment['notes']['order_id']
                ?? $payment['notes']['donation_order_id']
                ?? null;

            if (! $order && ! empty($paymentNotesOrderId)) {
                $order = DonationOrder::where(
                    'id',
                    $paymentNotesOrderId
                )->lockForUpdate()->first();
            }

            if (! $order) {
                $qrOrder = $this->razorpayQrPaymentService->createPaidOrderFromPayment($payment, $qrCodeId);

                if ($qrOrder) {
                    if (! $qrOrder->hasReceipt()) {
                        $qrOrder->update([
                            'receipt_number' => $this->generateNextReceiptNumber((string) $qrOrder->payment_provider),
                        ]);
                    }

                    return $qrOrder->fresh();
                }

                Log::error('DonationOrder not found (captured)', $payment);

                return null;
            }

            if ($order->isPaid()) {
                if (! $order->hasReceipt()) {
                    $order->update([
                        'receipt_number' => $this->generateNextReceiptNumber((string) $order->payment_provider),
                    ]);

                    return $order;
                }

                return null;
            }

            // Generate receipt number once
            $receiptNumber = $this->generateNextReceiptNumber((string) $order->payment_provider);

            $order->update([
                'receipt_number' => $receiptNumber,
            ]);
            $order->markAsPaid($payment['id'] ?? null, self::resolvePaymentCapturedAt($payment));

            return $order;
        });

        if (! $order) {
            return;
        }

        $this->completePaidOrder($order);
    }

    /**
     * Prefer Razorpay capture time so admin dates match the gateway (not webhook lag).
     *
     * @param  array<string, mixed>  $payment
     */
    public static function resolvePaymentCapturedAt(array $payment): Carbon
    {
        foreach (['captured_at', 'created_at'] as $key) {
            $value = $payment[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value)
                    ->setTimezone(config('app.timezone'));
            }

            try {
                return Carbon::parse((string) $value)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
                continue;
            }
        }

        return now();
    }

    public function captureOrderPayment(
        DonationOrder $order,
        string $providerPaymentId,
        ?\DateTimeInterface $paidAt = null,
    ): ?DonationOrder {
        $order = DB::transaction(function () use ($order, $providerPaymentId, $paidAt) {
            $locked = DonationOrder::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return null;
            }

            if ($locked->isPaid() && $locked->hasReceipt()) {
                return null;
            }

            if (! $locked->hasReceipt()) {
                $locked->update([
                    'receipt_number' => $this->generateNextReceiptNumber((string) $locked->payment_provider),
                ]);
            }

            if (! $locked->isPaid()) {
                $locked->markAsPaid($providerPaymentId, $paidAt);
            }

            return $locked->fresh();
        });

        if (! $order) {
            return null;
        }

        $this->completePaidOrder($order);

        return $order;
    }

    public function completePaidOrder(DonationOrder $order): void
    {
        $this->donationAttribution->ensureOnPaid($order);

        $this->analytics->trackDonationPaid($order->fresh());

        $this->linkTracking->markConverted($order->fresh());

        dispatch(new LogDonationToSheetJob($order));

        if ($this->shouldSendAutomaticReceiptEmail($order)) {
            dispatch(new SendDonationReceiptJob($order));
        }

        $this->dispatchPostPaymentWhatsAppJobs($order);
    }

    /**
     * QR donations are auto-created from anonymous payments, so they never get
     * automatic notifications; the admin can send them manually if needed.
     */
    private function shouldSendAutomaticReceiptEmail(DonationOrder $order): bool
    {
        if (! Setting::isEnabled(Setting::SEND_RECEIPT_EMAIL)) {
            return false;
        }

        if ($order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR) {
            return false;
        }

        return trim((string) $order->donor_email) !== '';
    }

    private function dispatchPostPaymentWhatsAppJobs(DonationOrder $order): void
    {
        $jobs = [];

        if ($this->donationWhatsAppPolicy->shouldSendThankYou($order)) {
            $jobs[] = new SendThankYouWhatsAppJob($order);
        }

        if ($this->donationWhatsAppPolicy->shouldSendCertificate($order)) {
            $jobs[] = new SendCertificateWhatsAppJob($order);
        }

        if ($this->donationWhatsAppPolicy->shouldSendReceipt($order)) {
            $jobs[] = new SendReceiptWhatsAppJob($order);
        }

        if ($jobs === []) {
            return;
        }

        if (count($jobs) > 1) {
            Bus::chain($jobs)->dispatch();

            return;
        }

        dispatch($jobs[0]);
    }

    /* =====================================================
     |  PAYMENT FAILED
     ===================================================== */

    public function handleFailed(array $payment): void
    {
        $orderId = $payment['order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        $order = DB::transaction(function () use ($orderId, $payment) {
            $order = DonationOrder::where(
                'provider_order_id',
                $orderId
            )->lockForUpdate()->first();

            if (! $order) {
                Log::warning('DonationOrder not found (failed)', $payment);

                return null;
            }

            $failedAt = now();
            $updated = DonationOrder::whereKey($order->getKey())
                ->where('status', DonationOrder::STATUS_PENDING)
                ->update([
                    'status' => DonationOrder::STATUS_FAILED,
                    'failed_at' => $failedAt,
                ]);

            if ($updated === 0) {
                return null;
            }

            $order->forceFill([
                'status' => DonationOrder::STATUS_FAILED,
                'failed_at' => $failedAt,
            ]);

            return $order;
        });

        if (! $order) {
            return;
        }

        $this->analytics->trackDonationFailed($order);

        // If checkout was already abandoned for 10+ minutes, send recovery WhatsApp immediately.
        $immediate = $order->created_at !== null
            && $order->created_at->lte(now()->subMinutes(10));

        dispatch(new CreatePaymentLinkJob($order->id, $immediate));
        dispatch(new LogDonationToSheetJob($order, 'failed'));
        dispatch(new LogFailedDonationFollowUpSheetJob($order));
    }

    /* =====================================================
     |  RECEIPT NUMBER GENERATOR
     ===================================================== */

    public function peekNextReceiptNumber(string $provider = DonationOrder::PROVIDER_RAZORPAY): int
    {
        $highestReceiptNumber = $this->highestExistingReceiptNumber($provider);
        $sequenceName = DonationOrder::receiptSequenceName($provider);

        $sequence = DB::table('receipt_number_sequences')
            ->where('name', $sequenceName)
            ->first();

        if (! $sequence) {
            return $highestReceiptNumber + 1;
        }

        return max((int) $sequence->next_number, $highestReceiptNumber + 1);
    }

    /**
     * @return array<string, array{number: int, formatted: string}>
     */
    public function peekNextReceiptNumbersByProvider(): array
    {
        $providers = [
            DonationOrder::PROVIDER_OFFLINE,
            DonationOrder::PROVIDER_RAZORPAY,
            DonationOrder::PROVIDER_RAZORPAY_QR,
            DonationOrder::PROVIDER_DANAMOJO,
            DonationOrder::PROVIDER_CASHFREE,
        ];

        $nextReceipts = [];

        foreach ($providers as $provider) {
            $number = $this->peekNextReceiptNumber($provider);

            $nextReceipts[$provider] = [
                'number' => $number,
                'formatted' => DonationOrder::formatReceiptNumber($number, $provider),
            ];
        }

        return $nextReceipts;
    }

    private function generateNextReceiptNumber(string $provider): int
    {
        return DB::transaction(function () use ($provider) {
            $highestReceiptNumber = $this->highestExistingReceiptNumber($provider);
            $sequenceName = DonationOrder::receiptSequenceName($provider);

            $sequence = DB::table('receipt_number_sequences')
                ->where('name', $sequenceName)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $nextReceiptNumber = $highestReceiptNumber + 1;

                DB::table('receipt_number_sequences')->insert([
                    'name' => $sequenceName,
                    'next_number' => $nextReceiptNumber + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $nextReceiptNumber = max((int) $sequence->next_number, $highestReceiptNumber + 1);

                DB::table('receipt_number_sequences')
                    ->where('name', $sequenceName)
                    ->update([
                        'next_number' => $nextReceiptNumber + 1,
                        'updated_at' => now(),
                    ]);
            }

            return $nextReceiptNumber;
        });
    }

    private function assignManualReceiptNumber(int $manualReceiptNumber, string $provider): int
    {
        return DB::transaction(function () use ($manualReceiptNumber, $provider) {
            $highestReceiptNumber = $this->highestExistingReceiptNumber($provider);
            $sequenceName = DonationOrder::receiptSequenceName($provider);

            $sequence = DB::table('receipt_number_sequences')
                ->where('name', $sequenceName)
                ->lockForUpdate()
                ->first();

            $nextAfterManual = max(
                $highestReceiptNumber + 1,
                $manualReceiptNumber + 1,
                $sequence ? (int) $sequence->next_number : 1,
            );

            if (! $sequence) {
                DB::table('receipt_number_sequences')->insert([
                    'name' => $sequenceName,
                    'next_number' => $nextAfterManual,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('receipt_number_sequences')
                    ->where('name', $sequenceName)
                    ->update([
                        'next_number' => $nextAfterManual,
                        'updated_at' => now(),
                    ]);
            }

            return $manualReceiptNumber;
        });
    }

    private function highestExistingReceiptNumber(string $provider): int
    {
        return DonationOrder::query()
            ->whereNotNull('receipt_number')
            ->whereIn('payment_provider', DonationOrder::receiptProviderFamilyMembers($provider))
            ->pluck('receipt_number')
            ->map(fn (string $receiptNumber): int => (int) $receiptNumber)
            ->max() ?? 0;
    }

    public function generateManualReceipt(
        DonationOrder $order,
        bool $sendEmail = true,
        bool $sendWhatsAppThankYou = false,
        bool $sendWhatsAppCertificate = false,
        ?int $manualReceiptNumber = null,
    ): DonationOrder {
        if (! $order->hasReceipt()) {
            $provider = (string) ($order->payment_provider ?: DonationOrder::PROVIDER_OFFLINE);

            $receiptNumber = $manualReceiptNumber !== null
                ? $this->assignManualReceiptNumber($manualReceiptNumber, $provider)
                : $this->generateNextReceiptNumber($provider);

            $order->update([
                'receipt_number' => (string) $receiptNumber,
            ]);
        }

        if (! $order->isPaid()) {
            $order->markAsPaid();
        }

        $order = $order->fresh(['items.causeModel']);

        $this->donationAttribution->ensureOnPaid($order);
        $order = $order->fresh(['items.causeModel']);

        $this->linkTracking->markConverted($order);

        dispatch(new LogDonationToSheetJob($order));

        if ($sendEmail) {
            SendDonationReceiptJob::dispatch($order, true);
        }

        if ($sendWhatsAppThankYou && $sendWhatsAppCertificate) {
            Bus::chain([
                new SendThankYouWhatsAppJob($order, true),
                new SendCertificateWhatsAppJob($order, true),
            ])->dispatch();
        } elseif ($sendWhatsAppThankYou) {
            dispatch(new SendThankYouWhatsAppJob($order, true));
        } elseif ($sendWhatsAppCertificate) {
            dispatch(new SendCertificateWhatsAppJob($order, true));
        }

        $this->analytics->trackDonationPaid($order);

        return $order;
    }
}
