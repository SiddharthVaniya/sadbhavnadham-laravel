<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NumberHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SendDonationReceiptJob;
use App\Models\DonationOrder;
use App\Services\DonationPaymentService;
use App\Services\DonationReceiptPdfService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class DonationReceiptController extends Controller
{
    public function __construct(
        private DonationPaymentService $donationPaymentService,
        private DonationReceiptPdfService $donationReceiptPdfService,
    ) {}

    public function preview(DonationOrder $order)
    {
        $this->authorize('view', $order);
        abort_if(! $order->isPaid(), 403);

        $amountInWords = \App\Helpers\NumberHelper::amountInWords(
            $order->total_amount
        );

        $order->loadMissing(['items.causeModel', 'items.package']);

        return view(config('receipt.view', 'receipts.donation-minimal'), compact('order', 'amountInWords'));
    }

    public function generate(DonationOrder $order)
    {
        $this->authorize('view', $order);
        $this->donationPaymentService->generateManualReceipt($order);

        $message = 'Manual receipt generation queued successfully.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donations.show', $order)
            ->with('status', $message);
    }

    public function download(DonationOrder $order): Response|RedirectResponse
    {
        $this->authorize('view', $order);
        abort_if(! $order->isPaid(), 403);

        $order->loadMissing(['items.causeModel', 'items.package', 'donor']);

        try {
            $pdf = $this->donationReceiptPdfService->make(
                (string) config('receipt.view', 'receipts.donation-minimal'),
                [
                    'order' => $order,
                    'amountInWords' => NumberHelper::amountInWords((float) $order->total_amount),
                ],
            );

            return $pdf->download($this->donationReceiptPdfService->whatsappFilename($order));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.donations.show', $order)
                ->with('status', 'Receipt PDF could not be created. Use Preview instead.')
                ->with('flash_tone', 'warning');
        }
    }

    public function print(DonationOrder $order)
    {
        $this->authorize('view', $order);
        $message = 'PDF receipt download is temporarily disabled.';
        toastr()->warning($message);

        return redirect()
            ->route('admin.donations.receipt.preview', $order)
            ->with('warning', $message);
    }

    public function resend(DonationOrder $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return redirect()
                ->route('admin.donations.show', $order)
                ->with('status', 'Only paid donations can receive a receipt email.')
                ->with('flash_tone', 'warning');
        }

        $email = trim((string) $order->donor_email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('admin.donations.show', $order)
                ->with('status', 'Add a valid donor email on this donation before resending the receipt.')
                ->with('flash_tone', 'warning');
        }

        SendDonationReceiptJob::dispatch($order, true);

        $message = 'Receipt email queued for '.$email.'. It will send when the queue worker runs.';
        toastr()->success($message);

        return redirect()
            ->route('admin.donations.show', $order)
            ->with('status', $message);
    }
}
