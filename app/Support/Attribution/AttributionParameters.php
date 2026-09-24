<?php

namespace App\Support\Attribution;

use App\Models\User;
use App\Support\StaffReferral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tracking parameters accepted on landing pages and checkout payloads.
 *
 * Meta fills the dynamic tokens at click time. `sid` is the marketer's
 * referral code and is reused across every campaign they run.
 */
class AttributionParameters
{
    /**
     * Raw UTM keys persisted as-is for audit/reporting.
     *
     * @var list<string>
     */
    public const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    /**
     * Query parameter => donation_orders column.
     *
     * `sid` is the marketer referral code, not a Meta ad set id.
     *
     * @var array<string, string>
     */
    public const ID_KEYS = [
        'utm_id' => 'meta_campaign_id',
        'aid' => 'meta_ad_id',
    ];

    /**
     * Attribution columns shared by donation_orders and donation_subscriptions.
     *
     * Recurring orders are built by the webhook long after the original request is
     * gone, so they inherit these values from their subscription.
     *
     * @var list<string>
     */
    public const PERSISTED_COLUMNS = [
        'source_channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'attr_source',
        'attr_medium',
        'attr_platform',
        'attr_placement',
        'partner_user_id',
        'partner_code',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'referrer',
        'landing_path',
        'device_type',
    ];

    /**
     * Copy the persisted attribution columns off a model, dropping empty values.
     *
     * @return array<string, int|string>
     */
    public static function inheritFrom(Model $source): array
    {
        $columns = [];

        foreach (self::PERSISTED_COLUMNS as $column) {
            $value = $source->getAttribute($column);

            if ($value !== null && $value !== '') {
                $columns[$column] = $value;
            }
        }

        return $columns;
    }

    /**
     * Every tracking key captured from the URL and persisted in the attribution cookie.
     *
     * @return list<string>
     */
    public static function trackingKeys(): array
    {
        return [
            ...self::UTM_KEYS,
            'platform',
            'placement',
            'sid',
            'utm_id',
            'aid',
            'pid',
        ];
    }

    /**
     * Meta ad identifiers are numeric strings; reject anything else so unresolved
     * tokens like "{{campaign.id}}" never reach the database.
     */
    public static function normalizeAdId(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '' || ! preg_match('/^[0-9]{1,40}$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    public static function normalizePartnerCode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = StaffReferral::normalize($value);

        if ($normalized === null || ! StaffReferral::isValidFormat($normalized)) {
            return null;
        }

        return Str::limit($normalized, 40, '');
    }

    /**
     * Decode leftover application/x-www-form-urlencoded UTM values.
     *
     * Meta often encodes campaign names once, then the click URL encodes them
     * again. The browser only undoes one pass, so values like
     * `Ashwini+%7C+10%2F08` would otherwise be stored as-is.
     */
    public static function decodeQueryValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $current = trim($value);

        if ($current === '' || self::isAbsoluteUrl($current)) {
            return $current === '' ? null : $current;
        }

        for ($i = 0; $i < 3; $i++) {
            if (! self::looksUrlEncoded($current)) {
                break;
            }

            $decoded = rawurldecode(str_replace('+', ' ', $current));

            if ($decoded === $current) {
                break;
            }

            $current = trim($decoded);
        }

        return $current === '' ? null : $current;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function decodeTrackingPayload(array $payload): array
    {
        foreach ([...self::UTM_KEYS, 'utm_id'] as $key) {
            if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
                continue;
            }

            $payload[$key] = self::decodeQueryValue($payload[$key]);
        }

        if (isset($payload['extra_params']) && is_array($payload['extra_params'])) {
            foreach ($payload['extra_params'] as $key => $value) {
                if (is_string($value)) {
                    $payload['extra_params'][$key] = self::decodeQueryValue($value);
                }
            }
        }

        return $payload;
    }

    /**
     * Copy a leftover `pid` or ChatGPT `utm_sid` onto `sid` when the payload has
     * no referral code yet.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function withSidAlias(array $payload): array
    {
        $sid = trim((string) ($payload['sid'] ?? ''));
        $pid = trim((string) ($payload['pid'] ?? ''));
        $utmSid = trim((string) ($payload['utm_sid'] ?? ''));
        $pidCode = self::normalizePartnerCode($pid !== '' ? $pid : null);
        $sidIsAdset = $sid !== '' && self::normalizeAdId($sid) !== null;

        if ($sid === '' && $utmSid !== '') {
            $payload['sid'] = $utmSid;
            $sid = $utmSid;
            $sidIsAdset = self::normalizeAdId($sid) !== null;
        }

        if ($sid === '' && $pid !== '') {
            $payload['sid'] = $pid;
        } elseif ($sidIsAdset && $pidCode !== null) {
            if (trim((string) ($payload['utm_term'] ?? '')) === '') {
                $payload['utm_term'] = $sid;
            }

            $payload['sid'] = $pidCode;
        }

        unset($payload['utm_sid']);

        return $payload;
    }

    /**
     * The partner code a tracking payload points at, without touching the database.
     *
     * Prefer `sid` (the referral code). Fall back to a leftover `pid`, then the
     * staff share convention (`utm_source=staff`, `utm_content={code}`).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function partnerCodeFromPayload(array $payload): ?string
    {
        $sid = $payload['sid'] ?? null;
        $code = null;

        if (self::normalizeAdId($sid) === null) {
            $code = self::normalizePartnerCode(is_string($sid) ? $sid : null);
        }

        if ($code === null) {
            $code = self::normalizePartnerCode($payload['pid'] ?? null);
        }

        if ($code === null) {
            $code = self::normalizePartnerCode($payload['utm_sid'] ?? null);
        }

        if ($code === null && ($payload['utm_source'] ?? null) === 'staff') {
            $code = self::normalizePartnerCode($payload['utm_content'] ?? null);
        }

        return $code;
    }

    /**
     * Resolve the partner columns for a tracking payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{partner_user_id: ?int, partner_code: ?string}
     */
    public static function resolvePartner(array $payload): array
    {
        $code = self::partnerCodeFromPayload($payload);

        if ($code === null) {
            return ['partner_user_id' => null, 'partner_code' => null];
        }

        $partner = User::query()->where('referral_code', $code)->first();

        return [
            'partner_user_id' => $partner?->id,
            'partner_code' => $code,
        ];
    }

    /**
     * Map a tracking payload onto the attribution columns shared by
     * donation_orders and analytics_events.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, int|string|null>
     */
    public static function columnsFromPayload(array $payload): array
    {
        $columns = [];

        foreach (self::ID_KEYS as $key => $column) {
            $columns[$column] = self::normalizeAdId($payload[$key] ?? null);
        }

        if (empty($columns['meta_adset_id'])) {
            $columns['meta_adset_id'] = self::normalizeAdId($payload['utm_term'] ?? null)
                ?? self::normalizeAdId($payload['sid'] ?? null);
        }

        return array_filter(
            [...$columns, ...self::resolvePartner($payload)],
            fn ($value) => $value !== null && $value !== ''
        );
    }

    private static function looksUrlEncoded(string $value): bool
    {
        return (bool) preg_match('/%[0-9A-Fa-f]{2}/', $value)
            || (bool) preg_match('/[^\s]\+[^\s]/', $value);
    }

    private static function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
