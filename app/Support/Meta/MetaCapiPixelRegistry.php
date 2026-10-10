<?php

namespace App\Support\Meta;

use App\Models\DonationOrder;
use App\Models\MetaPixel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MetaCapiPixelRegistry
{
    /**
     * @return Collection<int, MetaCapiPixel>
     */
    public function all(): Collection
    {
        if (! config('meta_capi.enabled', true)) {
            return collect();
        }

        $this->syncEnvMetadataToDatabase();

        $pixels = $this->fromEnvironment();

        if (config('meta_capi.allow_database_pixels', false)) {
            $envIds = $pixels->map(fn (MetaCapiPixel $pixel) => $pixel->pixelId())->all();

            $fromDatabase = MetaPixel::query()
                ->where('is_active', true)
                ->orderBy('label')
                ->get()
                ->filter(function (MetaPixel $row) use ($envIds): bool {
                    if (in_array($row->normalizedPixelId(), $envIds, true)) {
                        return false;
                    }

                    return $row->hasAccessToken();
                })
                ->map(fn (MetaPixel $row) => new MetaCapiPixel(
                    $row,
                    (string) $row->access_token,
                    'database',
                ));

            $pixels = $pixels->merge($fromDatabase);
        }

        return $pixels->values();
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    public function forPurchase(): Collection
    {
        return $this->all()->filter(fn (MetaCapiPixel $pixel) => $pixel->sendsPurchase());
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    public function forPurchaseForOrder(DonationOrder $order): Collection
    {
        return $this->forOrderEvent($order, sendsPurchase: true);
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    public function forInitiateCheckout(): Collection
    {
        return $this->all()->filter(fn (MetaCapiPixel $pixel) => $pixel->sendsInitiateCheckout());
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    public function forInitiateCheckoutForOrder(DonationOrder $order): Collection
    {
        return $this->forOrderEvent($order, sendsPurchase: false);
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    private function forOrderEvent(DonationOrder $order, bool $sendsPurchase): Collection
    {
        $targetId = MetaPixelCatalog::resolveNumericIdForOrder($order);

        return $this->all()
            ->filter(function (MetaCapiPixel $pixel) use ($targetId, $sendsPurchase): bool {
                if ($pixel->pixelId() !== $targetId) {
                    return false;
                }

                return $sendsPurchase ? $pixel->sendsPurchase() : $pixel->sendsInitiateCheckout();
            })
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function envPixelsForAdmin(): array
    {
        $rows = [];

        foreach (config('meta_capi.pixels', []) as $index => $config) {
            $pixelId = trim((string) ($config['pixel_id'] ?? ''));
            $token = trim((string) ($config['access_token'] ?? ''));
            $label = trim((string) ($config['label'] ?? '')) ?: ('Pixel '.($index + 1));

            if ($pixelId === '') {
                continue;
            }

            $rows[] = [
                'slot' => $index + 1,
                'label' => $label,
                'pixel_code' => strtolower(trim((string) ($config['code'] ?? ''))) ?: null,
                'pixel_id' => $pixelId,
                'is_active' => (bool) ($config['is_active'] ?? true),
                'send_purchase' => (bool) ($config['send_purchase'] ?? true),
                'send_initiate_checkout' => (bool) ($config['send_initiate_checkout'] ?? true),
                'test_event_code' => filled($config['test_event_code'] ?? null)
                    ? (string) $config['test_event_code']
                    : null,
                'has_access_token' => $token !== '',
                'credentials_source' => 'env',
            ];
        }

        return $rows;
    }

    public function syncEnvMetadataToDatabase(): void
    {
        if (! Schema::hasTable('meta_pixels')) {
            return;
        }

        foreach (config('meta_capi.pixels', []) as $config) {
            $pixelId = trim((string) ($config['pixel_id'] ?? ''));

            if ($pixelId === '') {
                continue;
            }

            $label = trim((string) ($config['label'] ?? '')) ?: $pixelId;
            $hasEnvToken = trim((string) ($config['access_token'] ?? '')) !== '';

            $row = MetaPixel::query()->firstOrNew(['pixel_id' => $pixelId]);
            $row->fill([
                'label' => $label,
                'is_active' => (bool) ($config['is_active'] ?? true),
                'send_purchase' => (bool) ($config['send_purchase'] ?? true),
                'send_initiate_checkout' => (bool) ($config['send_initiate_checkout'] ?? true),
                'test_event_code' => filled($config['test_event_code'] ?? null)
                    ? trim((string) $config['test_event_code'])
                    : null,
            ]);

            if ($hasEnvToken) {
                $row->access_token = null;
            }

            $row->save();
        }
    }

    /**
     * @return Collection<int, MetaCapiPixel>
     */
    private function fromEnvironment(): Collection
    {
        $pixels = collect();

        foreach (config('meta_capi.pixels', []) as $config) {
            $pixelId = trim((string) ($config['pixel_id'] ?? ''));
            $token = trim((string) ($config['access_token'] ?? ''));

            if ($pixelId === '' || $token === '') {
                continue;
            }

            if (! ($config['is_active'] ?? true)) {
                continue;
            }

            $row = MetaPixel::query()->where('pixel_id', $pixelId)->first();

            if (! $row) {
                continue;
            }

            $pixels->push(new MetaCapiPixel($row, $token, 'env'));
        }

        return $pixels;
    }
}
