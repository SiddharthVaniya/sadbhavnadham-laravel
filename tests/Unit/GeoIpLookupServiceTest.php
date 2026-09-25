<?php

use App\Services\GeoIp\GeoIpLookupService;
use App\Services\GeoIp\GeoIpResult;
use Illuminate\Http\Request;

it('prefers cloudflare connecting ip when public', function () {
    $service = app(GeoIpLookupService::class);
    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
    ]);

    expect($service->clientIp($request))->toBe('8.8.8.8');
});

it('skips private cloudflare ip when publicOnly', function () {
    $service = app(GeoIpLookupService::class);
    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '8.8.4.4',
        'HTTP_CF_CONNECTING_IP' => '192.168.1.10',
    ]);

    expect($service->clientIp($request))->toBe('8.8.4.4');
});

it('returns empty result for invalid ip without throwing', function () {
    $service = app(GeoIpLookupService::class);

    $result = $service->lookup('not-an-ip');

    expect($result)->toBeInstanceOf(GeoIpResult::class)
        ->and($result->countryCode)->toBeNull()
        ->and($result->city)->toBeNull();
});

it('falls back to cloudflare country for private ips', function () {
    $service = app(GeoIpLookupService::class);
    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_CF_IPCOUNTRY' => 'IN',
    ]);

    $result = $service->lookupFromRequest($request);

    expect($result->countryCode)->toBe('IN')
        ->and($result->countryName)->toBe('India');
});

it('maps result arrays for visits analytics and login logs', function () {
    $result = new GeoIpResult(
        ipAddress: '203.0.113.10',
        countryCode: 'IN',
        countryName: 'India',
        regionName: 'Gujarat',
        city: 'Ahmedabad',
        postalCode: '380001',
        latitude: 23.02,
        longitude: 72.57,
        timezone: 'Asia/Kolkata',
        asn: 13335,
        isp: 'Cloudflare, Inc.',
    );

    expect($result->toVisitColumns())->toMatchArray([
        'ip_address' => '203.0.113.10',
        'ip_country_code' => 'IN',
        'ip_city' => 'Ahmedabad',
        'ip_postal_code' => '380001',
        'ip_asn' => 13335,
        'ip_isp' => 'Cloudflare, Inc.',
    ])
        ->and($result->toAnalyticsArray())->toMatchArray([
            'country_code' => 'IN',
            'city' => 'Ahmedabad',
            'postal_code' => '380001',
            'asn' => 13335,
            'isp' => 'Cloudflare, Inc.',
        ])
        ->and($result->toLoginLogArray())->toMatchArray([
            'country' => 'India',
            'city' => 'Ahmedabad',
            'location' => 'Ahmedabad, Gujarat, India',
            'postal_code' => '380001',
            'asn' => 13335,
            'isp' => 'Cloudflare, Inc.',
        ]);
});

it('round-trips cache payload through GeoIpResult', function () {
    $payload = [
        'ip_address' => '1.1.1.1',
        'country_code' => 'AU',
        'country_name' => 'Australia',
        'region_name' => 'Queensland',
        'city' => 'Brisbane',
        'postal_code' => '4000',
        'lat' => -27.47,
        'lng' => 153.02,
        'timezone' => 'Australia/Brisbane',
        'asn' => 13335,
        'isp' => 'Cloudflare',
        'is_proxy_hint' => false,
    ];

    $result = GeoIpResult::fromCacheArray($payload);

    expect($result->toAnalyticsArray())->toMatchArray($payload);
});
