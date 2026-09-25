<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DonationOrder;
use App\Models\LinkTrackingSummary;
use App\Models\LinkTrackingVisit;
use App\Support\Attribution\AttributionParameters;
use App\Support\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LinkTrackingService
{
    public function __construct(
        private AnalyticsGeoLocator $geoLocator,
    ) {}

    /** @var list<string> */
    public const CAMPAIGN_FIELDS = [
        'sid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_id',
        'utm_term',
        'fbclid',
        'amt',
        'ptype',
        'pid',
        'aid',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{recorded: bool, unique?: bool, visit_id?: int, sid?: string, page_path?: string, reason?: string}
     */
    public function recordVisit(array $payload, Request $request): array
    {
        $payload = AttributionParameters::withSidAlias(
            AttributionParameters::decodeTrackingPayload($payload)
        );

        if (! $this->hasCampaignParams($payload)) {
            return [
                'recorded' => false,
                'reason' => 'no_tracking_params',
            ];
        }

        $visitorId = (string) $payload['visitor_id'];
        $sid = trim((string) ($payload['sid'] ?? ''));
        $pagePath = $this->pagePath(
            $payload['page_path'] ?? null,
            $payload['landing_url'] ?? null,
        );
        $now = now();

        $existing = LinkTrackingVisit::query()
            ->where('visitor_id', $visitorId)
            ->where('sid', $sid)
            ->where('page_path', $pagePath)
            ->first();

        if ($existing) {
            return [
                'recorded' => true,
                'unique' => false,
                'visit_id' => $existing->id,
                'sid' => $existing->sid,
                'page_path' => $existing->page_path,
            ];
        }

        $isUnique = ! LinkTrackingVisit::query()
            ->where('visitor_id', $visitorId)
            ->where('sid', $sid)
            ->exists();

        $userAgent = $this->nullableString($payload['user_agent'] ?? null, 512)
            ?? $this->nullableString((string) $request->userAgent(), 512);
        $deviceType = DeviceType::normalize(isset($payload['device_type']) ? (string) $payload['device_type'] : null)
            ?? DeviceType::fromUserAgent($userAgent);

        $geo = $this->geoLocator->donationLocationFromRequest($request);

        $visit = LinkTrackingVisit::query()->create([
            'visitor_id' => $visitorId,
            'sid' => $sid,
            'utm_source' => $this->nullableString($payload['utm_source'] ?? null, 120),
            'utm_medium' => $this->nullableString($payload['utm_medium'] ?? null, 120),
            'utm_campaign' => $this->nullableString($payload['utm_campaign'] ?? null, 120),
            'utm_content' => $this->nullableString($payload['utm_content'] ?? null, 120),
            'utm_id' => $this->nullableString($payload['utm_id'] ?? null, 120),
            'utm_term' => $this->nullableString($payload['utm_term'] ?? null, 120),
            'fbclid' => $this->nullableString($payload['fbclid'] ?? null, 255),
            'amt' => $this->nullableString($payload['amt'] ?? null, 32),
            'ptype' => $this->nullableString($payload['ptype'] ?? null, 32),
            'extra_params' => $this->extraParams($payload),
            'landing_url' => $this->nullableString($payload['landing_url'] ?? null, 2048),
            'page_path' => $pagePath,
            'referrer' => $this->nullableString($payload['referrer'] ?? null, 512),
            'ip_address' => $geo['ip_address'] ?? $request->ip(),
            'ip_country_code' => $geo['ip_country_code'] ?? null,
            'ip_country_name' => $geo['ip_country_name'] ?? null,
            'ip_region_name' => $geo['ip_region_name'] ?? null,
            'ip_city' => $geo['ip_city'] ?? null,
            'ip_postal_code' => $geo['ip_postal_code'] ?? null,
            'ip_lat' => $geo['ip_lat'] ?? null,
            'ip_lng' => $geo['ip_lng'] ?? null,
            'ip_timezone' => $geo['ip_timezone'] ?? null,
            'ip_asn' => $geo['ip_asn'] ?? null,
            'ip_isp' => $geo['ip_isp'] ?? null,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'is_unique' => $isUnique,
            'converted' => false,
        ]);

        $this->bumpSummaryForClick($visit, $now, $isUnique);

        return [
            'recorded' => true,
            'unique' => $isUnique,
            'visit_id' => $visit->id,
            'sid' => $visit->sid,
            'page_path' => $visit->page_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function attachToOrder(DonationOrder $order, array $payload, ?Request $request = null): ?LinkTrackingVisit
    {
        $payload = AttributionParameters::withSidAlias(
            AttributionParameters::decodeTrackingPayload($payload)
        );
        $visitorId = trim((string) ($payload['visitor_id'] ?? ''));

        if ($visitorId === '' && $this->hasCampaignParams($payload)) {
            $visitorId = (string) Str::uuid();
            $payload['visitor_id'] = $visitorId;
        }

        if ($visitorId === '') {
            return null;
        }

        if (empty($payload['page_path']) && ! empty($payload['landing_path'])) {
            $payload['page_path'] = $payload['landing_path'];
        }

        if ($this->hasCampaignParams($payload)) {
            $this->recordVisit($payload, $request ?? request());
        }

        $sid = trim((string) ($payload['sid'] ?? ''));

        $visit = LinkTrackingVisit::query()
            ->where('visitor_id', $visitorId)
            ->where('sid', $sid)
            ->orderBy('id')
            ->first();

        if ($visit === null) {
            return null;
        }

        if ($visit->donation_order_id !== $order->id) {
            $visit->update(['donation_order_id' => $order->id]);
        }

        return $visit->fresh();
    }

    public function markConverted(DonationOrder $order): ?LinkTrackingVisit
    {
        $visit = $this->resolveVisitForConversion($order);

        if ($visit === null) {
            return null;
        }

        if ($visit->converted) {
            // Already converted for this order, or for a different order — do not reassign.
            // Period "Donated" counts use paid orders, so leaving the first conversion is safe.
            return $visit->fresh();
        }

        if ((int) ($visit->donation_order_id ?? 0) !== (int) $order->id) {
            $visit->update(['donation_order_id' => $order->id]);
            $visit = $visit->fresh();
        }

        $amount = round((float) $order->total_amount, 2);
        $convertedAt = $order->paid_at ?? now();

        $visit->update([
            'converted' => true,
            'converted_amount' => $amount,
            'converted_at' => $convertedAt,
        ]);

        $this->bumpSummaryForConversion($visit, $amount, $convertedAt);

        return $visit->fresh();
    }

    /**
     * Resolve (and optionally attach) a click visit for a paid order.
     *
     * Prefer an existing link by donation_order_id, then sid+visitor from checkout
     * analytics, then the latest unconverted visit for the partner sid within a
     * short lookback before payment.
     */
    public function resolveVisitForConversion(DonationOrder $order): ?LinkTrackingVisit
    {
        $visit = LinkTrackingVisit::query()
            ->where('donation_order_id', $order->id)
            ->first();

        if ($visit !== null) {
            return $visit;
        }

        $sid = $this->resolveSidForOrder($order);

        if ($sid === null) {
            return null;
        }

        $visitorId = $this->resolveVisitorIdForOrder($order);

        if ($visitorId !== null) {
            $visit = LinkTrackingVisit::query()
                ->where('sid', $sid)
                ->where('visitor_id', $visitorId)
                ->orderByDesc('id')
                ->first();

            if ($visit !== null) {
                if (! $visit->converted && (int) ($visit->donation_order_id ?? 0) !== (int) $order->id) {
                    $visit->update(['donation_order_id' => $order->id]);

                    return $visit->fresh();
                }

                if ((int) ($visit->donation_order_id ?? 0) === (int) $order->id || ! $visit->converted) {
                    return $visit;
                }
            }
        }

        return $this->attachLatestUnconvertedVisit($order, $sid);
    }

    /**
     * Attach the latest unconverted visit for this sid within 7 days before pay.
     */
    public function attachLatestUnconvertedVisit(DonationOrder $order, ?string $sid = null, bool $attach = true): ?LinkTrackingVisit
    {
        $sid = $sid ?? $this->resolveSidForOrder($order);

        if ($sid === null) {
            return null;
        }

        $paidAt = $order->paid_at ?? now();
        $lookbackStart = $paidAt->copy()->subDays(7);

        $visit = LinkTrackingVisit::query()
            ->where('sid', $sid)
            ->where('converted', false)
            ->where(function ($query) use ($order): void {
                $query->whereNull('donation_order_id')
                    ->orWhere('donation_order_id', $order->id);
            })
            ->where('created_at', '>=', $lookbackStart)
            ->where('created_at', '<=', $paidAt)
            ->orderByDesc('id')
            ->first();

        if ($visit === null) {
            return null;
        }

        if ($attach && (int) ($visit->donation_order_id ?? 0) !== (int) $order->id) {
            $visit->update(['donation_order_id' => $order->id]);
        }

        return $attach ? $visit->fresh() : $visit;
    }

    private function resolveSidForOrder(DonationOrder $order): ?string
    {
        $code = trim((string) ($order->partner_code ?? ''));

        if ($code !== '') {
            return $code;
        }

        $legacy = trim((string) ($order->utm_content ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    private function resolveVisitorIdForOrder(DonationOrder $order): ?string
    {
        $event = AnalyticsEvent::query()
            ->where('donation_order_id', $order->id)
            ->where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED)
            ->latest('id')
            ->first();

        if ($event === null) {
            return null;
        }

        // session_id is the closest stable client identifier when visitor_id is not stored on events
        $sessionId = trim((string) ($event->session_id ?? ''));

        if ($sessionId === '') {
            return null;
        }

        // Prefer a visit whose visitor_id equals the analytics session (some clients reuse the same UUID).
        $match = LinkTrackingVisit::query()
            ->where('visitor_id', $sessionId)
            ->orderByDesc('id')
            ->value('visitor_id');

        return is_string($match) && $match !== '' ? $match : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasCampaignParams(array $payload): bool
    {
        foreach (self::CAMPAIGN_FIELDS as $field) {
            $value = trim((string) ($payload[$field] ?? ''));

            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function trackingRules(): array
    {
        return [
            'visitor_id' => ['nullable', 'uuid'],
            'sid' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_id' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
            'fbclid' => ['nullable', 'string', 'max:2048'],
            'aid' => ['nullable', 'string', 'max:120'],
            'amt' => ['nullable', 'string', 'max:32'],
            'ptype' => ['nullable', 'string', 'max:32'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'page_path' => ['nullable', 'string', 'max:255'],
            'source_channel' => ['nullable', 'string', 'max:32'],
            'user_agent' => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'max:16'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extraParams(array $payload): ?array
    {
        $extra = is_array($payload['extra_params'] ?? null) ? $payload['extra_params'] : [];
        unset($extra['pid']);

        foreach (['aid'] as $key) {
            $value = $this->nullableString($payload[$key] ?? null, 120);
            if ($value !== null) {
                $extra[$key] = $value;
            }
        }

        return $extra === [] ? null : $extra;
    }

    private function bumpSummaryForClick(LinkTrackingVisit $visit, mixed $now, bool $isUnique): void
    {
        $summary = LinkTrackingSummary::query()->firstOrNew(['sid' => $visit->sid]);

        $summary->total_clicks = (int) $summary->total_clicks + 1;
        if ($isUnique) {
            $summary->unique_visitors = (int) $summary->unique_visitors + 1;
        }
        $summary->first_click_at = $summary->first_click_at ?? $now;
        $summary->last_click_at = $now;
        $summary->utm_source = $visit->utm_source ?: $summary->utm_source;
        $summary->utm_medium = $visit->utm_medium ?: $summary->utm_medium;
        $summary->utm_campaign = $visit->utm_campaign ?: $summary->utm_campaign;
        $summary->save();
    }

    private function bumpSummaryForConversion(LinkTrackingVisit $visit, float $amount, mixed $convertedAt): void
    {
        $summary = LinkTrackingSummary::query()->firstOrNew(['sid' => $visit->sid]);

        $totalDonations = (int) $summary->total_donations + 1;
        $totalAmount = round((float) $summary->total_amount + $amount, 2);

        $summary->total_donations = $totalDonations;
        $summary->total_amount = $totalAmount;
        $summary->average_amount = $totalDonations > 0
            ? round($totalAmount / $totalDonations, 2)
            : 0;
        $summary->last_donation_at = $convertedAt;
        $summary->save();
    }

    private function pagePath(mixed $pagePath, mixed $landingUrl): string
    {
        $explicit = $this->nullableString($pagePath, 255);
        if ($explicit !== null) {
            return $explicit[0] === '/' ? $explicit : '/'.$explicit;
        }

        $url = $this->nullableString($landingUrl, 2048);
        if ($url !== null) {
            $parsed = parse_url($url, PHP_URL_PATH);
            if (is_string($parsed) && $parsed !== '') {
                return mb_substr($parsed, 0, 255);
            }
        }

        return '/';
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $max);
    }
}
