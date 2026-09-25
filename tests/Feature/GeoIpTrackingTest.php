<?php

use App\Models\LinkTrackingVisit;
use App\Models\User;
use App\Models\UserDeviceFingerprint;
use App\Models\UserLoginLog;
use App\Services\GeoIp\GeoIpLookupService;
use App\Services\GeoIp\GeoIpResult;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function fakeGeoResult(string $ip = '203.0.113.10'): GeoIpResult
{
    return new GeoIpResult(
        ipAddress: $ip,
        countryCode: 'IN',
        countryName: 'India',
        regionName: 'Gujarat',
        city: 'Ahmedabad',
        postalCode: '380001',
        latitude: 23.0225,
        longitude: 72.5714,
        timezone: 'Asia/Kolkata',
        asn: 55410,
        isp: 'Reliance Jio Infocomm Limited',
    );
}

function geoDigitalMarketer(): User
{
    Role::firstOrCreate(['name' => 'digital_marketer', 'guard_name' => 'web']);

    foreach ([
        AdminPermissions::CAUSE_VIEW,
        AdminPermissions::CAUSE_COPY_LINKS,
        AdminPermissions::CAMPAIGN_VIEW,
        AdminPermissions::CAMPAIGN_COPY_LINKS,
        AdminPermissions::PACKAGE_VIEW,
        AdminPermissions::PACKAGE_COPY_LINKS,
        AdminPermissions::REFERRAL_VIEW,
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $user = User::factory()->withReferralCode('zmupe')->create([
        'name' => 'Geo Marketer',
        'email' => 'geo-marketer@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('digital_marketer');

    return $user;
}

it('persists geo columns on donate track clicks', function () {
    $result = fakeGeoResult('203.0.113.50');

    $geo = mock(GeoIpLookupService::class);
    $geo->shouldReceive('clientIp')->andReturn('203.0.113.50');
    $geo->shouldReceive('lookupFromRequest')->andReturn($result);
    $geo->shouldReceive('lookup')->andReturn($result);
    $this->app->instance(GeoIpLookupService::class, $geo);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->postJson('/api/donate/track', [
            'visitor_id' => (string) Illuminate\Support\Str::uuid(),
            'sid' => 'zmupe',
            'utm_source' => 'meta',
            'page_path' => '/donate/old-age-home',
            'landing_url' => 'https://sadbhavnadham.org/donate/old-age-home?sid=zmupe',
        ])
        ->assertOk()
        ->assertJsonPath('recorded', true);

    $visit = LinkTrackingVisit::query()->first();

    expect($visit)->not->toBeNull()
        ->and($visit->ip_address)->toBe('203.0.113.50')
        ->and($visit->ip_country_code)->toBe('IN')
        ->and($visit->ip_city)->toBe('Ahmedabad')
        ->and($visit->ip_postal_code)->toBe('380001')
        ->and($visit->ip_asn)->toBe(55410)
        ->and($visit->ip_isp)->toBe('Reliance Jio Infocomm Limited')
        ->and($visit->ip_timezone)->toBe('Asia/Kolkata');
});

it('includes geo fields on marketer visits inertia payload', function () {
    $user = geoDigitalMarketer();

    LinkTrackingVisit::query()->create([
        'visitor_id' => 'v-geo',
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'page_path' => '/donate',
        'ip_address' => '203.0.113.10',
        'ip_country_code' => 'IN',
        'ip_country_name' => 'India',
        'ip_region_name' => 'Gujarat',
        'ip_city' => 'Rajkot',
        'ip_postal_code' => '360001',
        'ip_asn' => 9829,
        'ip_isp' => 'Bharti Airtel',
        'ip_timezone' => 'Asia/Kolkata',
        'device_type' => 'mobile',
        'is_unique' => true,
        'converted' => false,
    ]);

    $this->actingAs($user)
        ->get(route('marketer.visits', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketer/Visits')
            ->has('visits.data', 1)
            ->where('visits.data.0.ip_location', 'Rajkot, Gujarat, India')
            ->where('visits.data.0.ip_country_name', 'India')
            ->where('visits.data.0.ip_region_name', 'Gujarat')
            ->where('visits.data.0.ip_city', 'Rajkot')
            ->where('visits.data.0.ip_postal_code', '360001')
            ->where('visits.data.0.ip_isp', 'Bharti Airtel')
            ->where('visits.data.0.ip_asn', 9829)
            ->has('filterOptions.ip_country_code')
            ->has('filterOptions.ip_city')
            ->has('filterOptions.ip_isp'));
});

it('stores enriched geo on login audit via local lookup', function () {
    $result = fakeGeoResult('203.0.113.77');

    $geo = mock(GeoIpLookupService::class);
    $geo->shouldReceive('lookupFromRequest')->andReturn($result);
    $geo->shouldReceive('clientIp')->andReturn('203.0.113.77');
    $this->app->instance(GeoIpLookupService::class, $geo);

    Permission::firstOrCreate(['name' => 'view analytics']);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view analytics');

    $user = User::factory()->create([
        'email' => 'geo-login@example.com',
        'password' => 'secret-pass',
    ]);
    $user->assignRole('super_admin');

    UserDeviceFingerprint::query()->create([
        'user_id' => $user->id,
        'fingerprint' => 'trusted-device-geo',
    ]);

    $this->from(route('login'))
        ->post(route('admin.login.submit'), [
            'email' => 'geo-login@example.com',
            'password' => 'secret-pass',
            'fingerprint' => 'trusted-device-geo',
        ])
        ->assertRedirect();

    $log = UserLoginLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(UserLoginLog::STATUS_SUCCESS)
        ->and($log->country)->toBe('India')
        ->and($log->city)->toBe('Ahmedabad')
        ->and($log->postal_code)->toBe('380001')
        ->and($log->asn)->toBe(55410)
        ->and($log->isp)->toBe('Reliance Jio Infocomm Limited');
});
