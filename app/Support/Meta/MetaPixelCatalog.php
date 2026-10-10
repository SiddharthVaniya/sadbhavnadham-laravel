<?php

namespace App\Support\Meta;

use App\Models\DonationOrder;
use App\Models\LinkTrackingVisit;

/**
 * Mirrors Next.js {@see src/lib/meta-pixel.ts} — one browser/CAPI pixel per session.
 */
class MetaPixelCatalog
{
    public const DEFAULT_CODE = 'sadbhavna_d';

    /**
     * @return array{code: string, id: string}
     */
    public static function resolve(?string $urlCode, ?string $storedCode): array
    {
        $fromUrl = self::knownCode($urlCode);
        if ($fromUrl !== null) {
            return $fromUrl;
        }

        $stored = self::knownCode($storedCode);
        if ($stored !== null) {
            return $stored;
        }

        return self::defaultPixel();
    }

    /**
     * @return array{code: string, id: string}
     */
    public static function defaultPixel(): array
    {
        $defaultCode = strtolower(trim((string) config('meta_capi.default_pixel_code', self::DEFAULT_CODE)));
        $catalog = self::codeToIdMap();

        if (isset($catalog[$defaultCode])) {
            return ['code' => $defaultCode, 'id' => $catalog[$defaultCode]];
        }

        $first = array_key_first($catalog);

        if ($first !== null) {
            return ['code' => $first, 'id' => $catalog[$first]];
        }

        return ['code' => self::DEFAULT_CODE, 'id' => '1436406881878584'];
    }

    public static function resolveNumericIdForOrder(DonationOrder $order): string
    {
        $storedCode = filled($order->meta_pixel_code)
            ? (string) $order->meta_pixel_code
            : self::pixelCodeFromLinkVisit($order);

        $urlCode = self::pixelCodeFromLandingUrl($order->landing_path);

        return self::resolve($urlCode, $storedCode)['id'];
    }

    public static function resolveCodeForOrder(DonationOrder $order): string
    {
        if (filled($order->meta_pixel_code)) {
            $known = self::knownCode((string) $order->meta_pixel_code);

            return $known['code'] ?? strtolower(trim((string) $order->meta_pixel_code));
        }

        $urlCode = self::pixelCodeFromLandingUrl($order->landing_path);
        $storedCode = self::pixelCodeFromLinkVisit($order);

        return self::resolve($urlCode, $storedCode)['code'];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function resolveCodeFromCheckoutSnapshot(array $snapshot): string
    {
        $urlCode = self::pixelCodeFromLandingUrl((string) ($snapshot['landing_url'] ?? ''));
        $storedCode = trim((string) ($snapshot['pixel_id'] ?? ''));
        $storedCode = $storedCode !== '' ? $storedCode : null;

        return self::resolve($urlCode, $storedCode)['code'];
    }

    /**
     * @return array<string, string> lowercase code => numeric Meta pixel id
     */
    public static function codeToIdMap(): array
    {
        $map = [];

        foreach (config('meta_capi.pixels', []) as $config) {
            $id = trim((string) ($config['pixel_id'] ?? ''));
            $code = strtolower(trim((string) ($config['code'] ?? '')));

            if ($id === '' || $code === '') {
                continue;
            }

            $map[$code] = $id;
        }

        return $map;
    }

    /**
     * @return array{code: string, id: string}|null
     */
    private static function knownCode(?string $value): ?array
    {
        $code = strtolower(trim((string) ($value ?? '')));

        if ($code === '') {
            return null;
        }

        $catalog = self::codeToIdMap();

        if (! array_key_exists($code, $catalog)) {
            return null;
        }

        return ['code' => $code, 'id' => $catalog[$code]];
    }

    private static function pixelCodeFromLinkVisit(DonationOrder $order): ?string
    {
        $visit = LinkTrackingVisit::query()
            ->where('donation_order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if ($visit === null) {
            $visit = LinkTrackingVisit::query()
                ->where('sid', (string) ($order->partner_code ?? ''))
                ->orderByDesc('id')
                ->first();
        }

        if ($visit === null) {
            return null;
        }

        $extra = is_array($visit->extra_params) ? $visit->extra_params : [];
        $fromExtra = trim((string) ($extra['pixel_id'] ?? ''));

        return $fromExtra !== '' ? $fromExtra : null;
    }

    private static function pixelCodeFromLandingUrl(?string $landingUrl): ?string
    {
        if ($landingUrl === null || trim($landingUrl) === '') {
            return null;
        }

        $query = parse_url($landingUrl, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);

        $pixelId = trim((string) ($params['pixel_id'] ?? ''));

        return $pixelId !== '' ? $pixelId : null;
    }
}
