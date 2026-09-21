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
     * Meta ad names often start with the marketer's first name before a pipe:
     * e.g. "Urvi | Sales | Vishal Fodder R | 200".
     *
     * When that prefix uniquely matches a partner, it is stronger evidence than a
     * mismatched sid on a shared/wrong destination URL.
     */
    public static function metaAdNamePrefix(?string $utmContent): ?string
    {
        $content = trim((string) $utmContent);

        if ($content === '' || ! str_contains($content, '|')) {
            return null;
        }

        $prefix = trim(explode('|', $content, 2)[0]);

        if ($prefix === '' || mb_strlen($prefix) < 2) {
            return null;
        }

        // Numeric / date prefixes like "12/08" are not partner names.
        if (preg_match('/^[0-9]/', $prefix) === 1) {
            return null;
        }

        return $prefix;
    }

    /**
     * Resolve the partner uniquely identified by a Meta ad-name prefix, if any.
     */
    public static function partnerFromMetaAdNamePrefix(?string $utmContent): ?User
    {
        $prefix = self::metaAdNamePrefix($utmContent);

        if ($prefix === null) {
            return null;
        }

        $matches = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->get(['id', 'name', 'referral_code'])
            ->filter(function (User $user) use ($prefix): bool {
                $name = trim((string) $user->name);

                if ($name === '') {
                    return false;
                }

                if (strcasecmp($name, $prefix) === 0) {
                    return true;
                }

                $firstName = trim(explode(' ', $name, 2)[0]);

                return $firstName !== '' && strcasecmp($firstName, $prefix) === 0;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function whereUtmContentMatchesPartnerAd($query, User $partner, string $column = 'utm_content'): void
    {
        $name = trim((string) $partner->name);
        $firstName = $name !== '' ? trim(explode(' ', $name, 2)[0]) : '';

        if ($firstName === '' || mb_strlen($firstName) < 2) {
            $query->whereRaw('0 = 1');

            return;
        }

        $escaped = addcslashes($firstName, '%_\\');
        $query->where(function ($builder) use ($column, $escaped): void {
            $builder->where($column, 'like', $escaped.' |%')
                ->orWhere($column, 'like', $escaped.'|%');
        });
    }

    /**
     * First names that uniquely identify one marketer for "Name | …" Meta ads.
     *
     * @return list<string>
     */
    public static function uniquePartnerAdNamePrefixes(): array
    {
        $firstNames = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->pluck('name')
            ->map(function ($name) {
                $trimmed = trim((string) $name);

                if ($trimmed === '') {
                    return null;
                }

                $first = trim(explode(' ', $trimmed, 2)[0]);

                return mb_strlen($first) >= 2 ? $first : null;
            })
            ->filter()
            ->values();

        $counts = [];
        foreach ($firstNames as $first) {
            $key = mb_strtolower((string) $first);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $firstNames
            ->filter(fn (string $first) => ($counts[mb_strtolower($first)] ?? 0) === 1)
            ->unique(fn (string $first) => mb_strtolower($first))
            ->values()
            ->all();
    }

    /**
     * Exclude rows whose Meta ad name clearly credits a known marketer
     * (so sid=pr cannot claim an "Urvi | …" ad).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function whereUtmContentNotForeignPartnerAd($query, string $column = 'utm_content'): void
    {
        $prefixes = self::uniquePartnerAdNamePrefixes();

        if ($prefixes === []) {
            return;
        }

        $query->where(function ($builder) use ($column, $prefixes): void {
            $builder->whereNull($column)
                ->orWhere($column, '')
                ->orWhere(function ($inner) use ($column, $prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $escaped = addcslashes($prefix, '%_\\');
                        $inner->where($column, 'not like', $escaped.' |%')
                            ->where($column, 'not like', $escaped.'|%');
                    }
                });
        });
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
