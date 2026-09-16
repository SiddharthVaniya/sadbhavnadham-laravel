<?php

namespace App\Console\Commands;

use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendReceiptWhatsAppJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\DonationPaymentService;
use App\Services\DonationWhatsAppPolicy;
use App\Support\DonationNotificationRetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class ReconcileDonations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'donations:reconcile
                            {--all-missed-notifications : Re-queue missed notifications for all paid donations (not only recent)}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile successful donations and retry missed actions';

    /**
     * Execute the console command.
     */
    public function handle(
        DonationPaymentService $donationPaymentService,
        DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ): int {
        Log::info('🔄 Donation reconciliation started');

        $api = new Api(config('payments.razorpay.key'), config('payments.razorpay.secret'));
        $reconciledOrderIds = [];
        $sheetCount = 0;
        $receiptCount = 0;
        $whatsappCount = 0;
        $certificateWhatsappCount = 0;
        $receiptWhatsappCount = 0;
        $qrImportedCount = 0;

        // Fix payments missed by webhook (newest first so recent test/live donations are not blocked by old abandoned checkouts).
        DonationOrder::where('status', DonationOrder::STATUS_PENDING)
            ->whereNotNull('provider_order_id')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->each(function (DonationOrder $order) use ($api, $donationPaymentService, &$reconciledOrderIds): void {

                $payments = $api->payment->all([
                    'order_id' => $order->provider_order_id,
                ]);

                foreach ($payments->items as $payment) {
                    if ($payment->status === 'captured') {
                        $donationPaymentService->handleCaptured([
                            'id' => $payment->id,
                            'order_id' => $order->provider_order_id,
                        ]);

                        $reconciledOrderIds[] = $order->id;

                        Log::info('✅ Order marked paid by reconcile', ['order_id' => $order->id]);
                        break;
                    }
                }
            });

        $qrImportedCount = $this->reconcileQrPayments($api, $donationPaymentService);

        $notificationQuery = DonationOrder::where('status', DonationOrder::STATUS_PAID)
            ->when($reconciledOrderIds !== [], function ($query) use ($reconciledOrderIds): void {
                $query->whereNotIn('id', $reconciledOrderIds);
            })
            ->where(function ($q) {
                $q->whereNull('receipt_sent_at')
                    ->orWhereNull('sheet_logged_at')
                    ->orWhereNull('whatsapp_sent_at')
                    ->orWhereNull('certificate_whatsapp_sent_at')
                    ->orWhereNull('receipt_whatsapp_sent_at');
            });

        if (! $this->option('all-missed-notifications')) {
            $retryHours = DonationNotificationRetry::retryWindowHours();
            $notificationQuery->where(function ($query) use ($retryHours): void {
                $query->where('paid_at', '>=', now()->subHours($retryHours))
                    ->orWhere(function ($fallback) use ($retryHours): void {
                        $fallback->whereNull('paid_at')
                            ->where('created_at', '>=', now()->subHours($retryHours));
                    });
            });
        }

        $notificationQuery
            ->with('items')
            ->chunkById(100, function ($orders) use (&$sheetCount, &$receiptCount, &$whatsappCount, &$certificateWhatsappCount, &$receiptWhatsappCount, $donationWhatsAppPolicy): void {

                foreach ($orders as $order) {

                    if (! $order->receipt_sent_at && Setting::isEnabled(Setting::SEND_RECEIPT_EMAIL)) {
                        if (trim((string) $order->donor_email) === '') {
                            DonationNotificationRetry::markExhausted(
                                $order,
                                DonationNotificationRetry::CHANNEL_RECEIPT,
                                'No donor email on order',
                            );
                        } elseif (DonationNotificationRetry::canReconcileRetry($order, DonationNotificationRetry::CHANNEL_RECEIPT)) {
                            DonationNotificationRetry::markReconcileQueued($order, DonationNotificationRetry::CHANNEL_RECEIPT);
                            dispatch(new SendDonationReceiptJob($order));
                            $receiptCount++;
                            Log::info('📧 Receipt re-dispatched', [
                                'order_id' => $order->id,
                                'attempt' => $order->receipt_notify_attempts,
                            ]);
                        }
                    }

                    if (
                        ! $order->sheet_logged_at
                        && DonationNotificationRetry::canReconcileRetry($order, DonationNotificationRetry::CHANNEL_SHEET)
                    ) {
                        DonationNotificationRetry::markReconcileQueued($order, DonationNotificationRetry::CHANNEL_SHEET);
                        dispatch(new LogDonationToSheetJob($order));
                        $sheetCount++;
                        Log::info('📊 Sheet log re-dispatched', [
                            'order_id' => $order->id,
                            'attempt' => $order->sheet_notify_attempts,
                        ]);
                    }

                    $thankYouPending = ! $order->whatsapp_sent_at && $donationWhatsAppPolicy->shouldSendThankYou($order);

                    if (
                        $thankYouPending
                        && DonationNotificationRetry::canReconcileRetry($order, DonationNotificationRetry::CHANNEL_WHATSAPP)
                    ) {
                        DonationNotificationRetry::markReconcileQueued($order, DonationNotificationRetry::CHANNEL_WHATSAPP);
                        dispatch(new SendThankYouWhatsAppJob($order));
                        $whatsappCount++;
                        Log::info('📱 WhatsApp re-dispatched', [
                            'order_id' => $order->id,
                            'attempt' => $order->whatsapp_notify_attempts,
                        ]);
                    }

                    if (
                        ! $thankYouPending
                        && ! $order->certificate_whatsapp_sent_at
                        && $donationWhatsAppPolicy->shouldSendCertificate($order)
                        && DonationNotificationRetry::canReconcileRetry($order, DonationNotificationRetry::CHANNEL_CERTIFICATE)
                    ) {
                        DonationNotificationRetry::markReconcileQueued($order, DonationNotificationRetry::CHANNEL_CERTIFICATE);
                        dispatch(new SendCertificateWhatsAppJob($order));
                        $certificateWhatsappCount++;
                        Log::info('📱 Certificate WhatsApp re-dispatched', [
                            'order_id' => $order->id,
                            'attempt' => $order->certificate_whatsapp_notify_attempts,
                        ]);
                    }

                    if (
                        ! $order->receipt_whatsapp_sent_at
                        && $donationWhatsAppPolicy->shouldSendReceipt($order)
                        && DonationNotificationRetry::canReconcileRetry($order, DonationNotificationRetry::CHANNEL_RECEIPT_WHATSAPP)
                    ) {
                        DonationNotificationRetry::markReconcileQueued($order, DonationNotificationRetry::CHANNEL_RECEIPT_WHATSAPP);
                        dispatch(new SendReceiptWhatsAppJob($order));
                        $receiptWhatsappCount++;
                        Log::info('📱 Receipt WhatsApp re-dispatched', [
                            'order_id' => $order->id,
                            'attempt' => $order->receipt_whatsapp_notify_attempts,
                        ]);
                    }
                }
            });

        $this->info("Reconciliation queued: {$sheetCount} sheet log(s), {$receiptCount} receipt(s), {$whatsappCount} thank-you WhatsApp message(s), {$certificateWhatsappCount} certificate WhatsApp message(s), {$receiptWhatsappCount} receipt WhatsApp message(s), {$qrImportedCount} QR payment(s) imported.");

        Log::info('✅ Donation reconciliation finished', [
            'sheet_jobs' => $sheetCount,
            'receipt_jobs' => $receiptCount,
            'whatsapp_jobs' => $whatsappCount,
            'certificate_whatsapp_jobs' => $certificateWhatsappCount,
            'receipt_whatsapp_jobs' => $receiptWhatsappCount,
            'qr_imported' => $qrImportedCount,
            'all_missed_notifications' => (bool) $this->option('all-missed-notifications'),
        ]);

        return self::SUCCESS;
    }

    private function reconcileQrPayments(Api $api, DonationPaymentService $donationPaymentService): int
    {
        $qrCodeIds = app(\App\Services\RazorpayQrPaymentService::class)->configuredQrCodeIds();

        if ($qrCodeIds === []) {
            return 0;
        }

        $imported = 0;
        $from = now()->subDays(2)->timestamp;

        foreach ($qrCodeIds as $qrCodeId) {
            try {
                $response = $api->qrCode->fetch($qrCodeId)->fetchAllPayments([
                    'from' => $from,
                    'count' => 50,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Razorpay QR payments during reconcile', [
                    'qr_code_id' => $qrCodeId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $items = is_object($response) && isset($response->items)
                ? $response->items
                : [];

            foreach ($items as $payment) {
                $paymentArray = is_object($payment) && method_exists($payment, 'toArray')
                    ? $payment->toArray()
                    : (array) $payment;

                if (($paymentArray['status'] ?? null) !== 'captured') {
                    continue;
                }

                $paymentId = (string) ($paymentArray['id'] ?? '');

                if ($paymentId === '') {
                    continue;
                }

                if (DonationOrder::query()->where('provider_payment_id', $paymentId)->exists()) {
                    continue;
                }

                if (empty($paymentArray['qr_code_id'])) {
                    $paymentArray['qr_code_id'] = $qrCodeId;
                }

                $beforeCount = DonationOrder::query()->where('provider_payment_id', $paymentId)->count();
                $donationPaymentService->handleCaptured($paymentArray, $qrCodeId);

                if (
                    $beforeCount === 0
                    && DonationOrder::query()->where('provider_payment_id', $paymentId)->exists()
                ) {
                    $imported++;
                    Log::info('✅ QR payment imported by reconcile', [
                        'payment_id' => $paymentId,
                        'qr_code_id' => $qrCodeId,
                    ]);
                }
            }
        }

        return $imported;
    }
}
