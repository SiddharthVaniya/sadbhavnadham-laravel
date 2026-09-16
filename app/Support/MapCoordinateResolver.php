<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapCoordinateResolver
{
    /**
     * Resolve map coordinates from city / region / country, with country-centroid fallback.
     *
     * @return array{lat: float, lng: float, label: string, precision: string}|null
     */
    public function resolve(
        ?string $city = null,
        ?string $region = null,
        ?string $countryCode = null,
        ?string $countryName = null,
        ?float $lat = null,
        ?float $lng = null,
    ): ?array {
        if ($lat !== null && $lng !== null && $this->isValidCoordinate($lat, $lng)) {
            $label = collect([$city, $region, $countryName ?: $countryCode])
                ->filter(fn ($part) => filled($part))
                ->implode(', ');

            return [
                'lat' => round($lat, 5),
                'lng' => round($lng, 5),
                'label' => $label !== '' ? $label : 'Visitor',
                'precision' => 'exact',
            ];
        }

        $city = $this->clean($city);
        $region = $this->clean($region);
        $countryCode = $countryCode ? strtoupper(trim($countryCode)) : null;
        $countryName = $this->clean($countryName);

        if ($city !== null || $region !== null) {
            $resolved = $this->geocodePlace($city, $region, $countryCode, $countryName);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $code = CountryCentroids::resolveCode($countryCode, $countryName);
        $meta = $code ? CountryCentroids::for($code) : null;

        if ($meta === null) {
            return null;
        }

        // Slight deterministic offset so multiple visitors in the same country are not stacked
        // exactly on the country center when city geo is unavailable.
        $hash = crc32(($city ?? '').'|'.($region ?? '').'|'.$code);
        $latOffset = (($hash % 100) - 50) / 120; // ~±0.4°
        $lngOffset = (((int) ($hash / 100) % 100) - 50) / 120;

        return [
            'lat' => round($meta['lat'] + $latOffset, 5),
            'lng' => round($meta['lng'] + $lngOffset, 5),
            'label' => $meta['name'],
            'precision' => 'country',
        ];
    }

    /**
     * @return array{lat: float, lng: float, label: string, precision: string}|null
     */
    private function geocodePlace(?string $city, ?string $region, ?string $countryCode, ?string $countryName): ?array
    {
        $queryParts = array_values(array_filter([$city, $region, $countryName ?: $countryCode]));
        $query = implode(', ', $queryParts);

        if ($query === '') {
            return null;
        }

        $cacheKey = 'map_geocode:'.md5(Str::lower($query));

        /** @var array{lat: float, lng: float, label: string, precision: string}|null|false $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === false) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(4)
                ->withHeaders([
                    'User-Agent' => 'SadbhavnaDonation/1.0 (admin live map)',
                ])
                ->acceptJson()
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                Cache::put($cacheKey, false, now()->addHours(6));

                return null;
            }

            $row = $response->json('0');

            if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
                Cache::put($cacheKey, false, now()->addDay());

                return null;
            }

            $result = [
                'lat' => round((float) $row['lat'], 5),
                'lng' => round((float) $row['lon'], 5),
                'label' => $city && $countryName
                    ? $city.', '.$countryName
                    : (string) ($row['display_name'] ?? $query),
                'precision' => 'city',
            ];

            Cache::put($cacheKey, $result, now()->addDays(30));

            return $result;
        } catch (\Throwable) {
            Cache::put($cacheKey, false, now()->addHours(6));

            return null;
        }
    }

    private function isValidCoordinate(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 100, '');
    }
}
