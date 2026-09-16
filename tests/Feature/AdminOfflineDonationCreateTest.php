<?php

use App\Jobs\LogDonationToSheetJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\Cause;
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

function createOfflineDonationAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage donations']);
    Permission::firstOrCreate(['name' => 'view donations']);
    Permission::firstOrCreate(['name' => 'view all donations']);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage donations', 'view donations', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function offlineDonationPayload(array $overrides = []): array
{
    return array_merge([
        'donor_name' => 'Offline Donor',
        'donor_email' => '',
        'donor_phone' => '9876543210',
        'address' => '123 Test Street',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'payment_provider' => 'offline',
        'total_amount' => 1500,
        'send_receipt_email' => false,
    ], $overrides);
}

it('stores an offline donation without donor email', function () {
    $user = createOfflineDonationAdmin();

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload());

    $response->assertRedirect(route('admin.donations.index'));

    expect(DonationOrder::query()->where('donor_phone', '9876543210')->exists())->toBeTrue();
});

it('stores an international offline donation with postal code letters and dial code', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'donor_name' => 'Sanjay Morzaria',
            'donor_email' => 'sanjay_morzaria@hotmail.co.uk',
            'donor_phone' => '7932623852',
            'phone_dial_code' => '44',
            'donor_country_code' => 'GB',
            'country' => 'UNITED KINGDOM',
            'address' => '55 Sefton Avenue',
            'pincode' => 'N1 9GU',
            'city' => 'London',
            'state' => 'England',
            'payment_provider' => 'danamojo',
            'total_amount' => 2500,
            'send_receipt_email' => false,
        ]))
        ->assertRedirect(route('admin.donations.index'))
        ->assertSessionHasNoErrors();

    $order = DonationOrder::query()->where('donor_email', 'sanjay_morzaria@hotmail.co.uk')->first();

    expect($order)->not->toBeNull()
        ->and($order->pincode)->toBe('N1 9GU')
        ->and($order->donor_phone)->toBe('447932623852')
        ->and($order->donor_country_code)->toBe('GB')
        ->and($order->country)->toBe('UNITED KINGDOM')
        ->and($order->payment_provider)->toBe(DonationOrder::PROVIDER_DANAMOJO)
        ->and($order->consent_indian_citizen)->toBeFalse();
});

it('queues google sheet logging for every offline donation', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'send_receipt_email' => false,
        ]))
        ->assertRedirect(route('admin.donations.index'));

    Bus::assertDispatched(LogDonationToSheetJob::class);
});

it('queues whatsapp jobs when requested for offline donations', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['is_active' => true]);

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'cause_id' => $cause->id,
            'send_receipt_email' => false,
            'send_whatsapp_thank_you' => true,
            'send_whatsapp_certificate' => true,
        ]))
        ->assertRedirect(route('admin.donations.index'));

    Bus::assertChained([
        SendThankYouWhatsAppJob::class,
        SendCertificateWhatsAppJob::class,
    ]);
});

it('requires cause when whatsapp send is requested', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'send_whatsapp_thank_you' => true,
        ]))
        ->assertSessionHasErrors('cause_id');
});

it('stores an offline donation with only an amount', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'total_amount' => 251,
            'send_receipt_email' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.donations.index'));

    $order = DonationOrder::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_provider)->toBe('offline')
        ->and((float) $order->total_amount)->toBe(251.0)
        ->and($order->donor_name)->toBe('Unknown Donor')
        ->and($order->donor_phone)->toStartWith('u-')
        ->and($order->status)->toBe(DonationOrder::STATUS_PAID);
});

it('rejects an offline donation without an amount', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'donor_name' => 'No Amount Donor',
        ])
        ->assertSessionHasErrors(['total_amount']);
});

it('requires phone when whatsapp send is requested', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['is_active' => true]);

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'total_amount' => 500,
            'cause_id' => $cause->id,
            'send_whatsapp_thank_you' => true,
        ])
        ->assertSessionHasErrors(['donor_phone']);
});

it('lists every offline donation on the offline history page', function () {
    $user = createOfflineDonationAdmin();

    foreach ([0, 3, 30] as $index => $daysAgo) {
        $this->actingAs($user)->post(route('admin.donations.store'), offlineDonationPayload([
            'donor_phone' => '900000000'.$index,
            'donation_date' => now()->subDays($daysAgo)->toDateString(),
            'send_receipt_email' => false,
        ]))->assertRedirect(route('admin.donations.index'));
    }

    $this->actingAs($user)
        ->get(route('admin.donations.offline'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Offline')
            ->has('donations.data', 3));
});

it('lists manual entries stored with another provider on the offline history page', function () {
    $user = createOfflineDonationAdmin();

    DonationOrder::query()->create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'manual-20260718090000-legacy',
        'donor_name' => 'Legacy Manual Donor',
        'donor_email' => '',
        'donor_phone' => '9876500001',
        'currency' => 'INR',
        'total_amount' => 750,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 901,
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.offline'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Offline')
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Legacy Manual Donor'));
});

it('stores the selected payment provider for manual entries', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'payment_provider' => 'razorpay_qr',
        ]))
        ->assertRedirect(route('admin.donations.index'));

    $order = DonationOrder::query()->where('donor_phone', '9876543210')->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_provider)->toBe('razorpay_qr');
});

it('allows amount-only manual entry with razorpay qr provider', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'payment_provider' => 'razorpay_qr',
            'total_amount' => 501,
            'send_receipt_email' => false,
        ])
        ->assertRedirect(route('admin.donations.index'));

    $order = DonationOrder::query()->where('total_amount', 501)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_provider)->toBe('razorpay_qr')
        ->and($order->donor_name)->toBe('Unknown Donor')
        ->and($order->donor_phone)->toStartWith('u-');
});

it('requires pan for large amount even without a cause', function () {
    $user = createOfflineDonationAdmin();

    $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), [
            'payment_provider' => 'offline',
            'total_amount' => 100000,
            'send_receipt_email' => false,
        ])
        ->assertSessionHasErrors(['pan_number']);
});

it('passes payment providers to the create page', function () {
    $user = createOfflineDonationAdmin();

    $this->actingAs($user)
        ->get(route('admin.donations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Create')
            ->has('paymentProviders')
            ->where('paymentProviders.0.value', 'offline')
            ->has('next_receipts')
            ->has('next_receipts.offline')
            ->has('next_receipt_number')
            ->has('next_receipt_formatted')
            ->where('next_receipt_formatted', fn (string $value) => str_starts_with($value, 'MSCT-OFF-')));
});

it('auto-assigns the next offline receipt number when create leaves it blank', function () {
    $user = createOfflineDonationAdmin();

    DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'manual-existing-receipt',
        'donor_name' => 'Existing',
        'donor_email' => 'existing@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '50',
    ]);

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'receipt_number' => '',
            'send_receipt_email' => false,
        ]))
        ->assertRedirect();

    $order = DonationOrder::query()->where('donor_name', 'Offline Donor')->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and((int) $order->receipt_number)->toBe(51)
        ->and($order->receiptNumberFormatted())->toBe('MSCT-OFF-51');
});

it('uses a manual receipt number on offline create when provided', function () {
    $user = createOfflineDonationAdmin();

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'receipt_number' => 777,
            'send_receipt_email' => false,
        ]))
        ->assertRedirect();

    $order = DonationOrder::query()->where('donor_name', 'Offline Donor')->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and((int) $order->receipt_number)->toBe(777)
        ->and($order->receiptNumberFormatted())->toBe('MSCT-OFF-777');
});

it('rejects a duplicate receipt number within the same provider family', function () {
    $user = createOfflineDonationAdmin();

    DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'manual-dup-receipt',
        'donor_name' => 'Existing',
        'donor_email' => 'existing@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '888',
    ]);

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'receipt_number' => 888,
            'send_receipt_email' => false,
        ]))
        ->assertSessionHasErrors('receipt_number');
});

it('allows the same receipt number across different provider families', function () {
    $user = createOfflineDonationAdmin();

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_cross_family',
        'donor_name' => 'Existing',
        'donor_email' => 'existing@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '888',
    ]);

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'receipt_number' => 888,
            'payment_provider' => 'offline',
            'send_receipt_email' => false,
        ]))
        ->assertRedirect();

    $order = DonationOrder::query()->where('donor_name', 'Offline Donor')->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and((int) $order->receipt_number)->toBe(888)
        ->and($order->receiptNumberFormatted())->toBe('MSCT-OFF-888');
});

it('shows provider-scoped next receipts on the create page', function () {
    $user = createOfflineDonationAdmin();

    DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'manual-peek-receipt',
        'donor_name' => 'Existing',
        'donor_email' => 'existing@example.com',
        'donor_phone' => '9999999999',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '120',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_DANAMOJO,
        'provider_order_id' => 'manual-peek-danamojo',
        'donor_name' => 'Danamojo Existing',
        'donor_email' => 'danamojo@example.com',
        'donor_phone' => '9999999998',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'receipt_number' => '5',
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Create')
            ->where('next_receipt_number', 121)
            ->where('next_receipt_formatted', 'MSCT-OFF-121')
            ->where('next_receipts.offline.number', 121)
            ->where('next_receipts.offline.formatted', 'MSCT-OFF-121')
            ->where('next_receipts.danamojo.number', 6)
            ->where('next_receipts.danamojo.formatted', 'MSCT-DNMJ-6')
            ->where('next_receipts.cashfree.formatted', fn (string $value) => str_starts_with($value, 'MSCT-CF-')));
});

it('assigns danamojo and cashfree prefixes for those providers', function () {
    $user = createOfflineDonationAdmin();

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'payment_provider' => 'danamojo',
            'send_receipt_email' => false,
        ]))
        ->assertRedirect();

    $danamojo = DonationOrder::query()->where('payment_provider', 'danamojo')->latest('id')->first();

    expect($danamojo->receiptNumberFormatted())->toStartWith('MSCT-DNMJ-');

    $this->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'donor_name' => 'Cashfree Donor',
            'payment_provider' => 'cashfree',
            'send_receipt_email' => false,
        ]))
        ->assertRedirect();

    $cashfree = DonationOrder::query()->where('payment_provider', 'cashfree')->latest('id')->first();

    expect($cashfree->receiptNumberFormatted())->toStartWith('MSCT-CF-');
});

it('returns admin pan requirement status for a selected cause', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    $this->actingAs($user)
        ->postJson(route('admin.donations.pan-requirement'), [
            'cause_id' => $cause->id,
            'total_amount' => 100000,
            'donor_email' => null,
            'donor_phone' => '9876543210',
        ])
        ->assertOk()
        ->assertJson([
            'required' => true,
            'current_amount' => 100000,
        ]);
});

it('rejects offline donation when pan is required but missing', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'cause_id' => $cause->id,
            'total_amount' => 100000,
            'pan_number' => null,
        ]));

    $response->assertSessionHasErrors(['pan_number']);
});

it('requires email when send receipt email is enabled', function () {
    $user = createOfflineDonationAdmin();

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'send_receipt_email' => true,
        ]));

    $response->assertSessionHasErrors(['donor_email']);
});

it('passes causes with pan_required flag to the create page', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['pan_required' => true, 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('admin.donations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Create')
            ->has('causes', 1)
            ->where('causes.0.id', $cause->id)
            ->where('causes.0.pan_required', true)
            ->has('packages'));
});

it('stores an offline donation with package and a past donation date', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['is_active' => true]);
    $package = \App\Models\CausePackage::factory()->for($cause)->create([
        'title' => 'Morning Breakfast',
        'amount' => 500,
        'is_active' => true,
    ]);

    $donationDate = now()->subDays(10)->toDateString();

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
            'total_amount' => 500,
            'donation_date' => $donationDate,
        ]));

    $response->assertRedirect(route('admin.donations.index'));

    $order = DonationOrder::query()->where('donor_phone', '9876543210')->first();

    expect($order)->not->toBeNull()
        ->and($order->created_at->toDateString())->toBe($donationDate)
        ->and($order->paid_at->toDateString())->toBe($donationDate)
        ->and($order->items->first()->cause_package_id)->toBe($package->id)
        ->and($order->items->first()->title)->toBe('Morning Breakfast');
});

it('rejects a donation date in the future', function () {
    $user = createOfflineDonationAdmin();

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'donation_date' => now()->addDay()->toDateString(),
        ]));

    $response->assertSessionHasErrors(['donation_date']);
});

it('rejects a package that does not belong to the selected cause', function () {
    $user = createOfflineDonationAdmin();
    $cause = Cause::factory()->create(['is_active' => true]);
    $otherCause = Cause::factory()->create(['is_active' => true]);
    $package = \App\Models\CausePackage::factory()->for($otherCause)->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->post(route('admin.donations.store'), offlineDonationPayload([
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
        ]));

    $response->assertSessionHasErrors(['cause_package_id']);
});

it('lets admins edit an offline donation and assign a cause', function () {
    $user = createOfflineDonationAdmin();

    $order = DonationOrder::query()->create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'manual-edit-test',
        'donor_name' => 'Offline Donor',
        'donor_email' => 'edit@example.com',
        'donor_phone' => '9876543210',
        'address' => '123 Test Street',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 690,
    ]);

    $cause = Cause::factory()->create(['is_active' => true, 'title' => 'Old Age Home']);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Edit')
            ->where('donation.uuid', $order->order_uuid));

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), offlineDonationPayload([
            'donor_email' => 'edit@example.com',
            'total_amount' => 500,
            'cause_id' => $cause->id,
            'item_title' => 'Monthly Donation',
        ]))
        ->assertRedirect(route('admin.donations.offline'));

    $order->refresh()->load('items.causeModel');

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->cause_id)->toBe($cause->id)
        ->and($order->items->first()->causeModel?->title)->toBe('Old Age Home');

    Bus::assertDispatched(\App\Jobs\UpdateDonationOnSheetJob::class);
});

it('allows editing a paid razorpay donation cause', function () {
    $user = createOfflineDonationAdmin();

    $order = DonationOrder::query()->create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_online_only',
        'donor_name' => 'Online Donor',
        'donor_email' => 'online@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 800,
    ]);

    $cause = Cause::factory()->create(['is_active' => true, 'title' => 'Tree Plantation']);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertOk();

    $this->actingAs($user)
        ->put(route('admin.donations.update', $order), [
            'cause_id' => $cause->id,
        ])
        ->assertRedirect(route('admin.donations.show', $order));
});

it('returns not found when trying to edit a pending razorpay donation', function () {
    $user = createOfflineDonationAdmin();

    $order = DonationOrder::query()->create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_pending_only',
        'donor_name' => 'Online Donor',
        'donor_email' => 'online@example.com',
        'donor_phone' => '9876543211',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('admin.donations.edit', $order))
        ->assertNotFound();
});
