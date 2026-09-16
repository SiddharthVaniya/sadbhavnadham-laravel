<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('loads dashboard inertia page with paid donation analytics', function () {
    $cause = Cause::factory()->create();
    $birthday = today()->subYears(30);

    $testDonor = Donor::factory()->create([
        'name' => 'Test Donor',
        'email' => 'donor@example.com',
        'phone' => '1234567890',
        'date_of_birth' => $birthday,
    ]);

    $donationOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'test-order',
        'donor_id' => $testDonor->id,
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '1234567890',
        'date_of_birth' => $birthday,
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $donationOrder->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => $cause->title,
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'test-order-pending-same-donor',
        'donor_id' => $testDonor->id,
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '1234567890',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.totalDonations', 1)
            ->where('stats.totalDonationAmount', 500)
            ->where('stats.statusCounts.paid', 1)
            ->where('stats.statusCounts.pending', 1)
            ->has('monthlyTrend', 12)
            ->has('recentDonations.data', 2)
            ->where('recentDonations.data.0.donor_name', 'Test Donor')
            ->where('recentDonations.meta.total', 2)
            ->has('topDonors', 1)
            ->has('todaysGifts', 1)
            ->where('todaysGifts.0.donor_name', 'Test Donor')
            ->where('topDonors.0.donor_name', 'Test Donor')
            ->where('topDonors.0.amount', 500)
            ->has('topCauses', 1)
            ->where('topCauses.0.title', $cause->title)
            ->has('todaysBirthdays', 1)
            ->where('todaysBirthdays.0.donor_name', 'Test Donor'));
});

it('excludes far-future birthday donors from the dashboard birthday payload', function () {
    $cause = Cause::factory()->create();
    $todayDonor = Donor::factory()->create([
        'name' => 'Today Birthday',
        'email' => 'today-bday@example.com',
        'phone' => '1111111111',
        'date_of_birth' => today()->subYears(40),
    ]);
    $farDonor = Donor::factory()->create([
        'name' => 'Far Birthday',
        'email' => 'far-bday@example.com',
        'phone' => '2222222222',
        'date_of_birth' => today()->addDays(20)->subYears(25),
    ]);

    foreach ([$todayDonor, $farDonor] as $donor) {
        $order = DonationOrder::create([
            'payment_provider' => 'razorpay',
            'provider_order_id' => 'bday-'.$donor->id,
            'donor_id' => $donor->id,
            'donor_name' => $donor->name,
            'donor_email' => $donor->email,
            'donor_phone' => $donor->phone,
            'currency' => 'INR',
            'total_amount' => 100,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        DonationItem::create([
            'donation_order_id' => $order->id,
            'cause_id' => $cause->id,
            'cause' => $cause->title,
            'title' => $cause->title,
            'quantity' => 1,
            'unit_amount' => 100,
            'amount' => 100,
        ]);
    }

    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('todaysBirthdays', 1)
            ->where('todaysBirthdays.0.donor_name', 'Today Birthday')
            ->where('upcomingBirthdays.data', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['donor_name'] !== 'Far Birthday'
            )));
});

it('counts today dashboard totals by paid date not checkout created date', function () {
    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');

    $backloggedDonation = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'dashboard-backlog-order',
        'donor_name' => 'Backlog Donor',
        'donor_email' => 'backlog@example.com',
        'donor_phone' => '9999999988',
        'currency' => 'INR',
        'total_amount' => 2100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);
    $backloggedDonation->forceFill(['created_at' => now()->subDays(3)])->save();

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.todayDonations', 1)
            ->where('stats.todayAmount', 2100));
});
