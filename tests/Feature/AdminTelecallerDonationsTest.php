<?php

use App\Models\DonationOrder;
use App\Models\DonationTelecallerNote;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\TelecallerPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createTelecallerUser(array $extraPermissions = []): User
{
    Permission::findOrCreate('view donations');
    Permission::findOrCreate('view all donations');

    $role = Role::firstOrCreate(['name' => 'Telecaller', 'guard_name' => 'web']);
    $role->syncPermissions(array_merge(['view donations'], $extraPermissions));

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function createTelecallerDonation(string $status, string $name): DonationOrder
{
    return DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-'.uniqid(),
        'donor_name' => $name,
        'donor_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        'donor_phone' => '9999999901',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => $status,
        'failed_at' => $status === DonationOrder::STATUS_FAILED ? now()->subDay() : null,
    ]);
}

it('shows telecaller navigation to the dedicated failed donations page', function () {
    $user = createTelecallerUser(['view all donations']);

    $navigation = AdminNavigation::build($user);
    $donations = collect($navigation)->firstWhere('label', 'Donations');

    expect(collect($navigation)->pluck('label')->all())->toBe(['Donations'])
        ->and($donations['route'])->toBe('admin.donations.telecaller')
        ->and($donations['href'])->toBe(route('admin.donations.telecaller', TelecallerPortal::homeRouteParameters()));
});

it('lists only failed donations on the telecaller page', function () {
    $user = createTelecallerUser();

    createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Failed Donor');
    createTelecallerDonation(DonationOrder::STATUS_PAID, 'Paid Donor');

    actingAs($user)
        ->get(route('admin.donations.telecaller', TelecallerPortal::homeRouteParameters()))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Telecaller/FailedDonations')
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Failed Donor'));
});

it('redirects telecallers from the main donations index to the telecaller page', function () {
    $user = createTelecallerUser();

    actingAs($user)
        ->get(route('admin.donations.index'))
        ->assertRedirect(route('admin.donations.telecaller'));
});

it('redirects telecallers away from offline donations', function () {
    $user = createTelecallerUser();

    actingAs($user)
        ->get(route('admin.donations.offline'))
        ->assertRedirect(route('admin.donations.telecaller', TelecallerPortal::homeRouteParameters()));
});

it('finds donations by mobile on the telecaller search page', function () {
    $user = createTelecallerUser();

    $phone = '9876543210';
    createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Mobile Donor A');
    DonationOrder::query()->latest('id')->first()?->update(['donor_phone' => $phone]);

    createTelecallerDonation(DonationOrder::STATUS_PAID, 'Mobile Donor B');
    DonationOrder::query()->latest('id')->first()?->update(['donor_phone' => $phone]);

    actingAs($user)
        ->get(route('admin.donations.telecaller.search', ['phone' => $phone]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Telecaller/SearchByMobile')
            ->where('normalizedPhone', $phone)
            ->has('donations.data', 2));
});

it('returns quick-response outcomes with default messages for telecaller notes', function () {
    $user = createTelecallerUser();
    $order = createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Outcome Donor');

    actingAs($user)
        ->getJson(route('admin.donations.telecaller-notes.index', $order))
        ->assertOk()
        ->assertJsonPath('outcomes.0.value', 'not_receive')
        ->assertJsonPath('outcomes.0.default_message', 'Not receive')
        ->assertJsonPath('outcomes.3.value', 'send_details')
        ->assertJsonPath('outcomes.3.default_message', 'Send details')
        ->assertJsonPath('outcomes.4.value', 'paid');
});

it('stores a telecaller note with a quick-response outcome', function () {
    $user = createTelecallerUser();
    $order = createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Note Donor');

    actingAs($user)
        ->postJson(route('admin.donations.telecaller-notes.store', $order), [
            'speaker' => 'telecaller',
            'message' => 'Busy',
            'outcome' => 'busy',
        ])
        ->assertCreated()
        ->assertJsonPath('notes.0.outcome_label', 'Busy')
        ->assertJsonPath('notes.0.message', 'Busy');
});

it('filters failed donations by telecaller outcome', function () {
    $user = createTelecallerUser();

    $busy = createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Busy Filter Donor');
    $other = createTelecallerDonation(DonationOrder::STATUS_FAILED, 'Other Filter Donor');

    DonationTelecallerNote::query()->create([
        'donation_order_id' => $busy->id,
        'user_id' => $user->id,
        'telecaller_name' => $user->name,
        'speaker' => DonationTelecallerNote::SPEAKER_TELECALLER,
        'message' => 'Busy',
        'outcome' => 'busy',
    ]);

    DonationTelecallerNote::query()->create([
        'donation_order_id' => $other->id,
        'user_id' => $user->id,
        'telecaller_name' => $user->name,
        'speaker' => DonationTelecallerNote::SPEAKER_TELECALLER,
        'message' => 'Paid',
        'outcome' => 'paid',
    ]);

    actingAs($user)
        ->get(route('admin.donations.telecaller', [
            'duration' => 'all',
            'telecaller_outcome' => 'busy',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('donations.data', 1)
            ->where('donations.data.0.donor_name', 'Busy Filter Donor'));
});

it('forbids non-telecallers from the telecaller donations page', function () {
    Permission::findOrCreate('view donations');
    $role = Role::findOrCreate('admin');
    $role->syncPermissions(['view donations']);

    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user)
        ->get(route('admin.donations.telecaller'))
        ->assertForbidden();
});
