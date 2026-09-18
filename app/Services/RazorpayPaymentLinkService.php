<?php

namespace App\Services;

use App\Jobs\RefreshFailedDonationFollowUpPaymentLinkJob;
use App\Models\DonationOrder;
use App\Support\Branding;
use App\Support\RazorpayDonationLabels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Razorpay\Api\Api;
use Throwable;

class RazorpayPaymentLinkService
{
    public const MEDIUM_EMAIL = 'email';

    public const MEDIUM_SMS = 'sms';

    /**
     * @return list<string>
     */
    public static function mediums(): array
    {
        return [self::MEDIUM_EMAIL, self::MEDIUM_SMS];
    }

    /**
     * Create a Razorpay payment link for a failed donation and persist ids on the order.
     *
     * @throws Throwable
     */
    public function createForOrder(DonationOrder $order): DonationOrder
    {
        $order->loadMissing(['items.causeModel', 'items.package']);

        if (filled($order->payment_link_id) && filled($order->payment_link_url)) {
            return $order;
        }

        $api = $this->api();
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
                    : Branding::paymentLinkDescription(),
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
        ]);

        RefreshFailedDonationFollowUpPaymentLinkJob::dispatch($order->id);

        return $order->refresh();
    }

    /**
     * Ensure a payment link exists, verify it can be notified, then send email/SMS.
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function notifyOrder(DonationOrder $order, string $medium): DonationOrder
    {
        if ($order->isPaid()) {
            throw new InvalidArgumentException('This donation is already paid. Payment link notification was not sent.');
        }

        if (! $order->isFailed()) {
            throw new InvalidArgumentException('Payment link notify is only available for failed donations.');
        }

        if (! filled($order->payment_link_id) || ! filled($order->payment_link_url)) {
            $order = $this->createForOrder($order);
        }

        $this->notify($order->fresh(), $medium);

        return $order->fresh();
    }

    /**
     * Send or resend a payment-link notification via Razorpay email or SMS.
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function notify(DonationOrder $order, string $medium): void
    {
        $medium = strtolower(trim($medium));

        if (! in_array($medium, self::mediums(), true)) {
            throw new InvalidArgumentException('Payment link notify medium must be email or sms.');
        }

        if ($order->isPaid()) {
            throw new InvalidArgumentException('This donation is already paid. Payment link notification was not sent.');
        }

        if ($medium === self::MEDIUM_EMAIL && ! $this->hasSendableEmail($order->donor_email)) {
            throw new InvalidArgumentException('Add a valid donor email before sending the payment link email.');
        }

        if ($medium === self::MEDIUM_SMS && ! app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($order->donor_phone)) {
            throw new InvalidArgumentException('Add a valid donor phone before sending the payment link SMS.');
        }

        if (! filled($order->payment_link_id)) {
            throw new InvalidArgumentException('Payment link must exist before notifying the donor.');
        }

        $link = $this->fetchPaymentLink($order->payment_link_id);
        $this->assertLinkIsNotifiable($link, $medium);

        try {
            $response = $this->api()->paymentLink->fetch($order->payment_link_id)->notifyBy($medium);
        } catch (Throwable $exception) {
            Log::error('Payment link Razorpay notify failed', [
                'order_id' => $order->id,
                'payment_link_id' => $order->payment_link_id,
                'medium' => $medium,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (is_array($response) && array_key_exists('success', $response) && $response['success'] !== true) {
            throw new InvalidArgumentException('Razorpay did not confirm the payment link '.$medium.' notification.');
        }

        $timestampColumn = $medium === self::MEDIUM_EMAIL
            ? 'payment_link_email_sent_at'
            : 'payment_link_sms_sent_at';

        $order->forceFill([
            $timestampColumn => now(),
        ])->save();

        Log::info('Payment link notify sent', [
            'order_id' => $order->id,
            'payment_link_id' => $order->payment_link_id,
            'medium' => $medium,
            'response' => $response,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPaymentLink(string $paymentLinkId): array
    {
        $link = $this->api()->paymentLink->fetch($paymentLinkId);

        if (is_array($link)) {
            return $link;
        }

        if (is_object($link) && method_exists($link, 'toArray')) {
            return $link->toArray();
        }

        return (array) $link;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    public function assertLinkIsOpen(array $link): void
    {
        $status = strtolower(trim((string) ($link['status'] ?? '')));

        if (in_array($status, ['paid', 'partially_paid'], true)) {
            throw new InvalidArgumentException('This payment link is already paid. Notification was not sent.');
        }

        if (in_array($status, ['cancelled', 'expired'], true)) {
            throw new InvalidArgumentException("This payment link is {$status}. Create a new link before notifying.");
        }
    }

    /**
     * @param  array<string, mixed>  $link
     */
    public function assertLinkIsNotifiable(array $link, string $medium): void
    {
        $this->assertLinkIsOpen($link);

        $customer = is_array($link['customer'] ?? null) ? $link['customer'] : [];

        if ($medium === self::MEDIUM_EMAIL && ! $this->hasSendableEmail($customer['email'] ?? null)) {
            throw new InvalidArgumentException('This payment link has no customer email on Razorpay. Create a new link with the donor email.');
        }

        if ($medium === self::MEDIUM_SMS && ! filled($customer['contact'] ?? null)) {
            throw new InvalidArgumentException('This payment link has no customer phone on Razorpay. Create a new link with the donor phone.');
        }
    }

    public function hasSendableEmail(?string $email): bool
    {
        $email = trim((string) $email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function api(): Api
    {
        return new Api(
            config('payments.razorpay.key'),
            config('payments.razorpay.secret')
        );
    }

    private function razorpayContact(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) >= 10) {
            return '+91'.substr($digits, -10);
        }

        return $digits !== '' ? $digits : null;
    }
}
