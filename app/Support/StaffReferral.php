<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

class StaffReferral
{
    /**
     * First path segments that must never be usable as referral codes.
     *
     * @var list<string>
     */
    public const RESERVED_SEGMENTS = [
        'admin',
        'api',
        'bank-details',
        'donate',
        'give',
        'google-auth',
        'livewire',
        'marketer',
        'my-donations',
        'oauth2callback',
        'presence',
        'robots.txt',
        'sanctum',
        'sitemap.xml',
        'storage',
        'up',
        'vendor',
    ];

    public static function vanityUrlsEnabled(): bool
    {
        return (bool) config('donation.staff_vanity_urls_enabled', false);
    }

    public static function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtolower(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    public static function isReserved(?string $code): bool
    {
        $normalized = self::normalize($code);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::RESERVED_SEGMENTS, true);
    }

    public static function isValidFormat(?string $code): bool
    {
        $normalized = self::normalize($code);

        if ($normalized === null) {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized);
    }

    public static function findUserByCode(?string $code): ?User
    {
        $normalized = self::normalize($code);

        if ($normalized === null || self::isReserved($normalized)) {
            return null;
        }

        return User::query()->where('referral_code', $normalized)->first();
    }

    /**
     * @return array<string, string>
     */
    public static function attributionQuery(string $code): array
    {
        $normalized = self::normalize($code) ?? $code;

        return [
            'sid' => $normalized,
            'utm_source' => 'staff',
            'utm_medium' => 'referral',
        ];
    }

    /**
     * Append staff referral UTMs to a query array (first-touch: does not overwrite existing keys).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function mergeAttributionQuery(array $query, string $code): array
    {
        $normalized = self::normalize($code) ?? $code;

        foreach (self::attributionQuery($normalized) as $key => $value) {
            if (! filled($query[$key] ?? null)) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Build a tracked share URL using UTM query params (default) or legacy vanity paths.
     */
    public static function trackedShareUrl(string $absoluteOrPathUrl, ?string $referralCode): string
    {
        $code = self::normalize($referralCode);

        if ($code === null) {
            return $absoluteOrPathUrl;
        }

        if (self::vanityUrlsEnabled()) {
            return self::legacyVanityShareUrl($absoluteOrPathUrl, $code);
        }

        return self::utmShareUrl($absoluteOrPathUrl, $code);
    }

    /**
     * Canonical tracked URL for a path, merging optional extra query params from a request.
     *
     * @param  array<string, mixed>  $extraQuery
     */
    public static function canonicalTrackedUrl(string $path, string $referralCode, array $extraQuery = []): string
    {
        $code = self::normalize($referralCode) ?? $referralCode;
        $query = self::mergeAttributionQuery($extraQuery, $code);

        $path = $path === '' ? '/' : $path;
        $built = $path;

        if ($query !== []) {
            $built .= '?'.http_build_query($query);
        }

        return $built;
    }

    /**
     * URL parameters a marketer pastes into Meta Ads Manager.
     *
     * `sid` is the user's referral code and is reused across every campaign.
     * Meta expands the `{{...}}` tokens at click time for campaign/ad set names and IDs.
     *
     * Returned raw (not urlencoded) because Meta rejects encoded braces.
     */
    public static function metaAdParameters(?string $referralCode, ?string $utmMedium = null): string
    {
        $code = self::normalize($referralCode);
        $medium = self::utmMediumFromName($utmMedium) ?? $code ?? 'meta';

        $parameters = [
            'utm_source' => 'meta',
            'utm_medium' => $medium,
            'utm_campaign' => '{{campaign.name}}',
            'utm_content' => '{{ad.name}}',
        ];

        if ($code !== null) {
            $parameters['sid'] = $code;
        }

        $parameters['utm_id'] = '{{campaign.id}}';
        $parameters['utm_term'] = '{{adset.id}}';

        return collect($parameters)
            ->map(fn (string $value, string $key) => $key.'='.$value)
            ->implode('&');
    }

    /**
     * Full landing URL with Meta dynamic parameters appended, ready to paste as an ad destination.
     */
    public static function metaAdUrl(string $absoluteOrPathUrl, ?string $referralCode, ?string $utmMedium = null): string
    {
        $separator = str_contains($absoluteOrPathUrl, '?') ? '&' : '?';

        return $absoluteOrPathUrl.$separator.self::metaAdParameters($referralCode, $utmMedium);
    }

    public static function utmMediumFromName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $first = strtolower(trim(explode(' ', trim($name))[0] ?? ''));
        $first = preg_replace('/[^a-z0-9]+/', '', $first) ?? '';

        return $first !== '' ? $first : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(?int $ignoreUserId = null): array
    {
        $unique = Rule::unique('users', 'referral_code');

        if ($ignoreUserId !== null) {
            $unique = $unique->ignore($ignoreUserId);
        }

        return [
            'referral_code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $unique,
                Rule::notIn(self::RESERVED_SEGMENTS),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'referral_code.regex' => 'Referral code may only contain lowercase letters, numbers, and hyphens.',
            'referral_code.unique' => 'This referral code is already assigned to another user.',
            'referral_code.not_in' => 'This referral code is reserved. Please choose another.',
        ];
    }

    private static function utmShareUrl(string $absoluteOrPathUrl, string $code): string
    {
        $parts = parse_url($absoluteOrPathUrl);

        if ($parts === false) {
            return $absoluteOrPathUrl;
        }

        $path = $parts['path'] ?? '/';
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query = self::mergeAttributionQuery($query, $code);

        $rebuilt = ($parts['scheme'] ?? null) && ($parts['host'] ?? null)
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path
            : $path;

        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query);
        }

        if (! empty($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }

    private static function legacyVanityShareUrl(string $absoluteOrPathUrl, string $code): string
    {
        $parts = parse_url($absoluteOrPathUrl);

        if ($parts === false) {
            return $absoluteOrPathUrl;
        }

        $path = $parts['path'] ?? '/';
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if ($path === '/' || $path === '') {
            $path = '/'.$code;
        } elseif (str_starts_with($path, '/donate/') || str_starts_with($path, '/give/')) {
            $path = '/'.$code.$path;
        } else {
            $query = self::mergeAttributionQuery($query, $code);
        }

        $rebuilt = ($parts['scheme'] ?? null) && ($parts['host'] ?? null)
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path
            : $path;

        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query);
        }

        if (! empty($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
