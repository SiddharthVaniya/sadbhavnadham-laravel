<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreatePaymentLinkJob;
use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Jobs\SendReceiptWhatsAppJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\DonationOrder;
use App\Services\DonationWhatsAppPolicy;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Support\Facades\Log;

class DonationDeliveryController extends Controller
{
    public function __construct(
        private DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ) {}

    public function resendPaymentLinkWhatsApp(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if ($order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'This donation is already paid. Payment link WhatsApp was not sent.',
                'warning',
            );
        }

        if (! $order->isFailed()) {
            return $this->redirectWithTone(
                $order,
                'Payment link WhatsApp is only available for failed donations.',
                'warning',
            );
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor phone on this donation before sending WhatsApp.',
                'warning',
            );
        }

        if (filled($order->payment_link_id)) {
            try {
                $paymentLinks = app(RazorpayPaymentLinkService::class);
                $link = $paymentLinks->fetchPaymentLink($order->payment_link_id);
                $paymentLinks->assertLinkIsOpen($link);
            } catch (\InvalidArgumentException $exception) {
                return $this->redirectWithTone($order, $exception->getMessage(), 'warning');
            } catch (\Throwable $exception) {
                Log::warning('Could not verify Razorpay payment link before WhatsApp', [
                    'order_id' => $order->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (! filled($order->payment_link_url)) {
            CreatePaymentLinkJob::dispatch($order->id, true);

            $message = 'Payment link creation queued. WhatsApp will send after the link is ready.';
            toastr()->success($message);

            return $this->redirectWithTone($order, $message);
        }

        SendPaymentLinkWhatsAppJob::dispatch($order->id, true);

        $message = $order->payment_link_sent_at
            ? 'Payment link WhatsApp re-send queued. It will send when the queue worker runs.'
            : 'Payment link WhatsApp queued. It will send when the queue worker runs.';
        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    public function notifyPaymentLink(DonationOrder $order, string $medium)
    {
        $this->authorize('view', $order);

        $medium = strtolower(trim($medium));
        $paymentLinks = app(RazorpayPaymentLinkService::class);

        if (! in_array($medium, RazorpayPaymentLinkService::mediums(), true)) {
            abort(404);
        }

        if ($order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'This donation is already paid. Payment link notification was not sent.',
                'warning',
            );
        }

        if (! $order->isFailed()) {
            return $this->redirectWithTone(
                $order,
                'Payment link notify is only available for failed donations.',
                'warning',
            );
        }

        if ($medium === RazorpayPaymentLinkService::MEDIUM_EMAIL
            && ! $paymentLinks->hasSendableEmail($order->donor_email)) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor email on this donation before sending the payment link email.',
                'warning',
            );
        }

        if ($medium === RazorpayPaymentLinkService::MEDIUM_SMS
            && ! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor phone on this donation before sending the payment link SMS.',
                'warning',
            );
        }

        $label = $medium === RazorpayPaymentLinkService::MEDIUM_EMAIL ? 'email' : 'SMS';

        try {
            // Run immediately so admin sees Razorpay errors without waiting on the queue worker.
            $paymentLinks->notifyOrder($order, $medium);
        } catch (\InvalidArgumentException $exception) {
            toastr()->warning($exception->getMessage());

            return $this->redirectWithTone($order, $exception->getMessage(), 'warning');
        } catch (\Throwable $exception) {
            Log::error('Admin payment link notify failed', [
                'order_id' => $order->id,
                'medium' => $medium,
                'error' => $exception->getMessage(),
            ]);

            $message = "Payment link {$label} failed: ".$exception->getMessage();
            toastr()->error($message);

            return $this->redirectWithTone($order, $message, 'warning');
        }

        $message = "Payment link {$label} sent via Razorpay.";
        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    public function resendSheet(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'Only paid donations can be logged to Google Sheet.',
                'warning',
            );
        }

        $force = $order->sheet_logged_at !== null;
        LogDonationToSheetJob::dispatch($order, 'captured', $force);

        $message = $force
            ? 'Google Sheet re-log queued. It will run when the queue worker processes it.'
            : 'Google Sheet log queued. It will run when the queue worker processes it.';

        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    public function resendThankYouWhatsApp(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'Only paid donations can receive a thank-you WhatsApp.',
                'warning',
            );
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)
            || $order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR
        ) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor phone on this donation before sending WhatsApp.',
                'warning',
            );
        }

        SendThankYouWhatsAppJob::dispatch($order, true);

        $message = 'Thank-you WhatsApp queued. It will send when the queue worker runs.';
        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    public function resendCertificateWhatsApp(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'Only paid donations can receive a certificate WhatsApp.',
                'warning',
            );
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)
            || $order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR
        ) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor phone on this donation before sending WhatsApp.',
                'warning',
            );
        }

        SendCertificateWhatsAppJob::dispatch($order, true);

        $message = 'Certificate WhatsApp queued. It will send when the queue worker runs.';
        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    public function resendReceiptWhatsApp(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return $this->redirectWithTone(
                $order,
                'Only paid donations can receive a receipt WhatsApp.',
                'warning',
            );
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($order->donor_phone)
            || $order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR
        ) {
            return $this->redirectWithTone(
                $order,
                'Add a valid donor phone on this donation before sending WhatsApp.',
                'warning',
            );
        }

        SendReceiptWhatsAppJob::dispatch($order, true);

        $message = 'Receipt WhatsApp queued. It will send when the queue worker runs.';
        toastr()->success($message);

        return $this->redirectWithTone($order, $message);
    }

    private function redirectWithTone(DonationOrder $order, string $message, string $tone = 'success')
    {
        return redirect()
            ->route('admin.donations.show', $order)
            ->with('status', $message)
            ->with('flash_tone', $tone);
    }
}
