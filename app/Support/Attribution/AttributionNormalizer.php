<?php

namespace App\Support\Attribution;

use Illuminate\Support\Str;

class AttributionNormalizer
{
    /**
     * @param  array{
     *     utm_source?: ?string,
     *     utm_medium?: ?string,
     *     utm_campaign?: ?string,
     *     utm_content?: ?string,
     *     utm_term?: ?string,
     *     platform?: ?string,
     *     placement?: ?string,
     *     referrer?: ?string,
     *     landing_path?: ?string
     * }  $input
     * @return array{
     *     attr_source: ?string,
     *     attr_medium: ?string,
     *     attr_platform: ?string,
     *     attr_placement: ?string
     * }
     */
    public static function normalize(array $input): array
    {
        $source = self::lower($input['utm_source'] ?? null);
        $medium = self::lower($input['utm_medium'] ?? null);
        $referrer = self::lower($input['referrer'] ?? null);
        $path = self::lower($input['landing_path'] ?? null);
        $platformParam = self::lower($input['platform'] ?? null);
        $placementParam = self::normalizePlacement($input['placement'] ?? null);
        $haystack = trim("{$source} {$medium} {$referrer} {$path}");

        if ($source === 'staff' || ($source === '' && str_contains($haystack, 'utm_source=staff'))) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_STAFF,
                'attr_medium' => self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_REFERRAL,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if ($source === 'wordpress' || str_contains($haystack, 'wordpress')) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_WORDPRESS,
                'attr_medium' => self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_WEBSITE,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if ($source === 'email' || str_contains($medium ?? '', 'email')) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_EMAIL,
                'attr_medium' => AttributionTaxonomy::MEDIUM_EMAIL,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if (self::isWhatsApp($source, $medium, $haystack)) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_WHATSAPP,
                'attr_medium' => self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_MESSAGING,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if (self::isYouTube($source, $haystack)) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_YOUTUBE,
                'attr_medium' => self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_ORGANIC_SOCIAL,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if (self::isGoogle($source, $medium, $haystack)) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_GOOGLE,
                'attr_medium' => self::normalizeGoogleMedium($medium, $haystack),
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if (self::isMeta($source, $medium, $haystack)) {
            $platform = self::resolveMetaPlatform($source, $medium, $platformParam, $haystack);
            $placement = $placementParam ?? self::placementFromMetaMedium($input['utm_medium'] ?? null);

            return [
                'attr_source' => AttributionTaxonomy::SOURCE_META,
                'attr_medium' => self::normalizeMetaMedium($medium, $haystack),
                'attr_platform' => $platform,
                'attr_placement' => $placement,
            ];
        }

        if (self::isOrganic($source, $medium)) {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_ORGANIC,
                'attr_medium' => AttributionTaxonomy::MEDIUM_NONE,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        if ($source === '') {
            return [
                'attr_source' => AttributionTaxonomy::SOURCE_ORGANIC,
                'attr_medium' => AttributionTaxonomy::MEDIUM_NONE,
                'attr_platform' => null,
                'attr_placement' => null,
            ];
        }

        return [
            'attr_source' => Str::limit($source, 32, ''),
            'attr_medium' => self::normalizeMedium($medium),
            'attr_platform' => null,
            'attr_placement' => $placementParam,
        ];
    }

    /**
     * @param  array{
     *     utm_source?: ?string,
     *     utm_medium?: ?string,
     *     utm_campaign?: ?string,
     *     utm_content?: ?string,
     *     utm_term?: ?string,
     *     platform?: ?string,
     *     placement?: ?string,
     *     referrer?: ?string,
     *     landing_path?: ?string
     * }  $input
     * @return array<string, ?string>
     */
    public static function normalizedPayload(array $input): array
    {
        $normalized = self::normalize($input);

        return array_filter($normalized, fn (?string $value) => filled($value));
    }

    private static function isMeta(?string $source, ?string $medium, string $haystack): bool
    {
        if (in_array($source, AttributionTaxonomy::metaRawSources(), true)) {
            return true;
        }

        return str_contains($haystack, 'fbclid')
            || str_contains($haystack, 'facebook')
            || str_contains($haystack, 'fb.com')
            || str_contains($haystack, 'instagram')
            || str_contains($haystack, 'igshid')
            || str_contains($haystack, ' meta ')
            || str_contains($medium ?? '', 'facebook_')
            || str_contains($medium ?? '', 'instagram_');
    }

    private static function isGoogle(?string $source, ?string $medium, string $haystack): bool
    {
        return $source === 'google'
            || str_contains($haystack, 'gclid')
            || str_contains($haystack, 'wbraid')
            || str_contains($haystack, 'gbraid')
            || (str_contains($haystack, 'google') && ! str_contains($haystack, 'instagram'));
    }

    private static function isYouTube(?string $source, string $haystack): bool
    {
        return $source === 'youtube'
            || str_contains($haystack, 'youtube')
            || str_contains($haystack, 'youtu.be');
    }

    private static function isWhatsApp(?string $source, ?string $medium, string $haystack): bool
    {
        return $source === 'whatsapp'
            || str_contains($haystack, 'whatsapp')
            || str_contains($haystack, 'wa.me')
            || str_contains($haystack, 'aisensy');
    }

    private static function isOrganic(?string $source, ?string $medium): bool
    {
        return in_array($source, ['organic', 'direct', '(direct)', 'none'], true)
            || str_contains($medium ?? '', 'organic');
    }

    private static function resolveMetaPlatform(
        ?string $source,
        ?string $medium,
        ?string $platformParam,
        string $haystack,
    ): ?string {
        if ($mapped = self::mapSiteSourceName($platformParam)) {
            return $mapped;
        }

        if (in_array($source, ['instagram', 'ig'], true)) {
            return AttributionTaxonomy::PLATFORM_INSTAGRAM;
        }

        if (in_array($source, ['facebook', 'fb'], true)) {
            return AttributionTaxonomy::PLATFORM_FACEBOOK;
        }

        if (str_contains($haystack, 'instagram') || str_contains($haystack, 'igshid')) {
            return AttributionTaxonomy::PLATFORM_INSTAGRAM;
        }

        if (str_contains($medium ?? '', 'instagram')) {
            return AttributionTaxonomy::PLATFORM_INSTAGRAM;
        }

        if (str_contains($medium ?? '', 'messenger')) {
            return AttributionTaxonomy::PLATFORM_MESSENGER;
        }

        if (str_contains($medium ?? '', 'audience_network') || str_contains($medium ?? '', 'audience network')) {
            return AttributionTaxonomy::PLATFORM_AUDIENCE_NETWORK;
        }

        if (str_contains($haystack, 'facebook') || str_contains($haystack, 'fbclid')) {
            return AttributionTaxonomy::PLATFORM_FACEBOOK;
        }

        if (str_contains($medium ?? '', 'facebook')) {
            return AttributionTaxonomy::PLATFORM_FACEBOOK;
        }

        return null;
    }

    private static function mapSiteSourceName(?string $value): ?string
    {
        return match ($value) {
            'fb' => AttributionTaxonomy::PLATFORM_FACEBOOK,
            'ig' => AttributionTaxonomy::PLATFORM_INSTAGRAM,
            'msg' => AttributionTaxonomy::PLATFORM_MESSENGER,
            'an' => AttributionTaxonomy::PLATFORM_AUDIENCE_NETWORK,
            'facebook' => AttributionTaxonomy::PLATFORM_FACEBOOK,
            'instagram' => AttributionTaxonomy::PLATFORM_INSTAGRAM,
            'messenger' => AttributionTaxonomy::PLATFORM_MESSENGER,
            'audience_network' => AttributionTaxonomy::PLATFORM_AUDIENCE_NETWORK,
            default => null,
        };
    }

    private static function placementFromMetaMedium(?string $medium): ?string
    {
        if ($medium === null || trim($medium) === '') {
            return null;
        }

        if (! str_contains(strtolower($medium), 'facebook')
            && ! str_contains(strtolower($medium), 'instagram')
            && ! str_contains(strtolower($medium), 'messenger')
            && ! str_contains(strtolower($medium), 'audience')) {
            return null;
        }

        return self::normalizePlacement($medium);
    }

    private static function normalizeMetaMedium(?string $medium, string $haystack): string
    {
        if ($medium === null || $medium === '') {
            return str_contains($haystack, 'fbclid') || str_contains($haystack, 'igshid')
                ? AttributionTaxonomy::MEDIUM_PAID_SOCIAL
                : AttributionTaxonomy::MEDIUM_ORGANIC_SOCIAL;
        }

        if (in_array($medium, ['paid', 'cpc', 'ppc', 'paid_social', 'paid-social'], true)) {
            return AttributionTaxonomy::MEDIUM_PAID_SOCIAL;
        }

        if (str_contains($medium, 'paid')) {
            return AttributionTaxonomy::MEDIUM_PAID_SOCIAL;
        }

        if (in_array($medium, ['social', 'referral'], true)) {
            return AttributionTaxonomy::MEDIUM_ORGANIC_SOCIAL;
        }

        if (str_contains($medium, 'facebook_') || str_contains($medium, 'instagram_')) {
            return AttributionTaxonomy::MEDIUM_PAID_SOCIAL;
        }

        return self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_PAID_SOCIAL;
    }

    private static function normalizeGoogleMedium(?string $medium, string $haystack): string
    {
        if (str_contains($haystack, 'gclid') || str_contains($haystack, 'wbraid') || str_contains($haystack, 'gbraid')) {
            return AttributionTaxonomy::MEDIUM_PAID_SEARCH;
        }

        if (in_array($medium, ['cpc', 'ppc', 'paid_search', 'paid-search'], true)) {
            return AttributionTaxonomy::MEDIUM_PAID_SEARCH;
        }

        if ($medium !== null && str_contains($medium, 'organic')) {
            return AttributionTaxonomy::MEDIUM_ORGANIC_SOCIAL;
        }

        return self::normalizeMedium($medium) ?? AttributionTaxonomy::MEDIUM_PAID_SEARCH;
    }

    private static function normalizeMedium(?string $medium): ?string
    {
        if ($medium === null || $medium === '') {
            return null;
        }

        $normalized = str_replace('-', '_', $medium);

        return Str::limit($normalized, 32, '');
    }

    private static function normalizePlacement(?string $placement): ?string
    {
        if ($placement === null || trim($placement) === '') {
            return null;
        }

        $slug = Str::of($placement)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $slug === '' ? null : Str::limit($slug, 64, '');
    }

    private static function lower(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
