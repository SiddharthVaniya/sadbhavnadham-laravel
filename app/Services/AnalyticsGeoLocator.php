<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AnalyticsGeoLocator
{
    public function fromRequest(Request $request): array
    {
        if (! config('analytics.geolocation.enabled', true)) {
            return $this->emptyLocation();
        }

        $ip = $this->clientIp($request);

        if ($ip === null) {
            return $this->cloudflareFallback($request);
        }

        return Cache::remember(
            'analytics_geo:'.md5($ip),
            config('analytics.geolocation.cache_ttl', 86400),
            fn () => $this->lookup($ip) ?? $this->cloudflareFallback($request)
        );
    }

    public function emptyLocation(): array
    {
        return [
            'ip_address' => null,
            'country_code' => null,
            'country_name' => null,
            'region_name' => null,
            'city' => null,
            'lat' => null,
            'lng' => null,
        ];
    }

    public function clientIp(Request $request): ?string
    {
        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return $ip;
    }

    /**
     * Any client IP suitable for donation_orders storage (includes private/local).
     */
    public function requestIp(Request $request): ?string
    {
        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $ip;
    }

    /**
     * IP + location fields for donation_orders (donate / checkout only).
     *
     * @return array{
     *     ip_address: ?string,
     *     ip_country_code: ?string,
     *     ip_country_name: ?string,
     *     ip_region_name: ?string,
     *     ip_city: ?string,
     *     ip_lat: ?float,
     *     ip_lng: ?float
     * }
     */
    public function donationLocationFromRequest(Request $request): array
    {
        $geo = $this->fromRequest($request);
        $ip = $geo['ip_address'] ?? $this->requestIp($request);

        return [
            'ip_address' => $ip,
            'ip_country_code' => $geo['country_code'] ?? null,
            'ip_country_name' => $geo['country_name'] ?? null,
            'ip_region_name' => $geo['region_name'] ?? null,
            'ip_city' => $geo['city'] ?? null,
            'ip_lat' => isset($geo['lat']) && is_numeric($geo['lat']) ? (float) $geo['lat'] : null,
            'ip_lng' => isset($geo['lng']) && is_numeric($geo['lng']) ? (float) $geo['lng'] : null,
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    public function lookup(string $ip): ?array
    {
        try {
            $response = Http::timeout(config('analytics.geolocation.lookup_timeout', 2))
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,countryCode,regionName,city,lat,lon,query',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
                return null;
            }

            return $this->normalize([
                'ip_address' => $payload['query'] ?? $ip,
                'country_code' => $payload['countryCode'] ?? null,
                'country_name' => $payload['country'] ?? null,
                'region_name' => $payload['regionName'] ?? null,
                'city' => $payload['city'] ?? null,
                'lat' => isset($payload['lat']) ? (float) $payload['lat'] : null,
                'lng' => isset($payload['lon']) ? (float) $payload['lon'] : null,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string|null>  $attributes
     * @return array<string, string|null>
     */
    private function cloudflareFallback(Request $request): array
    {
        $countryCode = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        if ($countryCode === '' || $countryCode === 'XX') {
            return $this->emptyLocation();
        }

        return $this->normalize([
            'ip_address' => $this->clientIp($request),
            'country_code' => $countryCode,
            'country_name' => $this->countryNameFromCode($countryCode),
            'region_name' => null,
            'city' => null,
            'lat' => null,
            'lng' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $countryCode = $this->clean($attributes['country_code'] ?? null, 2);
        $lat = isset($attributes['lat']) && is_numeric($attributes['lat']) ? (float) $attributes['lat'] : null;
        $lng = isset($attributes['lng']) && is_numeric($attributes['lng']) ? (float) $attributes['lng'] : null;

        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $lat = null;
        }

        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $lng = null;
        }

        return [
            'ip_address' => $this->clean($attributes['ip_address'] ?? null, 45),
            'country_code' => $countryCode ? Str::upper($countryCode) : null,
            'country_name' => $this->clean($attributes['country_name'] ?? null, 100),
            'region_name' => $this->clean($attributes['region_name'] ?? null, 100),
            'city' => $this->clean($attributes['city'] ?? null, 100),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function clean(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $maxLength, '');
    }

    private function countryNameFromCode(string $countryCode): ?string
    {
        $countries = [
            'IN' => 'India',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AE' => 'United Arab Emirates',
            'CA' => 'Canada',
            'AU' => 'Australia',
        ];

        return $countries[$countryCode] ?? $countryCode;
    }
}
