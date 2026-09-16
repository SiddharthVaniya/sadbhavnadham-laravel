<?php

namespace App\Support;

class ReceiptAssets
{
    public static function url(string $key): string
    {
        $path = config("receipt.images.{$key}");

        if (! is_string($path) || $path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim((string) config('receipt.asset_base_url', config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $images = config('receipt.images', []);

        if (! is_array($images)) {
            return [];
        }

        return collect($images)
            ->mapWithKeys(fn ($path, $key) => [$key => self::url($key)])
            ->all();
    }
}
