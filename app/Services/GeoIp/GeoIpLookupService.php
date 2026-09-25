<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Throwable;

class GeoIpLookupService
{
    private ?Reader $cityReader = null;

    private ?Reader $asnReader = null;

    private bool $cityReaderFailed = false;

    private bool $asnReaderFailed = false;

    public function clientIp(Request $request, bool $publicOnly = true): ?string
    {
        $candidates = [
            trim((string) $request->header('CF-Connecting-IP', '')),
            (string) $request->ip(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '' || ! filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }

            if ($publicOnly && ! filter_var(
                $candidate,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    public function lookup(?string $ip, ?Request $request = null): GeoIpResult
    {
        $ip = trim((string) $ip);

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $request
                ? $this->cloudflareFallback($request, null)
                : GeoIpResult::empty();
        }

        $isPublic = (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if (! $isPublic) {
            return $request
                ? $this->cloudflareFallback($request, $ip)
                : GeoIpResult::empty($ip);
        }

        if (! config('geoip.enabled', true)) {
            return $request
                ? $this->cloudflareFallback($request, $ip)
                : GeoIpResult::empty($ip);
        }

        try {
            $cacheKey = $this->cacheKey($ip);
            $cached = $this->cache()->get($cacheKey);

            if (is_array($cached)) {
                $result = GeoIpResult::fromCacheArray($cached);
            } else {
                $result = $this->lookupMmDb($ip);

                // Do not cache empty lookups — missing MMDB or transient reader
                // failures must not poison Redis for the full TTL.
                if ($result->countryCode !== null || $result->city !== null || $result->asn !== null) {
                    $this->cache()->put(
                        $cacheKey,
                        $result->toAnalyticsArray(),
                        (int) config('geoip.cache_ttl', 86400)
                    );
                }
            }

            if ($result->countryCode === null && $request !== null) {
                return $this->mergeCloudflareCountry($result, $request);
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('GeoIP lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return $request
                ? $this->cloudflareFallback($request, $ip)
                : GeoIpResult::empty($ip);
        }
    }

    public function lookupFromRequest(Request $request): GeoIpResult
    {
        $ip = $this->clientIp($request, publicOnly: true)
            ?? $this->clientIp($request, publicOnly: false);

        return $this->lookup($ip, $request);
    }

    private function lookupMmDb(string $ip): GeoIpResult
    {
        $countryCode = null;
        $countryName = null;
        $regionName = null;
        $city = null;
        $postalCode = null;
        $latitude = null;
        $longitude = null;
        $timezone = null;
        $isProxyHint = null;
        $asn = null;
        $isp = null;

        try {
            $cityReader = $this->cityReader();
            if ($cityReader !== null) {
                $record = $cityReader->city($ip);
                $countryCode = $this->clean($record->country->isoCode, 2);
                $countryName = $this->clean($record->country->name, 100);
                $regionName = $this->clean($record->mostSpecificSubdivision->name, 100);
                $city = $this->clean($record->city->name, 100);
                $postalCode = $this->clean($record->postal->code, 32);
                $latitude = is_numeric($record->location->latitude) ? (float) $record->location->latitude : null;
                $longitude = is_numeric($record->location->longitude) ? (float) $record->location->longitude : null;
                $timezone = $this->clean($record->location->timeZone, 64);

                if (isset($record->traits->isAnonymous) || isset($record->traits->isAnonymousProxy)) {
                    $isProxyHint = (bool) (($record->traits->isAnonymous ?? false) || ($record->traits->isAnonymousProxy ?? false));
                }
            }
        } catch (AddressNotFoundException) {
            // IP not in city database
        } catch (Throwable $e) {
            Log::warning('GeoIP city MMDB read failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $asnReader = $this->asnReader();
            if ($asnReader !== null) {
                $asnRecord = $asnReader->asn($ip);
                $asn = $asnRecord->autonomousSystemNumber !== null
                    ? (int) $asnRecord->autonomousSystemNumber
                    : null;
                $isp = $this->clean($asnRecord->autonomousSystemOrganization, 255);
            }
        } catch (AddressNotFoundException) {
            // IP not in ASN database
        } catch (Throwable $e) {
            Log::warning('GeoIP ASN MMDB read failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            $latitude = null;
        }

        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            $longitude = null;
        }

        return new GeoIpResult(
            ipAddress: $ip,
            countryCode: $countryCode ? Str::upper($countryCode) : null,
            countryName: $countryName,
            regionName: $regionName,
            city: $city,
            postalCode: $postalCode,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            asn: $asn,
            isp: $isp,
            isProxyHint: $isProxyHint,
        );
    }

    private function cityReader(): ?Reader
    {
        if ($this->cityReaderFailed) {
            return null;
        }

        if ($this->cityReader !== null) {
            return $this->cityReader;
        }

        $path = (string) config('geoip.city_mmdb');

        if ($path === '' || ! is_file($path)) {
            $this->cityReaderFailed = true;

            return null;
        }

        try {
            $this->cityReader = new Reader($path);
        } catch (InvalidDatabaseException|Throwable $e) {
            $this->cityReaderFailed = true;
            Log::warning('GeoIP city MMDB open failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        return $this->cityReader;
    }

    private function asnReader(): ?Reader
    {
        if ($this->asnReaderFailed) {
            return null;
        }

        if ($this->asnReader !== null) {
            return $this->asnReader;
        }

        $path = (string) config('geoip.asn_mmdb');

        if ($path === '' || ! is_file($path)) {
            $this->asnReaderFailed = true;

            return null;
        }

        try {
            $this->asnReader = new Reader($path);
        } catch (InvalidDatabaseException|Throwable $e) {
            $this->asnReaderFailed = true;
            Log::warning('GeoIP ASN MMDB open failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        return $this->asnReader;
    }

    private function cache(): CacheRepository
    {
        $store = (string) config('geoip.cache_store', 'redis');

        try {
            return Cache::store($store);
        } catch (Throwable) {
            return Cache::store();
        }
    }

    private function cacheKey(string $ip): string
    {
        return (string) config('geoip.cache_prefix', 'geoip:v1:').md5($ip);
    }

    private function cloudflareFallback(Request $request, ?string $ip): GeoIpResult
    {
        $countryCode = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        if ($countryCode === '' || $countryCode === 'XX') {
            return GeoIpResult::empty($ip);
        }

        return new GeoIpResult(
            ipAddress: $ip,
            countryCode: $countryCode,
            countryName: $this->countryNameFromCode($countryCode),
        );
    }

    private function mergeCloudflareCountry(GeoIpResult $result, Request $request): GeoIpResult
    {
        $fallback = $this->cloudflareFallback($request, $result->ipAddress);

        if ($fallback->countryCode === null) {
            return $result;
        }

        return new GeoIpResult(
            ipAddress: $result->ipAddress,
            countryCode: $fallback->countryCode,
            countryName: $fallback->countryName,
            regionName: $result->regionName,
            city: $result->city,
            postalCode: $result->postalCode,
            latitude: $result->latitude,
            longitude: $result->longitude,
            timezone: $result->timezone,
            asn: $result->asn,
            isp: $result->isp,
            isProxyHint: $result->isProxyHint,
        );
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
