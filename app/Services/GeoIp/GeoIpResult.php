<?php

namespace App\Services\GeoIp;

final class GeoIpResult
{
    public function __construct(
        public readonly ?string $ipAddress = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $countryName = null,
        public readonly ?string $regionName = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $timezone = null,
        public readonly ?int $asn = null,
        public readonly ?string $isp = null,
        public readonly ?bool $isProxyHint = null,
    ) {}

    /**
     * @return array{
     *     ip_address: ?string,
     *     country_code: ?string,
     *     country_name: ?string,
     *     region_name: ?string,
     *     city: ?string,
     *     postal_code: ?string,
     *     lat: ?float,
     *     lng: ?float,
     *     timezone: ?string,
     *     asn: ?int,
     *     isp: ?string,
     *     is_proxy_hint: ?bool
     * }
     */
    public function toAnalyticsArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'region_name' => $this->regionName,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'timezone' => $this->timezone,
            'asn' => $this->asn,
            'isp' => $this->isp,
            'is_proxy_hint' => $this->isProxyHint,
        ];
    }

    /**
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
    public function toVisitColumns(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'ip_country_code' => $this->countryCode,
            'ip_country_name' => $this->countryName,
            'ip_region_name' => $this->regionName,
            'ip_city' => $this->city,
            'ip_postal_code' => $this->postalCode,
            'ip_lat' => $this->latitude,
            'ip_lng' => $this->longitude,
            'ip_timezone' => $this->timezone,
            'ip_asn' => $this->asn,
            'ip_isp' => $this->isp,
        ];
    }

    /**
     * @return array{
     *     country: ?string,
     *     region: ?string,
     *     city: ?string,
     *     location: ?string,
     *     postal_code: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     timezone: ?string,
     *     asn: ?int,
     *     isp: ?string
     * }
     */
    public function toLoginLogArray(): array
    {
        $parts = array_values(array_filter([
            $this->city,
            $this->regionName,
            $this->countryName,
        ]));

        return [
            'country' => $this->countryName,
            'region' => $this->regionName,
            'city' => $this->city,
            'location' => $parts === [] ? null : implode(', ', $parts),
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'asn' => $this->asn,
            'isp' => $this->isp,
        ];
    }

    public static function empty(?string $ip = null): self
    {
        return new self(ipAddress: $ip);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCacheArray(array $data): self
    {
        return new self(
            ipAddress: isset($data['ip_address']) ? (string) $data['ip_address'] : null,
            countryCode: isset($data['country_code']) ? (string) $data['country_code'] : null,
            countryName: isset($data['country_name']) ? (string) $data['country_name'] : null,
            regionName: isset($data['region_name']) ? (string) $data['region_name'] : null,
            city: isset($data['city']) ? (string) $data['city'] : null,
            postalCode: isset($data['postal_code']) ? (string) $data['postal_code'] : null,
            latitude: isset($data['lat']) && is_numeric($data['lat']) ? (float) $data['lat'] : null,
            longitude: isset($data['lng']) && is_numeric($data['lng']) ? (float) $data['lng'] : null,
            timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
            asn: isset($data['asn']) && is_numeric($data['asn']) ? (int) $data['asn'] : null,
            isp: isset($data['isp']) ? (string) $data['isp'] : null,
            isProxyHint: array_key_exists('is_proxy_hint', $data) && $data['is_proxy_hint'] !== null
                ? (bool) $data['is_proxy_hint']
                : null,
        );
    }
}
