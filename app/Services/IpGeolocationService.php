<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    /**
     * @return array{country: ?string, region: ?string, city: ?string, location: ?string}
     */
    public function lookup(?string $ip): array
    {
        $empty = [
            'country' => null,
            'region' => null,
            'city' => null,
            'location' => null,
        ];

        $ip = trim((string) $ip);

        if ($ip === '' || $this->isNonPublicIp($ip)) {
            return $empty;
        }

        $cacheKey = 'ip-geo:'.md5($ip);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($ip, $empty) {
            try {
                $response = Http::timeout(2)
                    ->acceptJson()
                    ->get('http://ip-api.com/json/'.$ip, [
                        'fields' => 'status,country,regionName,city',
                    ]);

                if (! $response->ok()) {
                    return $empty;
                }

                $payload = $response->json();

                if (($payload['status'] ?? null) !== 'success') {
                    return $empty;
                }

                $country = $this->nullableString($payload['country'] ?? null);
                $region = $this->nullableString($payload['regionName'] ?? null);
                $city = $this->nullableString($payload['city'] ?? null);
                $parts = array_values(array_filter([$city, $region, $country]));

                return [
                    'country' => $country,
                    'region' => $region,
                    'city' => $city,
                    'location' => $parts === [] ? null : implode(', ', $parts),
                ];
            } catch (\Throwable $e) {
                Log::warning('IP geolocation lookup failed', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);

                return $empty;
            }
        });
    }

    private function isNonPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
