<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PostalCodeLookupService
{
    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    public function lookup(string $countryCode, string $postal): ?array
    {
        $countryCode = strtoupper(trim($countryCode));
        $postal = trim($postal);

        if ($countryCode === '' || $postal === '') {
            return null;
        }

        try {
            return match ($countryCode) {
                'IN' => $this->lookupIndia($postal),
                'GB' => $this->lookupUnitedKingdom($postal),
                default => $this->lookupZippopotam($countryCode, $postal)
                    ?? $this->lookupNominatim($countryCode, $postal),
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    private function lookupIndia(string $postal): ?array
    {
        $digits = preg_replace('/\D+/', '', $postal) ?? '';

        if (strlen($digits) !== 6) {
            return null;
        }

        $postOffice = $this->fetchIndiaPostOffice($digits);

        if (is_array($postOffice)) {
            return $this->result(
                city: (string) ($postOffice['District'] ?? $postOffice['Block'] ?? $postOffice['Name'] ?? ''),
                state: (string) ($postOffice['State'] ?? ''),
                country: 'INDIA',
                countryCode: 'IN',
                postal: $digits,
            );
        }

        return $this->lookupZippopotam('IN', $digits)
            ?? $this->lookupNominatim('IN', $digits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchIndiaPostOffice(string $digits): ?array
    {
        $attempts = 0;

        while ($attempts < 2) {
            $attempts++;

            try {
                $response = $this->http()
                    ->get('https://api.postalpincode.in/pincode/'.$digits);
            } catch (ConnectionException) {
                if ($attempts >= 2) {
                    return null;
                }

                usleep(250_000);

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            if (strtoupper((string) $response->json('0.Status')) === 'ERROR') {
                return null;
            }

            $postOffice = $response->json('0.PostOffice.0');

            return is_array($postOffice) ? $postOffice : null;
        }

        return null;
    }

    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    private function lookupUnitedKingdom(string $postal): ?array
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $postal) ?? '');

        if (strlen($normalized) < 5) {
            return null;
        }

        $response = $this->http()
            ->get('https://api.postcodes.io/postcodes/'.$normalized);

        if (! $response->successful() || (int) $response->json('status') !== 200) {
            return null;
        }

        $result = $response->json('result');

        if (! is_array($result)) {
            return null;
        }

        $city = (string) ($result['admin_district'] ?? $result['parish'] ?? $result['region'] ?? '');
        $state = (string) ($result['region'] ?? $result['country'] ?? '');
        $postalFormatted = (string) ($result['postcode'] ?? $postal);

        return $this->result(
            city: $city,
            state: $state,
            country: 'UNITED KINGDOM',
            countryCode: 'GB',
            postal: $postalFormatted,
        );
    }

    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    private function lookupZippopotam(string $countryCode, string $postal): ?array
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $postal) ?? '');

        if ($normalized === '') {
            return null;
        }

        $response = $this->http()
            ->get('https://api.zippopotam.us/'.strtolower($countryCode).'/'.$normalized);

        if (! $response->successful()) {
            return null;
        }

        $place = $response->json('places.0');

        if (! is_array($place)) {
            return null;
        }

        $countryName = strtoupper((string) ($response->json('country') ?: $countryCode));
        $postalFormatted = (string) ($response->json('post code') ?: $postal);

        return $this->result(
            city: (string) ($place['place name'] ?? ''),
            state: (string) ($place['state'] ?? $place['state abbreviation'] ?? ''),
            country: $countryName,
            countryCode: strtoupper((string) ($response->json('country abbreviation') ?: $countryCode)),
            postal: $postalFormatted,
        );
    }

    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    private function lookupNominatim(string $countryCode, string $postal): ?array
    {
        $response = $this->http()
            ->get('https://nominatim.openstreetmap.org/search', [
                'postalcode' => $postal,
                'countrycodes' => strtolower($countryCode),
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $row = $response->json('0');

        if (! is_array($row) || ! is_array($row['address'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = $row['address'];

        $city = (string) (
            $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['suburb']
            ?? ''
        );
        $state = (string) ($address['state'] ?? $address['region'] ?? $address['county'] ?? '');
        $country = strtoupper((string) ($address['country'] ?? $countryCode));

        return $this->result(
            city: $city,
            state: $state,
            country: $country,
            countryCode: $countryCode,
            postal: (string) ($address['postcode'] ?? $postal),
        );
    }

    private function http(): PendingRequest
    {
        // api.postalpincode.in resets connections that use Guzzle's default User-Agent.
        return Http::timeout(8)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; SadbhavnaDonation/1.0; +https://sadbhavna.org)',
                'Accept-Language' => 'en-IN,en;q=0.9',
            ]);
    }

    /**
     * @return array{city: string, state: string, country: string, country_code: string, postal: string}|null
     */
    private function result(string $city, string $state, string $country, string $countryCode, string $postal): ?array
    {
        $city = trim($city);
        $state = trim($state);
        $country = trim($country);
        $postal = trim($postal);

        if ($city === '' && $state === '') {
            return null;
        }

        return [
            'city' => $city,
            'state' => $state,
            'country' => $country !== '' ? Str::upper($country) : $countryCode,
            'country_code' => strtoupper($countryCode),
            'postal' => $postal,
        ];
    }
}
