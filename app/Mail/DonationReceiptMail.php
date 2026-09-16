<?php

namespace App\Mail;

use App\Helpers\NumberHelper;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\DonationReceiptPdfService;
use App\Support\ReceiptAssets;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DonationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public DonationOrder $order;

    public string $amountInWords;

    /**
     * Create a new message instance.
     */
    public function __construct(DonationOrder $order)
    {
        $this->order = $order;
        $this->amountInWords = NumberHelper::amountInWords((float) $order->total_amount);
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $this->order->loadMissing(['items.causeModel', 'items.package', 'donor']);

        return $this->subject('Donation Receipt')
            ->view($this->receiptView())
            ->with([
                'order' => $this->order,
                'amountInWords' => $this->amountInWords,
                'receiptImages' => ReceiptAssets::all(),
                'isEmail' => true,
            ]);
    }

    private function receiptView(): string
    {
        return (string) config('receipt.view', 'receipts.donation-minimal');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! Setting::isEnabled(Setting::ATTACH_RECEIPT_PDF, true)) {
            return [];
        }

        try {
            $pdf = app(DonationReceiptPdfService::class)->make($this->receiptView(), [
                'order' => $this->order->loadMissing(['items.causeModel', 'items.package', 'donor']),
                'amountInWords' => $this->amountInWords,
            ]);

            $filename = 'donation-receipt-'.($this->order->receipt_number ?: $this->order->id).'.pdf';

            return [
                Attachment::fromData(fn () => $pdf->output(), $filename)
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            Log::error('Donation receipt PDF attachment failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
