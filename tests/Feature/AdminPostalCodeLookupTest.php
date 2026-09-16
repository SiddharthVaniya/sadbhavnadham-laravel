<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createPostalLookupAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage donations']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage donations');

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('looks up a uk postcode for admin offline forms', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response([
            'status' => 200,
            'result' => [
                'postcode' => 'N1 9GU',
                'admin_district' => 'Islington',
                'region' => 'London',
                'country' => 'England',
            ],
        ]),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'GB',
            'postal' => 'N1 9GU',
        ])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Islington',
            'state' => 'London',
            'country' => 'UNITED KINGDOM',
            'country_code' => 'GB',
            'postal' => 'N1 9GU',
        ]);
});

it('looks up a us zip code via zippopotam', function () {
    Http::fake([
        'api.zippopotam.us/*' => Http::response([
            'post code' => '90210',
            'country' => 'United States',
            'country abbreviation' => 'US',
            'places' => [[
                'place name' => 'Beverly Hills',
                'state' => 'California',
                'state abbreviation' => 'CA',
            ]],
        ]),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'US',
            'postal' => '90210',
        ])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Beverly Hills',
            'state' => 'California',
            'country' => 'UNITED STATES',
            'country_code' => 'US',
        ]);
});

it('looks up an indian pincode', function () {
    Http::fake([
        'api.postalpincode.in/*' => Http::response([[
            'Status' => 'Success',
            'PostOffice' => [[
                'Name' => 'Rajkot',
                'District' => 'Rajkot',
                'State' => 'Gujarat',
            ]],
        ]]),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'IN',
            'postal' => '360001',
        ])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'country_code' => 'IN',
        ]);
});

it('returns not found when postal lookup fails', function () {
    Http::fake([
        'api.postcodes.io/*' => Http::response(['status' => 404], 404),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'GB',
            'postal' => 'ZZ1 1ZZ',
        ])
        ->assertNotFound()
        ->assertJson(['found' => false]);
});

it('sends a browser-like user agent for indian pincode lookups', function () {
    Http::fake([
        'api.postalpincode.in/*' => Http::response([[
            'Status' => 'Success',
            'PostOffice' => [[
                'Name' => 'Gadhaka',
                'District' => 'Rajkot',
                'State' => 'Gujarat',
            ]],
        ]]),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'IN',
            'postal' => '360020',
        ])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Rajkot',
            'state' => 'Gujarat',
        ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.postalpincode.in/pincode/360020')
            && str_contains((string) $request->header('User-Agent')[0], 'SadbhavnaDonation');
    });
});

it('falls back to zippopotam when the india pincode api connection resets', function () {
    Http::fake([
        'api.postalpincode.in/*' => function () {
            throw new Illuminate\Http\Client\ConnectionException('Connection was reset');
        },
        'api.zippopotam.us/*' => Http::response([
            'post code' => '396424',
            'country' => 'India',
            'country abbreviation' => 'IN',
            'places' => [[
                'place name' => 'Kabilpore',
                'state' => 'Gujarat',
            ]],
        ]),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'IN',
            'postal' => '396424',
        ])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Kabilpore',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'country_code' => 'IN',
        ]);
});

it('returns not found instead of failing when the india pincode api connection resets', function () {
    Http::fake([
        'api.postalpincode.in/*' => function () {
            throw new Illuminate\Http\Client\ConnectionException('Connection was reset');
        },
        'api.zippopotam.us/*' => Http::response([], 404),
        'nominatim.openstreetmap.org/*' => Http::response([], 404),
    ]);

    $user = createPostalLookupAdmin();

    actingAs($user)
        ->postJson(route('admin.donations.postal-lookup'), [
            'country_code' => 'IN',
            'postal' => '360020',
        ])
        ->assertNotFound()
        ->assertJson(['found' => false]);
});
