<?php

namespace App\Services;

use App\Services\GeoIp\GeoIpLookupService;

class IpGeolocationService
{
    public function __construct(
        private GeoIpLookupService $geoIp,
    ) {}

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
    public function lookup(?string $ip): array
    {
        return $this->geoIp->lookup($ip)->toLoginLogArray();
    }
}
