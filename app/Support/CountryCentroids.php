<?php

namespace App\Support;

class CountryCentroids
{
    /**
     * Approximate country centroids for map bubbles (ISO 3166-1 alpha-2).
     *
     * @return array{lat: float, lng: float, name: string}|null
     */
    public static function for(string $countryCode): ?array
    {
        $code = strtoupper(trim($countryCode));

        return self::all()[$code] ?? null;
    }

    /**
     * @return array<string, array{lat: float, lng: float, name: string}>
     */
    public static function all(): array
    {
        return [
            'AE' => ['lat' => 23.4241, 'lng' => 53.8478, 'name' => 'United Arab Emirates'],
            'AU' => ['lat' => -25.2744, 'lng' => 133.7751, 'name' => 'Australia'],
            'BD' => ['lat' => 23.6850, 'lng' => 90.3563, 'name' => 'Bangladesh'],
            'BH' => ['lat' => 25.9304, 'lng' => 50.6378, 'name' => 'Bahrain'],
            'CA' => ['lat' => 56.1304, 'lng' => -106.3468, 'name' => 'Canada'],
            'CN' => ['lat' => 35.8617, 'lng' => 104.1954, 'name' => 'China'],
            'DE' => ['lat' => 51.1657, 'lng' => 10.4515, 'name' => 'Germany'],
            'FR' => ['lat' => 46.2276, 'lng' => 2.2137, 'name' => 'France'],
            'GB' => ['lat' => 55.3781, 'lng' => -3.4360, 'name' => 'United Kingdom'],
            'HK' => ['lat' => 22.3193, 'lng' => 114.1694, 'name' => 'Hong Kong'],
            'IE' => ['lat' => 53.1424, 'lng' => -7.6921, 'name' => 'Ireland'],
            'IN' => ['lat' => 20.5937, 'lng' => 78.9629, 'name' => 'India'],
            'JP' => ['lat' => 36.2048, 'lng' => 138.2529, 'name' => 'Japan'],
            'KE' => ['lat' => -0.0236, 'lng' => 37.9062, 'name' => 'Kenya'],
            'KW' => ['lat' => 29.3117, 'lng' => 47.4818, 'name' => 'Kuwait'],
            'LK' => ['lat' => 7.8731, 'lng' => 80.7718, 'name' => 'Sri Lanka'],
            'MY' => ['lat' => 4.2105, 'lng' => 101.9758, 'name' => 'Malaysia'],
            'NG' => ['lat' => 9.0820, 'lng' => 8.6753, 'name' => 'Nigeria'],
            'NL' => ['lat' => 52.1326, 'lng' => 5.2913, 'name' => 'Netherlands'],
            'NP' => ['lat' => 28.3949, 'lng' => 84.1240, 'name' => 'Nepal'],
            'NZ' => ['lat' => -40.9006, 'lng' => 174.8860, 'name' => 'New Zealand'],
            'OM' => ['lat' => 21.4735, 'lng' => 55.9754, 'name' => 'Oman'],
            'PK' => ['lat' => 30.3753, 'lng' => 69.3451, 'name' => 'Pakistan'],
            'QA' => ['lat' => 25.3548, 'lng' => 51.1839, 'name' => 'Qatar'],
            'SA' => ['lat' => 23.8859, 'lng' => 45.0792, 'name' => 'Saudi Arabia'],
            'SG' => ['lat' => 1.3521, 'lng' => 103.8198, 'name' => 'Singapore'],
            'TH' => ['lat' => 15.8700, 'lng' => 100.9925, 'name' => 'Thailand'],
            'US' => ['lat' => 37.0902, 'lng' => -95.7129, 'name' => 'United States'],
            'ZA' => ['lat' => -30.5595, 'lng' => 22.9375, 'name' => 'South Africa'],
        ];
    }

    public static function resolveCode(?string $countryCode, ?string $countryName): ?string
    {
        $code = strtoupper(trim((string) $countryCode));

        if ($code !== '' && isset(self::all()[$code])) {
            return $code;
        }

        $name = strtoupper(trim((string) $countryName));

        if ($name === '') {
            return null;
        }

        foreach (self::all() as $iso => $meta) {
            if (strtoupper($meta['name']) === $name || str_contains($name, strtoupper($meta['name']))) {
                return $iso;
            }
        }

        $aliases = [
            'INDIA' => 'IN',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'UK' => 'GB',
            'UAE' => 'AE',
            'UNITED ARAB EMIRATES' => 'AE',
        ];

        return $aliases[$name] ?? null;
    }
}
