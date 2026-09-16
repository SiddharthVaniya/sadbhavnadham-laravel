<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\DonationCampaign;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Support\Attribution\AttributionNormalizer;
use App\Support\Attribution\AttributionParameters;
use App\Support\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AnalyticsService
{
    private const SESSION_COOKIE = 'donation_analytics_session';

    public const UTM_COOKIE = 'donation_analytics_utm';

    public function __construct(
        private AnalyticsGeoLocator $geoLocator,
        private DonationAttributionService $donationAttribution,
    ) {}

    public function sessionId(Request $request): string
    {
        $existing = $request->cookie(self::SESSION_COOKIE);

        if (is_string($existing) && Str::isUuid($existing)) {
            return $existing;
        }

        $sessionId = (string) Str::uuid();

        Cookie::queue(
            self::SESSION_COOKIE,
            $sessionId,
            60 * 24 * 30,
            '/',
            null,
            null,
            true,
            false,
            'lax'
        );

        return $sessionId;
    }

    /**
     * Persist first-touch attribution from the current request without logging a visit.
     * Used on public pages that are not home/cause/campaign (e.g. bank details).
     */
    public function captureFromRequest(Request $request): void
    {
        $this->utmFromRequest($request);
    }

    /**
     * Next.js / WordPress checkout posts JSON from sadbhavnadham.org.
     * A leftover host-only cookie on donate.* must not override that payload.
     */
    public function discardHostUtmCookie(Request $request): void
    {
        $request->cookies->remove(self::UTM_COOKIE);
    }

    public function trackVisitHome(Request $request): void
    {
        $this->record($request, AnalyticsEvent::TYPE_VISIT_HOME, [
            'path' => '/',
        ]);
    }

    public function trackVisitCause(Request $request, Cause $cause): void
    {
        $this->record($request, AnalyticsEvent::TYPE_VISIT_CAUSE, [
            'cause_id' => $cause->id,
            'path' => '/donate/'.$cause->slug,
        ]);
    }

    public function trackVisitCampaign(Request $request, DonationCampaign $campaign): void
    {
        $this->record($request, AnalyticsEvent::TYPE_VISIT_CAMPAIGN, [
            'cause_id' => $campaign->cause_id,
            'path' => '/give/'.$campaign->slug,
        ]);
    }

    public function trackCheckoutStarted(Request $request, DonationOrder $order): void
    {
        $causeId = $order->items()->value('cause_id');
        $path = $this->attributionLandingPath($request);
        $attribution = $this->attributionFromRequest($request);
        $referrer = Str::limit((string) $request->headers->get('referer'), 512, '');

        $this->record($request, AnalyticsEvent::TYPE_CHECKOUT_STARTED, [
            'session_id' => $this->sessionId($request),
            'cause_id' => $causeId,
            'donation_order_id' => $order->id,
            'amount' => $order->total_amount,
            'path' => $path,
        ]);

        $sourceChannel = $request->input('source_channel');

        $this->donationAttribution->stampFromCheckout($order, [
            ...$attribution,
            'source_channel' => is_string($sourceChannel) ? $sourceChannel : null,
            'referrer' => $referrer !== '' ? $referrer : null,
            'landing_path' => $path,
        ]);

        $this->stampDonationIpLocation($order, $request);
    }

    public function trackSubscriptionCheckoutStarted(Request $request, DonationSubscription $subscription): void
    {
        $path = $this->attributionLandingPath($request);
        $attribution = $this->attributionFromRequest($request);
        $referrer = Str::limit((string) $request->headers->get('referer'), 512, '');

        $this->record($request, AnalyticsEvent::TYPE_SUBSCRIPTION_CHECKOUT_STARTED, [
            'session_id' => $this->sessionId($request),
            'cause_id' => $subscription->cause_id,
            'amount' => $subscription->total_amount,
            'path' => $path,
        ]);

        $sourceChannel = $request->input('source_channel');

        $this->donationAttribution->stampSubscriptionFromCheckout($subscription, [
            ...$attribution,
            'source_channel' => is_string($sourceChannel) ? $sourceChannel : null,
            'referrer' => $referrer !== '' ? $referrer : null,
            'landing_path' => $path,
        ]);
    }

    public function trackDonationPaid(DonationOrder $order): void
    {
        if ($this->eventExistsForOrder(AnalyticsEvent::TYPE_DONATION_PAID, $order->id)) {
            return;
        }

        $order->refresh();
        $causeId = $order->items()->value('cause_id');

        AnalyticsEvent::create([
            'event_type' => AnalyticsEvent::TYPE_DONATION_PAID,
            'session_id' => null,
            'cause_id' => $causeId,
            'donation_order_id' => $order->id,
            'amount' => $order->total_amount,
            'referrer' => $order->referrer,
            'device_type' => $order->device_type,
            'utm_source' => $order->utm_source,
            'utm_medium' => $order->utm_medium,
            'utm_campaign' => $order->utm_campaign,
            'utm_content' => $order->utm_content,
            'utm_term' => $order->utm_term,
            'attr_source' => $order->attr_source,
            'attr_medium' => $order->attr_medium,
            'attr_platform' => $order->attr_platform,
            'attr_placement' => $order->attr_placement,
            'partner_user_id' => $order->partner_user_id,
            'partner_code' => $order->partner_code,
            'meta_campaign_id' => $order->meta_campaign_id,
            'meta_adset_id' => $order->meta_adset_id,
            'meta_ad_id' => $order->meta_ad_id,
            'path' => $order->landing_path,
            ...$this->locationFromOrder($order),
            'created_at' => now(),
        ]);
    }

    public function trackDonationFailed(DonationOrder $order): void
    {
        if ($this->eventExistsForOrder(AnalyticsEvent::TYPE_DONATION_FAILED, $order->id)) {
            return;
        }

        $causeId = $order->items()->value('cause_id');

        AnalyticsEvent::create([
            'event_type' => AnalyticsEvent::TYPE_DONATION_FAILED,
            'session_id' => null,
            'cause_id' => $causeId,
            'donation_order_id' => $order->id,
            'amount' => $order->total_amount,
            ...$this->locationFromOrder($order),
            'created_at' => now(),
        ]);
    }

    private function record(Request $request, string $eventType, array $attributes = []): void
    {
        if ($request->is('admin/*')) {
            return;
        }

        // IP location lookup only for donate/checkout — not visit/click tracking.
        $locateIp = in_array($eventType, [
            AnalyticsEvent::TYPE_CHECKOUT_STARTED,
            AnalyticsEvent::TYPE_SUBSCRIPTION_CHECKOUT_STARTED,
        ], true);

        $geo = $locateIp
            ? $this->geoLocator->fromRequest($request)
            : $this->geoLocator->emptyLocation();

        $sessionId = $this->sessionId($request);

        AnalyticsEvent::create([
            'event_type' => $eventType,
            'session_id' => $sessionId,
            'referrer' => Str::limit((string) $request->headers->get('referer'), 512, ''),
            ...$this->attributionFromRequest($request),
            ...$this->normalizedEventAttributes($request),
            ...$this->identifierColumnsFromRequest($request),
            'ip_address' => $geo['ip_address'] ?? null,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'region_name' => $geo['region_name'] ?? null,
            'city' => $geo['city'] ?? null,
            ...$attributes,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function attributionFromRequest(Request $request): array
    {
        $utm = $this->utmFromRequest($request);

        return [
            'device_type' => $this->deviceType($request),
            'utm_source' => $utm['utm_source'] ?? null,
            'utm_medium' => $utm['utm_medium'] ?? null,
            'utm_campaign' => $utm['utm_campaign'] ?? null,
            'utm_content' => $utm['utm_content'] ?? null,
            'utm_term' => $utm['utm_term'] ?? null,
            'platform' => $utm['platform'] ?? null,
            'placement' => $utm['placement'] ?? null,
            'pid' => $utm['pid'] ?? null,
            'utm_id' => $utm['utm_id'] ?? null,
            'sid' => $utm['sid'] ?? null,
            'aid' => $utm['aid'] ?? null,
        ];
    }

    /**
     * Partner + Meta ad identifier columns for an analytics event row.
     *
     * @return array<string, int|string|null>
     */
    private function identifierColumnsFromRequest(Request $request): array
    {
        return AttributionParameters::columnsFromPayload($this->utmFromRequest($request));
    }

    /**
     * @return array<string, string>
     */
    private function utmFromRequest(Request $request): array
    {
        $incoming = array_filter(
            array_merge(
                $this->inferUtmFromClickIdsAndReferrer($request),
                $this->explicitUtmFromRequest($request),
            ),
            fn ($value) => is_string($value) && trim($value) !== ''
        );

        $viewLandingPath = $this->firstTouchLandingPath($request);

        if ($viewLandingPath !== null) {
            $incoming['landing_path'] = $viewLandingPath;
        }

        $stored = $this->readUtmCookie($request);
        $payload = $this->firstTouchMerge($stored, $incoming);

        if ($payload === []) {
            return [];
        }

        if ($payload !== $stored) {
            $this->queueUtmCookie($payload);
        }

        return $payload;
    }

    /**
     * Attribution rules (deterministic):
     *
     * 1. Marketing UTMs (`utm_*`, platform, placement, Meta ad IDs) are first-touch
     *    for 30 days. The campaign that originally acquired the visitor is never
     *    replaced by a later URL.
     * 2. Partner credit (`sid` / leftover `pid` / staff `utm_content`) is a
     *    separate first-touch slot. A later employee link can FILL sid if it is
     *    still empty, but never replace an already captured partner. A later
     *    Meta link never clears sid.
     * 3. Empty keys may be filled from a later request (e.g. Meta first without an
     *    ad id, then a click that includes `aid`).
     *
     * This lets an order store both `utm_source=meta` and `partner_user_id=…`
     * when a visitor hits an ad and later opens an employee's tracking link.
     *
     * @param  array<string, string>  $stored
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function firstTouchMerge(array $stored, array $incoming): array
    {
        $payload = $stored;

        foreach ($incoming as $key => $value) {
            if ($key === 'pid' || $key === 'sid') {
                continue;
            }

            if (! filled($payload[$key] ?? null)) {
                $payload[$key] = $value;
            }
        }

        $storedAdset = AttributionParameters::normalizeAdId($stored['utm_term'] ?? null)
            ?? AttributionParameters::normalizeAdId($stored['sid'] ?? null);
        $incomingAdset = AttributionParameters::normalizeAdId($incoming['utm_term'] ?? null)
            ?? AttributionParameters::normalizeAdId($incoming['sid'] ?? null);

        if (! filled($payload['utm_term'] ?? null)) {
            $adset = $storedAdset ?? $incomingAdset;
            if ($adset !== null) {
                $payload['utm_term'] = $adset;
            }
        }

        $code = AttributionParameters::partnerCodeFromPayload($stored)
            ?? AttributionParameters::partnerCodeFromPayload($incoming);

        if ($code !== null) {
            $payload['sid'] = $code;
        } elseif (! filled($payload['sid'] ?? null) && filled($incoming['sid'] ?? null)) {
            $payload['sid'] = $incoming['sid'];
        }

        unset($payload['pid']);

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function explicitUtmFromRequest(Request $request): array
    {
        $keys = AttributionParameters::trackingKeys();
        $fromRequest = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (! is_string($value) || trim($value) === '') {
                $value = $request->input($key);
            }

            if (is_string($value) && trim($value) !== '') {
                $fromRequest[$key] = Str::limit(trim((string) AttributionParameters::decodeQueryValue($value)), 120, '');
            }
        }

        return $fromRequest;
    }

    /**
     * Meta/Google ads often land with click IDs (fbclid/gclid) and no UTM params.
     * Infer a stable traffic source so those checkouts are not bucketed as Direct.
     *
     * @return array<string, string>
     */
    private function inferUtmFromClickIdsAndReferrer(Request $request): array
    {
        $referrer = Str::lower((string) $request->headers->get('referer'));
        $has = fn (string $key): bool => filled($request->query($key)) || filled($request->input($key));

        if ($has('fbclid')
            || str_contains($referrer, 'facebook.com')
            || str_contains($referrer, 'fb.com')
            || str_contains($referrer, 'fb.')
        ) {
            return [
                'utm_source' => 'meta',
                'utm_medium' => $has('fbclid') ? 'paid_social' : 'referral',
            ];
        }

        if ($has('igshid')
            || str_contains($referrer, 'instagram.com')
            || str_contains($referrer, 'l.instagram.com')
        ) {
            return [
                'utm_source' => 'meta',
                'utm_medium' => 'paid_social',
                'platform' => 'ig',
            ];
        }

        if ($has('gclid') || $has('wbraid') || $has('gbraid')) {
            return [
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
            ];
        }

        if (str_contains($referrer, 'google.')
            || str_contains($referrer, 'googleusercontent.com')
            || str_contains($referrer, 'googlesyndication.com')
        ) {
            return [
                'utm_source' => 'google',
                'utm_medium' => 'organic',
            ];
        }

        if (str_contains($referrer, 'youtube.com') || str_contains($referrer, 'youtu.be')) {
            return [
                'utm_source' => 'youtube',
                'utm_medium' => 'referral',
            ];
        }

        if (str_contains($referrer, 'whatsapp')
            || str_contains($referrer, 'wa.me')
            || str_contains($referrer, 'api.whatsapp.com')
        ) {
            return [
                'utm_source' => 'whatsapp',
                'utm_medium' => 'social',
            ];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function readUtmCookie(Request $request): array
    {
        $stored = $request->cookie(self::UTM_COOKIE);

        if (! is_string($stored) || trim($stored) === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        if (! is_array($decoded)) {
            return [];
        }

        $clean = [];

        foreach ($this->cookieKeys() as $key) {
            $value = $decoded[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $limit = $key === 'landing_path' ? 255 : 120;
                $clean[$key] = Str::limit(trim($value), $limit, '');
            }
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    private function cookieKeys(): array
    {
        return [...AttributionParameters::trackingKeys(), 'landing_path'];
    }

    /**
     * Persist first-touch UTM values (e.g. staff referral codes) for later pages/checkouts.
     *
     * @param  array<string, string|null>  $utm
     */
    public function rememberUtm(array $utm): void
    {
        $payload = [];

        foreach ($this->cookieKeys() as $key) {
            $value = $utm[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $limit = $key === 'landing_path' ? 255 : 120;
                $payload[$key] = Str::limit(trim($value), $limit, '');
            }
        }

        if ($payload === []) {
            return;
        }

        $stored = $this->readUtmCookie(request());
        $merged = $this->firstTouchMerge($stored, $payload);

        if ($merged !== $stored) {
            $this->queueUtmCookie($merged);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function queueUtmCookie(array $payload): void
    {
        Cookie::queue(
            self::UTM_COOKIE,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            60 * 24 * 30,
            '/',
            null,
            null,
            true,
            false,
            'lax'
        );
    }

    private function deviceType(Request $request): string
    {
        return DeviceType::fromUserAgent($request->userAgent());
    }

    /**
     * Keep a compact landing path that still retains click-id / UTM markers for attribution.
     */
    private function attributionLandingPath(Request $request): string
    {
        $utm = $this->utmFromRequest($request);
        $fromCookie = $utm['landing_path'] ?? null;

        if (is_string($fromCookie) && trim($fromCookie) !== '') {
            return Str::limit(trim($fromCookie), 255, '');
        }

        $explicitPath = $request->input('landing_path');
        if (is_string($explicitPath) && trim($explicitPath) !== '') {
            return Str::limit(trim($explicitPath), 255, '');
        }

        $landingUrl = $request->input('landing_url');
        if (is_string($landingUrl) && trim($landingUrl) !== '') {
            $parsed = parse_url($landingUrl, PHP_URL_PATH);
            if (is_string($parsed) && $parsed !== '') {
                return Str::limit($parsed, 255, '');
            }
        }

        if ($this->isCheckoutPath($request)) {
            return '/';
        }

        return $this->pathWithTrackingMarkers($request);
    }

    /**
     * First-touch landing path stored in the attribution cookie.
     *
     * Checkout endpoints are never used: the donor has already moved off the
     * original campaign URL by the time Razorpay checkout is posted.
     */
    private function firstTouchLandingPath(Request $request): ?string
    {
        if ($this->isCheckoutPath($request)) {
            $explicitPath = $request->input('landing_path');

            if (is_string($explicitPath) && trim($explicitPath) !== '') {
                return Str::limit(trim($explicitPath), 255, '');
            }

            $landingUrl = $request->input('landing_url');
            if (is_string($landingUrl) && trim($landingUrl) !== '') {
                $parsed = parse_url($landingUrl, PHP_URL_PATH);
                if (is_string($parsed) && $parsed !== '') {
                    return Str::limit($parsed, 255, '');
                }
            }

            return null;
        }

        return $this->pathWithTrackingMarkers($request);
    }

    private function isCheckoutPath(Request $request): bool
    {
        return $request->is(
            'donate/razorpay',
            'donate/razorpay/subscription',
            'api/wp-razorpay',
            'api/next-razorpay',
            'api/donate/checkout',
            'api/donate/subscription',
        );
    }

    private function pathWithTrackingMarkers(Request $request): string
    {
        $path = $request->path();
        $markers = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'platform', 'placement', 'pid', 'utm_id', 'sid', 'aid', 'fbclid', 'gclid', 'wbraid', 'gbraid', 'igshid'] as $key) {
            $value = $request->query($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $markers[$key] = in_array($key, ['fbclid', 'gclid', 'wbraid', 'gbraid', 'igshid'], true)
                ? '1'
                : Str::limit(trim($value), 40, '');
        }

        if ($markers === []) {
            return Str::limit($path, 255, '');
        }

        return Str::limit($path.'?'.http_build_query($markers), 255, '');
    }

    public function stampDonationIpLocation(DonationOrder $order, Request $request): void
    {
        $payload = array_filter(
            $this->geoLocator->donationLocationFromRequest($request),
            static fn ($value) => $value !== null && $value !== '',
        );

        if ($payload === []) {
            return;
        }

        $order->forceFill($payload)->save();
    }

    /**
     * @return array<string, string|null>
     */
    private function locationFromOrder(DonationOrder $order): array
    {
        if (filled($order->ip_address) || filled($order->ip_country_code) || filled($order->ip_city)) {
            return [
                'ip_address' => $order->ip_address,
                'country_code' => $order->ip_country_code,
                'country_name' => $order->ip_country_name,
                'region_name' => $order->ip_region_name,
                'city' => $order->ip_city,
            ];
        }

        $checkoutEvent = AnalyticsEvent::query()
            ->where('donation_order_id', $order->id)
            ->where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED)
            ->first();

        if ($checkoutEvent === null) {
            return $this->geoLocator->emptyLocation();
        }

        return [
            'ip_address' => $checkoutEvent->ip_address,
            'country_code' => $checkoutEvent->country_code,
            'country_name' => $checkoutEvent->country_name,
            'region_name' => $checkoutEvent->region_name,
            'city' => $checkoutEvent->city,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizedEventAttributes(Request $request): array
    {
        $attribution = $this->attributionFromRequest($request);

        return AttributionNormalizer::normalizedPayload([
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'platform' => $attribution['platform'] ?? null,
            'placement' => $attribution['placement'] ?? null,
            'referrer' => $request->headers->get('referer'),
            'landing_path' => $request->path(),
        ]);
    }

    private function eventExistsForOrder(string $eventType, int $orderId): bool
    {
        return AnalyticsEvent::query()
            ->where('event_type', $eventType)
            ->where('donation_order_id', $orderId)
            ->exists();
    }
}
