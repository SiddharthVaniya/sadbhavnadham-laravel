<?php

use App\Jobs\UpdateDonationOnSheetJob;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

function createDonationEditAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage donations', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createPaidDonationForCauseEdit(string $provider, array $orderOverrides = [], array $itemOverrides = []): DonationOrder
{
    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Bull Shelter',
        'slug' => 'bull-shelter',
    ]);

    $order = DonationOrder::query()->create(array_merge([
        'payment_provider' => $provider,
        'provider_order_id' => 'order_'.$provider.'_1',
        'provider_payment_id' => 'pay_'.$provider.'_1',
        'donor_name' => 'Online Donor',
        'donor_email' => 'online@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 701,
        'sheet_logged_at' => now(),
    ], $orderOverrides));

    DonationItem::query()->create(array_merge([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'General Donation',
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
        'meta' => [
            'cause_title' => $cause->title,
            'cause_slug' => $cause->slug,
        ],
    ], $itemOverrides));

    return $order->fresh(['items']);
}

it('lets admins edit cause and package on a paid razorpay donation', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_RAZORPAY);
    $newCause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
    ]);
    $package = CausePackage::factory()->for($newCause)->create([
        'is_active' => true,
        'title' => '10 Trees',
        'amount' => 1000,
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Edit')
            ->where('donation.is_offline', false)
            ->where('donation.uuid', $order->order_uuid));

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'cause_id' => $newCause->id,
            'cause_package_id' => $package->id,
        ])
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh()->load('items');

    expect((float) $order->total_amount)->toBe(1000.0)
        ->and((int) $order->receipt_number)->toBe(701)
        ->and($order->provider_payment_id)->toBe('pay_razorpay_1')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->cause_id)->toBe($newCause->id)
        ->and($order->items->first()->cause_package_id)->toBe($package->id)
        ->and($order->items->first()->title)->toBe('10 Trees')
        ->and($order->items->first()->meta['cause_title'] ?? null)->toBe('Tree Plantation');

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});

it('lets admins edit cause on a paid cashfree donation', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_CASHFREE);
    $newCause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Old Age Home',
        'slug' => 'old-age-home',
    ]);

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'cause_id' => $newCause->id,
        ])
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh()->load('items');

    expect($order->items->first()->cause_id)->toBe($newCause->id)
        ->and($order->items->first()->meta['cause_title'] ?? null)->toBe('Old Age Home');

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});

it('rejects editing a pending donation', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_RAZORPAY, [
        'status' => DonationOrder::STATUS_PENDING,
        'paid_at' => null,
        'receipt_number' => null,
        'sheet_logged_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertNotFound();
});

it('rejects an online cause edit when package belongs to another cause', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_RAZORPAY);
    $cause = Cause::factory()->create(['is_active' => true]);
    $otherCause = Cause::factory()->create(['is_active' => true]);
    $package = CausePackage::factory()->for($otherCause)->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
        ])
        ->assertSessionHasErrors(['cause_package_id']);
});

it('updates an offline donation with only an amount', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_OFFLINE, [
        'provider_order_id' => 'manual-amount-only',
        'provider_payment_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'total_amount' => 2500,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.donations.offline'));

    $order->refresh();

    expect((float) $order->total_amount)->toBe(2500.0);
});

it('dispatches sheet update when an offline donation is edited', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_OFFLINE, [
        'provider_order_id' => 'manual-edit-sheet',
        'provider_payment_id' => null,
        'address' => '123 Test Street',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
    ]);
    $cause = Cause::factory()->create(['is_active' => true, 'title' => 'Animal Hospital']);

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'donor_name' => 'Online Donor',
            'donor_email' => 'online@example.com',
            'donor_phone' => '9876543211',
            'address' => '123 Test Street',
            'pincode' => '360001',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'total_amount' => 1000,
            'cause_id' => $cause->id,
            'item_title' => 'Monthly Support',
        ])
        ->assertRedirect(route('admin.donations.offline'));

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});

it('lets admins complete donor details on a razorpay qr donation without changing amount', function () {
    $user = createDonationEditAdmin();
    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Old Age Home',
        'slug' => 'old-age-home',
    ]);

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'provider_order_id' => 'qr-pay_qr_unknown_1',
        'provider_payment_id' => 'pay_qr_unknown_1',
        'donor_name' => 'Unknown Donor',
        'donor_email' => 'unknown-qr@example.test',
        'donor_phone' => 'u-abc123',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 1237,
        'sheet_logged_at' => now(),
        'city' => null,
        'address' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Edit')
            ->where('donation.is_offline', false)
            ->where('donation.can_edit_donor_details', true)
            ->where('donation.can_edit_amount', false)
            ->where('donation.provider', DonationOrder::PROVIDER_RAZORPAY_QR));

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'donor_name' => 'KamlaBen Rabari',
            'donor_email' => 'kamla@example.com',
            'donor_phone' => '9876501234',
            'address' => 'Dabhoi Road, Vadodara',
            'pincode' => '390001',
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'country' => 'INDIA',
            'cause_id' => $cause->id,
            'quantity' => 5,
            'total_amount' => 9999,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh()->load('items');

    expect($order->donor_name)->toBe('KamlaBen Rabari')
        ->and($order->donor_email)->toBe('kamla@example.com')
        ->and($order->donor_phone)->toBe('9876501234')
        ->and($order->city)->toBe('Vadodara')
        ->and($order->address)->toBe('Dabhoi Road, Vadodara')
        ->and((float) $order->total_amount)->toBe(3000.0)
        ->and((int) $order->receipt_number)->toBe(1237)
        ->and($order->provider_payment_id)->toBe('pay_qr_unknown_1')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->cause_id)->toBe($cause->id)
        ->and((int) $order->items->first()->quantity)->toBe(5)
        ->and((float) $order->items->first()->amount)->toBe(3000.0)
        ->and($order->items->first()->meta['cause_title'] ?? null)->toBe('Old Age Home');

    Bus::assertDispatched(UpdateDonationOnSheetJob::class);
});

it('lets admins fully edit a manual danamojo offline donation', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_DANAMOJO, [
        'provider_order_id' => 'manual-danamojo-edit-1',
        'provider_payment_id' => null,
        'source_channel' => 'offline',
        'donor_name' => 'Sanjay Morzaria',
        'donor_email' => 'sanjay@example.com',
        'donor_phone' => '447932623852',
        'address' => 'Wrong Street',
        'pincode' => 'N1 9GU',
        'city' => 'London',
        'state' => 'London',
        'country' => 'UNITED KINGDOM',
        'donor_country_code' => 'GB',
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Edit')
            ->where('donation.is_offline', true)
            ->where('donation.can_edit_donor_details', true)
            ->where('donation.can_edit_amount', true)
            ->where('donation.provider', DonationOrder::PROVIDER_DANAMOJO));

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'donor_name' => 'Sanjay Morzaria',
            'donor_email' => 'sanjay_morzaria@hotmail.co.uk',
            'donor_phone' => '7932623852',
            'phone_dial_code' => '44',
            'donor_country_code' => 'GB',
            'address' => '55 Sefton Avenue',
            'pincode' => 'N1 9GU',
            'city' => 'Islington',
            'state' => 'London',
            'country' => 'UNITED KINGDOM',
            'total_amount' => 3500,
            'cause_id' => $order->items->first()->cause_id,
            'item_title' => 'International Support',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.donations.offline'));

    $order->refresh();

    expect($order->donor_email)->toBe('sanjay_morzaria@hotmail.co.uk')
        ->and($order->donor_phone)->toBe('447932623852')
        ->and($order->address)->toBe('55 Sefton Avenue')
        ->and($order->city)->toBe('Islington')
        ->and((float) $order->total_amount)->toBe(3500.0);
});

it('does not update donor details when editing a normal razorpay donation', function () {
    $user = createDonationEditAdmin();
    $order = createPaidDonationForCauseEdit(DonationOrder::PROVIDER_RAZORPAY, [
        'donor_name' => 'Online Donor',
        'donor_email' => 'online@example.com',
        'donor_phone' => '9876543211',
        'city' => 'Rajkot',
    ]);
    $newCause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('donation.can_edit_donor_details', false)
            ->where('donation.can_edit_amount', false));

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'cause_id' => $newCause->id,
            'donor_name' => 'Hacked Name',
            'donor_email' => 'hacked@example.com',
            'donor_phone' => '9000000000',
            'city' => 'Mumbai',
        ])
        ->assertRedirect(route('admin.donations.show', $order));

    $order->refresh();

    expect($order->donor_name)->toBe('Online Donor')
        ->and($order->donor_email)->toBe('online@example.com')
        ->and($order->donor_phone)->toBe('9876543211')
        ->and($order->city)->toBe('Rajkot')
        ->and($order->items->first()->cause_id)->toBe($newCause->id);
});
