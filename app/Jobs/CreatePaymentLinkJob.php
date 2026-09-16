<?php

namespace App\Jobs;

use App\Models\DonationOrder;
use App\Models\Setting;
use App\Support\RazorpayDonationLabels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Throwable;

class CreatePaymentLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $orderId,
        private bool $immediate = false,
    ) {}

    public function handle(): void
    {
        $order = DonationOrder::with(['items.causeModel', 'items.package'])->find($this->orderId);
        if (! $order) {
            return;
        }

        if (! $order->isFailed()) {
            Log::info('Payment link job skipped', [
                'order_id' => $order->id,
                'reason' => 'not_failed',
                'status' => $order->status,
                'immediate' => $this->immediate,
            ]);

            return;
        }

        if ($order->payment_link_id) {
            Log::info('Payment link job skipped', [
                'order_id' => $order->id,
                'reason' => 'link_already_exists',
                'immediate' => $this->immediate,
                'has_url' => filled($order->payment_link_url),
            ]);

            if ($this->immediate && filled($order->payment_link_url)) {
                $this->dispatchWhatsApp($order);
            }

            return;
        }

        if (! $order->failed_at) {
            return;
        }

        if (! $this->immediate && $order->failed_at->diffInMinutes(now()) < 5) {
            self::dispatch($order->id)->delay(now()->addMinutes(5));

            return;
        }

        $api = new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret')
        );

        $item = $order->items->first();
        $cause = $item?->causeModel;
        $package = $item?->package;
        $contact = $this->razorpayContact($order->donor_phone);

        try {
            $link = $api->paymentLink->create([
                'amount' => (int) ($order->total_amount * 100),
                'currency' => 'INR',
                'accept_partial' => false,
                'description' => $cause
                    ? RazorpayDonationLabels::description($cause, $package)
                    : \App\Support\Branding::paymentLinkDescription(),

                'customer' => array_filter([
                    'name' => $order->donor_name,
                    'email' => $order->donor_email,
                    'contact' => $contact,
                ]),

                'notes' => array_merge(
                    $cause ? RazorpayDonationLabels::orderNotes($order, $cause, $package) : [
                        'donation_order_id' => (string) $order->id,
                    ],
                    [
                        'address' => trim(implode(', ', array_filter([
                            $order->address,
                            $order->city,
                            $order->state,
                            $order->pincode,
                            $order->country,
                        ]))),
                        'city' => $order->city ?? '',
                        'state' => $order->state ?? '',
                        'pincode' => $order->pincode ?? '',
                    ]
                ),
            ]);
        } catch (Throwable $exception) {
            Log::error('Payment link Razorpay create failed', [
                'order_id' => $order->id,
                'immediate' => $this->immediate,
                'contact_suffix' => $contact ? substr($contact, -4) : null,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $order->update([
            'payment_link_id' => $link['id'],
            'payment_link_url' => $link['short_url'] ?? $link['url'],
        ]);

        Log::info('Payment link created', [
            'order_id' => $order->id,
            'payment_link_id' => $order->payment_link_id,
            'immediate' => $this->immediate,
        ]);

        $this->dispatchWhatsApp($order);
    }

    private function dispatchWhatsApp(DonationOrder $order): void
    {
        if (! Setting::isEnabled(Setting::SEND_WHATSAPP_PAYMENT_LINK)) {
            Log::warning('Payment-link WhatsApp not dispatched', [
                'order_id' => $order->id,
                'reason' => 'send_whatsapp_payment_link_disabled',
            ]);

            return;
        }

        if (! filled($order->payment_link_url)) {
            return;
        }

        dispatch(new SendPaymentLinkWhatsAppJob($order->id, $this->immediate));

        Log::info('Payment-link WhatsApp job dispatched', [
            'order_id' => $order->id,
            'force_send' => $this->immediate,
        ]);
    }

    private function razorpayContact(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) >= 10) {
            return substr($digits, -10);
        }

        return $digits !== '' ? $digits : null;
    }
}
