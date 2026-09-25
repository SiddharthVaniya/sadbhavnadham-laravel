<?php

namespace App\Services;

use App\Services\GeoIp\GeoIpLookupService;
use Illuminate\Http\Request;

class AnalyticsGeoLocator
{
    public function __construct(
        private GeoIpLookupService $geoIp,
    ) {}

    public function fromRequest(Request $request): array
    {
        if (! config('analytics.geolocation.enabled', true) || ! config('geoip.enabled', true)) {
            return $this->emptyLocation();
        }

        return $this->geoIp->lookupFromRequest($request)->toAnalyticsArray();
    }

    public function emptyLocation(): array
    {
        return [
            'ip_address' => null,
            'country_code' => null,
            'country_name' => null,
            'region_name' => null,
            'city' => null,
            'postal_code' => null,
            'lat' => null,
            'lng' => null,
            'timezone' => null,
            'asn' => null,
            'isp' => null,
            'is_proxy_hint' => null,
        ];
    }

    public function clientIp(Request $request): ?string
    {
        return $this->geoIp->clientIp($request, publicOnly: true);
    }

    /**
     * Any client IP suitable for donation_orders storage (includes private/local).
     */
    public function requestIp(Request $request): ?string
    {
        return $this->geoIp->clientIp($request, publicOnly: false);
    }

    /**
     * IP + location fields for donation_orders / visits.
     *
     * @return array{
     *     ip_address: ?string,
     *     ip_country_code: ?string,
     *     ip_country_name: ?string,
     *     ip_region_name: ?string,
     *     ip_city: ?string,
     *     ip_postal_code: ?string,
     *     ip_lat: ?float,
     *     ip_lng: ?float,
     *     ip_timezone: ?string,
     *     ip_asn: ?int,
     *     ip_isp: ?string
     * }
     */
    public function donationLocationFromRequest(Request $request): array
    {
        $result = $this->geoIp->lookupFromRequest($request);
        $columns = $result->toVisitColumns();
        $columns['ip_address'] = $columns['ip_address'] ?? $this->requestIp($request);

        return $columns;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookup(string $ip): ?array
    {
        $result = $this->geoIp->lookup($ip);

        if ($result->countryCode === null && $result->city === null && $result->asn === null) {
            return null;
        }

        return $result->toAnalyticsArray();
    }
}
