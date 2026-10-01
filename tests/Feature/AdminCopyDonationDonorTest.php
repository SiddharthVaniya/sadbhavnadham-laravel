<?php

use App\Jobs\UpdateDonationOnSheetJob;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function copyDonorAdmin(): User
{
    foreach (['manage donations', 'view all donations', 'edit donations'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage donations', 'view all donations', 'edit donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('lists recent failed donations first and can search any past donor', function () {
    $user = copyDonorAdmin();

    $qr = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-pay_copy_target',
        'provider_payment_id' => 'pay_copy_target',
        'donor_name' => 'Unknown Donor',
        'donor_email' => '',
        'donor_phone' => 'upi-abcdef1234',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $failed = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_failed_source',
        'provider_payment_id' => 'pay_failed_source',
        'donor_name' => 'Failed Checkout Donor',
        'donor_email' => 'failed.checkout@example.com',
        'donor_phone' => '9822211100',
        'address' => '12 Temple Road',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'pincode' => '360001',
        'country' => 'INDIA',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subHour(),
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_old_paid_source',
        'provider_payment_id' => 'pay_old_paid_source',
        'donor_name' => 'Returning Temple Donor',
        'donor_email' => 'returning.donor@example.com',
        'donor_phone' => '9822211199',
        'address' => '8 Market Street',
        'city' => 'Junagadh',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subMonths(2),
    ]);

    actingAs($user)
        ->getJson(route('admin.donations.donor-sources', $qr))
        ->assertOk()
        ->assertJsonPath('mode', 'recent_failed')
        ->assertJsonPath('donations.0.uuid', $failed->order_uuid)
        ->assertJsonMissing(['donor_name' => 'Returning Temple Donor']);

    actingAs($user)
        ->getJson(route('admin.donations.donor-sources', ['donationOrder' => $qr, 'q' => 'Returning Temple']))
        ->assertOk()
        ->assertJsonPath('mode', 'search')
        ->assertJsonPath('donations.0.donor_name', 'Returning Temple Donor');
});

it('copies donor details onto a qr payment without changing the amount', function () {
    Bus::fake();

    $user = copyDonorAdmin();

    $qr = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-pay_copy_amount',
        'provider_payment_id' => 'pay_copy_amount',
        'donor_name' => 'Unknown Donor',
        'donor_email' => '',
        'donor_phone' => 'upi-zzzzzzzzzz',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $source = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_copy_source',
        'provider_payment_id' => 'pay_copy_source',
        'donor_name' => 'Sheetal Mehta',
        'donor_email' => 'sheetal.mehta@example.com',
        'donor_phone' => '9822200001',
        'address' => 'Green City, Junagadh',
        'city' => 'Junagadh',
        'state' => 'Gujarat',
        'pincode' => '362015',
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(20),
    ]);

    actingAs($user)
        ->post(route('admin.donations.copy-donor', $qr), [
            'source_uuid' => $source->order_uuid,
        ])
        ->assertRedirect(route('admin.donations.show', $qr));

    $qr->refresh();

    expect($qr->donor_name)->toBe('Sheetal Mehta')
        ->and($qr->donor_email)->toBe('sheetal.mehta@example.com')
        ->and($qr->donor_phone)->toBe('9822200001')
        ->and($qr->address)->toBe('Green City, Junagadh')
        ->and($qr->city)->toBe('Junagadh')
        ->and((float) $qr->total_amount)->toBe(500.0)
        ->and($qr->provider_payment_id)->toBe('pay_copy_amount')
        ->and($qr->status)->toBe(DonationOrder::STATUS_PAID);

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});
