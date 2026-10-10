<?php

namespace App\Support\Meta;

use App\Models\MetaPixel;

/**
 * Runtime pixel credentials (token may come from .env, not the database).
 */
class MetaCapiPixel
{
    public function __construct(
        public MetaPixel $record,
        public string $accessToken,
        public string $credentialsSource,
    ) {}

    public function databaseId(): int
    {
        return (int) $this->record->id;
    }

    public function pixelId(): string
    {
        return $this->record->normalizedPixelId();
    }

    public function label(): string
    {
        return (string) $this->record->label;
    }

    public function testEventCode(): ?string
    {
        $code = trim((string) ($this->record->test_event_code ?? ''));

        return $code !== '' ? $code : null;
    }

    public function sendsPurchase(): bool
    {
        return (bool) $this->record->send_purchase;
    }

    public function sendsInitiateCheckout(): bool
    {
        return (bool) $this->record->send_initiate_checkout;
    }

    public function isFromEnv(): bool
    {
        return $this->credentialsSource === 'env';
    }
}
