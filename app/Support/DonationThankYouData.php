<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\DonationSubscription;

class DonationThankYouData
{
    /**
     * @return array{
     *     type: string,
     *     headline: string,
     *     subcopy: string,
     *     donor_name: string,
     *     amount_label: string,
     *     currency: string,
     *     amount: float,
     *     cause_title: string|null,
     *     item_title: string|null,
     *     receipt_number: string|null,
     *     reference: string,
     *     status: string,
     *     is_confirmed: bool,
     *     is_recurring: bool,
     *     back_url: string,
     *     website_url: string,
     *     ecommerce: array<string, mixed>
     * }
     */
    public static function forOrder(DonationOrder $order): array
    {
        $order->loadMissing(['items.causeModel']);

        $firstItem = $order->items->first();
        $causeTitle = $firstItem?->causeModel?->title
            ?? $firstItem?->title
            ?? null;

        $itemTitle = $firstItem?->title;
        $isConfirmed = $order->isPaid();
        $amount = round((float) $order->total_amount, 2);
        $currency = strtoupper((string) ($order->currency ?: 'INR'));
        $transactionId = (string) $order->order_uuid;

        $items = $order->items->map(function ($item): array {
            $price = round((float) $item->amount, 2);
            $quantity = max(1, (int) $item->quantity);

            return [
                'item_id' => (string) ($item->cause ?: ($item->cause_id ? 'cause-'.$item->cause_id : 'donation')),
                'item_name' => (string) ($item->title ?: 'Donation'),
                'item_category' => 'Donation',
                'price' => $quantity > 0 ? round($price / $quantity, 2) : $price,
                'quantity' => $quantity,
            ];
        })->values()->all();

        if ($items === []) {
            $items[] = [
                'item_id' => 'donation',
                'item_name' => $itemTitle ?: 'Donation',
                'item_category' => 'Donation',
                'price' => $amount,
                'quantity' => 1,
            ];
        }

        return [
            'type' => 'order',
            'headline' => $isConfirmed ? 'Thank you for your donation' : 'Thank you — payment received',
            'subcopy' => $isConfirmed
                ? 'Your kind support helps us care for those who need it most.'
                : 'Your payment was successful. We are confirming it now — this usually takes just a few moments.',
            'donor_name' => (string) $order->donor_name,
            'amount_label' => self::formatMoney($amount, $currency),
            'currency' => $currency,
            'amount' => $amount,
            'cause_title' => $causeTitle,
            'item_title' => $itemTitle,
            'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : null,
            'reference' => $transactionId,
            'status' => (string) $order->status,
            'is_confirmed' => $isConfirmed,
            'is_recurring' => (bool) $order->is_recurring,
            'back_url' => DonationPublicFrontend::absolute('/donate'),
            'website_url' => DonationPublicFrontend::baseUrl(),
            'ecommerce' => [
                'transaction_id' => $transactionId,
                'value' => $amount,
                'currency' => $currency,
                'tax' => 0,
                'shipping' => 0,
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     headline: string,
     *     subcopy: string,
     *     donor_name: string,
     *     amount_label: string,
     *     currency: string,
     *     amount: float,
     *     cause_title: string|null,
     *     item_title: string|null,
     *     receipt_number: string|null,
     *     reference: string,
     *     status: string,
     *     is_confirmed: bool,
     *     is_recurring: bool,
     *     back_url: string,
     *     website_url: string,
     *     ecommerce: array<string, mixed>
     * }
     */
    public static function forSubscription(DonationSubscription $subscription): array
    {
        $subscription->loadMissing('cause');

        $amount = round((float) $subscription->total_amount, 2);
        $currency = strtoupper((string) ($subscription->currency ?: 'INR'));
        $transactionId = 'sub-'.(string) $subscription->subscription_uuid;
        $causeTitle = $subscription->cause?->title;
        $itemTitle = $subscription->item_title;
        $frequency = method_exists($subscription, 'frequencyLabel')
            ? $subscription->frequencyLabel()
            : 'Monthly';

        return [
            'type' => 'subscription',
            'headline' => 'Thank you for starting monthly support',
            'subcopy' => 'Your '.$frequency.' mandate is being set up. You will receive updates when the first donation is confirmed.',
            'donor_name' => (string) $subscription->donor_name,
            'amount_label' => self::formatMoney($amount, $currency).' / '.strtolower($frequency),
            'currency' => $currency,
            'amount' => $amount,
            'cause_title' => $causeTitle,
            'item_title' => $itemTitle,
            'receipt_number' => null,
            'reference' => (string) $subscription->subscription_uuid,
            'status' => (string) $subscription->status,
            'is_confirmed' => in_array($subscription->status, [
                DonationSubscription::STATUS_AUTHENTICATED,
                DonationSubscription::STATUS_ACTIVE,
            ], true),
            'is_recurring' => true,
            'back_url' => DonationPublicFrontend::absolute('/donate'),
            'website_url' => DonationPublicFrontend::baseUrl(),
            'ecommerce' => [
                'transaction_id' => $transactionId,
                'value' => $amount,
                'currency' => $currency,
                'tax' => 0,
                'shipping' => 0,
                'items' => [[
                    'item_id' => (string) ($subscription->cause?->slug ?: 'monthly-donation'),
                    'item_name' => (string) ($itemTitle ?: ($causeTitle ?: 'Monthly Donation')),
                    'item_category' => 'Donation',
                    'item_variant' => 'recurring',
                    'price' => $amount,
                    'quantity' => 1,
                ]],
            ],
        ];
    }

    private static function formatMoney(float $amount, string $currency): string
    {
        if ($currency === 'INR') {
            return '₹'.number_format($amount, 2);
        }

        return $currency.' '.number_format($amount, 2);
    }
}
