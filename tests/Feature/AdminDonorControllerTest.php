<?php

use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createDonationOrderForDonor(Donor $donor, array $attributes = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => 'razorpay',
        'provider_order_id' => fake()->unique()->bothify('order-####'),
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ], $attributes));
}

function createDonorAdminUser(): User
{
    Permission::firstOrCreate(['name' => 'view donors']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view donors');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('lists donors using normalized donor aggregates', function () {
    $user = createDonorAdminUser();

    $firstDonor = Donor::factory()->create([
        'name' => 'First Donor',
        'email' => 'first@example.com',
        'phone' => '1111111111',
    ]);

    $secondDonor = Donor::factory()->create([
        'name' => 'Second Donor',
        'email' => 'second@example.com',
        'phone' => '2222222222',
    ]);

    createDonationOrderForDonor($firstDonor, [
        'total_amount' => 100,
        'created_at' => now()->subDays(2),
    ]);

    createDonationOrderForDonor($firstDonor, [
        'total_amount' => 200,
        'created_at' => now()->subDay(),
    ]);

    createDonationOrderForDonor($secondDonor, [
        'total_amount' => 300,
        'created_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->has('donors.data', 2)
            ->where('donors.data', function ($donors) use ($firstDonor, $secondDonor) {
                $first = collect($donors)->firstWhere('id', $firstDonor->id);
                $second = collect($donors)->firstWhere('id', $secondDonor->id);

                return $first
                    && $second
                    && (int) $first['paid_donations'] === 2
                    && (float) $first['paid_amount'] === 300.0
                    && (int) $second['paid_donations'] === 1
                    && (float) $second['paid_amount'] === 300.0;
            }));
});

it('shows donor details and includes legacy orders without donor_id', function () {
    $user = createDonorAdminUser();

    $targetDonor = Donor::factory()->create([
        'name' => 'Legacy Donor',
        'email' => 'shared@example.com',
        'phone' => '9999999999',
    ]);

    $otherDonor = Donor::factory()->create([
        'name' => 'Other Donor',
        'email' => 'shared@example.com',
        'phone' => '8888888888',
    ]);

    createDonationOrderForDonor($targetDonor, [
        'total_amount' => 150,
    ]);

    createDonationOrderForDonor($targetDonor, [
        'donor_id' => null,
        'total_amount' => 250,
    ]);

    createDonationOrderForDonor($otherDonor, [
        'total_amount' => 500,
    ]);

    actingAs($user)
        ->get(route('admin.donors.show', $targetDonor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('donor.email', $targetDonor->email)
            ->where('donor.phone', $targetDonor->phone)
            ->where('donor.paid_donations', 2)
            ->where('donor.paid_amount', 400)
            ->where('donor.total_attempts', 2)
            ->where('donations.meta.total', 2));
});

it('excludes pending and failed orders from paid donor totals', function () {
    $user = createDonorAdminUser();

    $donor = Donor::factory()->create([
        'name' => 'Mixed Status Donor',
        'email' => 'mixed@example.com',
        'phone' => '6666666666',
    ]);

    createDonationOrderForDonor($donor, [
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    createDonationOrderForDonor($donor, [
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    createDonationOrderForDonor($donor, [
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('donor.paid_donations', 1)
            ->where('donor.paid_amount', 500)
            ->where('donor.total_attempts', 3)
            ->where('donor.pending_attempts', 1)
            ->where('donor.failed_attempts', 1)
            ->where('donations.meta.total', 3));
});

it('sorts donors across the full dataset', function () {
    $user = createDonorAdminUser();

    $alpha = Donor::factory()->create(['name' => 'Alpha Donor']);
    $zulu = Donor::factory()->create(['name' => 'Zulu Donor']);

    createDonationOrderForDonor($alpha);
    createDonationOrderForDonor($zulu);

    actingAs($user)
        ->get(route('admin.donors.index', ['sort' => 'name', 'dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->where('filters.sort', 'name')
            ->where('filters.dir', 'asc')
            ->where('donors.data.0.name', 'Alpha Donor')
            ->where('donors.data.1.name', 'Zulu Donor'));

    actingAs($user)
        ->get(route('admin.donors.index', ['sort' => 'name', 'dir' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donors.data.0.name', 'Zulu Donor')
            ->where('donors.data.1.name', 'Alpha Donor'));
});

it('filters repeat donors from the repeat donors card', function () {
    $user = createDonorAdminUser();

    $repeatDonor = Donor::factory()->create(['name' => 'Repeat Donor']);
    $oneTimeDonor = Donor::factory()->create(['name' => 'One Time Donor']);

    createDonationOrderForDonor($repeatDonor, ['total_amount' => 100]);
    createDonationOrderForDonor($repeatDonor, ['total_amount' => 200]);
    createDonationOrderForDonor($oneTimeDonor, ['total_amount' => 500]);

    actingAs($user)
        ->get(route('admin.donors.index', ['repeat' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->where('filters.repeat', true)
            ->has('donors.data', 1)
            ->where('donors.data.0.name', 'Repeat Donor'));
});

it('searches and filters donors by city and source', function () {
    $user = createDonorAdminUser();

    $rajkot = Donor::factory()->create([
        'name' => 'Rajkot Donor',
        'email' => 'rajkot@example.com',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
    ]);
    $ahmedabad = Donor::factory()->create([
        'name' => 'Ahmedabad Donor',
        'email' => 'ahmedabad@example.com',
        'city' => 'Ahmedabad',
        'state' => 'Gujarat',
    ]);

    createDonationOrderForDonor($rajkot, [
        'utm_source' => 'facebook',
        'utm_campaign' => 'feed_a',
        'paid_at' => now(),
    ]);
    createDonationOrderForDonor($ahmedabad, [
        'utm_source' => 'whatsapp',
        'paid_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donors.index', [
            'search' => 'Rajkot',
            'city' => 'Rajkot',
            'source' => 'facebook',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->has('donors.data', 1)
            ->where('donors.data.0.id', $rajkot->id)
            ->where('donors.data.0.source', 'Meta · Facebook · Organic social')
            ->where('filters.city', 'Rajkot')
            ->where('filters.source', 'facebook'));
});

it('keeps donor list filters when returning from the detail page', function () {
    $user = createDonorAdminUser();
    $donor = Donor::factory()->create(['name' => 'Return Donor']);
    createDonationOrderForDonor($donor);

    actingAs($user)
        ->get(route('admin.donors.show', [
            'donor' => $donor,
            'return' => '/admin/donors?search=Return&page=2',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('back_url', '/admin/donors?search=Return&page=2'));
});

it('exports donor donations as csv', function () {
    $user = createDonorAdminUser();

    $donor = Donor::factory()->create([
        'name' => 'Csv Donor',
        'email' => 'csv@example.com',
        'phone' => '7777777777',
    ]);

    $order = createDonationOrderForDonor($donor, [
        'order_uuid' => 'order_csv_123',
        'total_amount' => 900,
    ]);

    $response = actingAs($user)->get(route('admin.donors.export', $donor));

    $response->assertOk();
    expect((string) $response->headers->get('Content-Type'))->toContain('text/csv');
    $response->assertHeader('Content-Disposition', 'attachment; filename=donations_csv_example.com.csv');

    $content = $response->streamedContent();

    expect($content)->toContain('Order ID');
    expect($content)->toContain($order->order_uuid);
});

it('toggles whatsapp campaign opt-out on donor show', function () {
    $user = createDonorAdminUser();
    $donor = Donor::factory()->create([
        'name' => 'Opt Out Donor',
        'email' => 'optout-toggle@example.com',
        'phone' => '9887766554',
        'whatsapp_opt_out' => false,
    ]);
    createDonationOrderForDonor($donor);

    actingAs($user)
        ->post(route('admin.donors.whatsapp-opt-out', $donor), ['whatsapp_opt_out' => true])
        ->assertRedirect();

    expect($donor->fresh()->whatsapp_opt_out)->toBeTrue()
        ->and($donor->fresh()->whatsapp_opted_out_at)->not->toBeNull();

    actingAs($user)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('donor.whatsapp_opt_out', true)
            ->has('opt_out_url'));
});
