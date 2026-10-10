<?php

namespace App\Services\Meta;

use App\Models\DonationOrder;
use App\Models\MetaCapiEventLog;
use App\Support\Meta\MetaCapiPixel;
use App\Support\Meta\MetaCapiPixelRegistry;
use App\Support\Meta\MetaCapUserDataBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaConversionsApiService
{
    public const EVENT_PURCHASE = 'Purchase';

    public const EVENT_INITIATE_CHECKOUT = 'InitiateCheckout';

    public function __construct(
        private readonly MetaConversionsApiClient $client,
        private readonly MetaCapUserDataBuilder $userDataBuilder,
        private readonly MetaCapiPixelRegistry $pixelRegistry,
    ) {}

    public function sendPurchase(DonationOrder $order): void
    {
        $order->refresh()->loadMissing('items');

        foreach ($this->pixelRegistry->forPurchaseForOrder($order) as $pixel) {
            $this->dispatchEvent($pixel, $order, self::EVENT_PURCHASE, $this->purchaseEventId($order));
        }
    }

    public function sendInitiateCheckout(DonationOrder $order, ?string $clientUserAgent = null): void
    {
        $order->refresh()->loadMissing('items');

        foreach ($this->pixelRegistry->forInitiateCheckoutForOrder($order) as $pixel) {
            $this->dispatchEvent(
                $pixel,
                $order,
                self::EVENT_INITIATE_CHECKOUT,
                $this->initiateCheckoutEventId($order),
                $clientUserAgent,
            );
        }
    }

    private function dispatchEvent(
        MetaCapiPixel $pixel,
        DonationOrder $order,
        string $eventName,
        string $eventId,
        ?string $clientUserAgent = null,
    ): void {
        if (MetaCapiEventLog::query()
            ->where('meta_pixel_id', $pixel->databaseId())
            ->where('event_id', $eventId)
            ->where('status', MetaCapiEventLog::STATUS_SUCCESS)
            ->exists()) {
            return;
        }

        $eventTime = $this->eventTime($order, $eventName);
        $userData = $this->userDataBuilder->forOrder($order, $clientUserAgent);

        if ($userData === []) {
            Log::info('meta.capi.skipped_no_user_data', [
                'pixel_id' => $pixel->pixelId(),
                'order_id' => $order->id,
                'event' => $eventName,
            ]);
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $this->eventSourceUrl($order),
            'user_data' => $userData,
            'custom_data' => $this->customData($order, $eventName),
        ];

        $result = $this->client->sendEvents(
            $pixel->pixelId(),
            $pixel->accessToken,
            [$event],
            $pixel->testEventCode(),
        );

        $body = $result['body'] ?? [];
        $eventsReceived = (int) data_get($body, 'events_received', 0);
        $success = $result['http_status'] >= 200
            && $result['http_status'] < 300
            && $eventsReceived >= 1;

        $errorMessage = $success
            ? null
            : (string) data_get($body, 'error.message', 'Meta CAPI request failed');

        MetaCapiEventLog::query()->updateOrCreate(
            [
                'meta_pixel_id' => $pixel->databaseId(),
                'event_id' => $eventId,
            ],
            [
                'donation_order_id' => $order->id,
                'event_name' => $eventName,
                'status' => $success ? MetaCapiEventLog::STATUS_SUCCESS : MetaCapiEventLog::STATUS_ERROR,
                'http_status' => $result['http_status'],
                'error_message' => $errorMessage !== null ? Str::limit($errorMessage, 1000, '') : null,
                'response_json' => $body !== [] ? $body : null,
                'sent_at' => now(),
            ],
        );

        $pixel->record->forceFill([
            'last_event_at' => now(),
            'last_event_status' => $success ? MetaCapiEventLog::STATUS_SUCCESS : MetaCapiEventLog::STATUS_ERROR,
            'last_event_error' => $errorMessage !== null ? Str::limit($errorMessage, 1000, '') : null,
        ])->save();

        if (! $success) {
            Log::warning('meta.capi.event_failed', [
                'pixel_id' => $pixel->pixelId(),
                'order_id' => $order->id,
                'event' => $eventName,
                'message' => $errorMessage,
            ]);
        }
    }

    private function eventTime(DonationOrder $order, string $eventName): int
    {
        if ($eventName === self::EVENT_PURCHASE) {
            $paidAt = $order->paid_at ?? now();

            return $paidAt instanceof Carbon ? $paidAt->getTimestamp() : now()->getTimestamp();
        }

        $created = $order->created_at ?? now();

        return $created instanceof Carbon ? $created->getTimestamp() : now()->getTimestamp();
    }

    /**
     * @return array<string, mixed>
     */
    private function customData(DonationOrder $order, string $eventName): array
    {
        $item = $order->items->first();
        $contentId = $item?->cause ?: (string) ($item?->cause_id ?? 'donation');

        $data = [
            'currency' => strtoupper((string) ($order->currency ?: 'INR')),
            'value' => round((float) $order->total_amount, 2),
            'content_type' => 'donation',
            'content_ids' => [$contentId],
            'order_id' => (string) ($order->order_uuid ?? $order->id),
        ];

        if ($eventName === self::EVENT_PURCHASE && filled($order->provider_payment_id)) {
            $data['transaction_id'] = (string) $order->provider_payment_id;
        }

        if (filled($order->meta_campaign_id)) {
            $data['campaign_id'] = (string) $order->meta_campaign_id;
        }

        return array_filter($data, fn (mixed $value) => $value !== null && $value !== '');
    }

    private function eventSourceUrl(DonationOrder $order): string
    {
        $base = rtrim((string) config('donation.public_frontend_url', 'https://sadbhavnadham.org'), '/');
        $path = trim((string) ($order->landing_path ?? ''), '/');

        if ($path === '') {
            return $base.'/donate';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $base.'/'.ltrim($path, '/');
    }

    public function purchaseEventId(DonationOrder $order): string
    {
        return 'purchase_'.($order->order_uuid ?: $order->id);
    }

    public function initiateCheckoutEventId(DonationOrder $order): string
    {
        return 'initiate_checkout_'.($order->order_uuid ?: $order->id);
    }
}
