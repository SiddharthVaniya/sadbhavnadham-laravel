<?php

use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createSubscriptionBadgeAdminUser(): User
{
    foreach (['view donations', 'view all donations', 'view subscriptions'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations', 'view all donations', 'view subscriptions']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('marks recurring donations on the admin donations index', function () {
    $user = createSubscriptionBadgeAdminUser();

    $subscription = DonationSubscription::factory()->active()->create([
        'donor_name' => 'Recurring Donor',
        'donor_email' => 'recurring@example.com',
    ]);

    DonationOrder::create([
        'donation_subscription_id' => $subscription->id,
        'billing_cycle_number' => 2,
        'is_recurring' => true,
        'payment_provider' => 'razorpay',
        'provider_payment_id' => 'pay_recurring_badge',
        'donor_name' => 'Recurring Donor',
        'donor_email' => 'recurring@example.com',
        'donor_phone' => '9999999991',
        'currency' => 'INR',
        'total_amount' => 501,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_payment_id' => 'pay_one_time_badge',
        'donor_name' => 'One Time Donor',
        'donor_email' => 'onetime@example.com',
        'donor_phone' => '9999999992',
        'currency' => 'INR',
        'total_amount' => 250,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'is_recurring' => false,
    ]);

    actingAs($user)
        ->get(route('admin.donations.index', ['duration' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Index')
            ->has('donations.data', 2)
            ->where('donations.data', function ($rows) {
                $byPayment = collect($rows)->keyBy('payment_id');
                $recurring = $byPayment->get('pay_recurring_badge');
                $oneTime = $byPayment->get('pay_one_time_badge');

                if (! is_array($recurring) || ! is_array($oneTime)) {
                    return false;
                }

                return ($recurring['is_recurring'] ?? false) === true
                    && (int) ($recurring['billing_cycle_number'] ?? 0) === 2
                    && filled($recurring['subscription']['uuid'] ?? null)
                    && ($oneTime['is_recurring'] ?? true) === false
                    && ($oneTime['subscription'] ?? null) === null;
            }));
});

it('shows subscription context on the admin donation detail page', function () {
    $user = createSubscriptionBadgeAdminUser();

    $subscription = DonationSubscription::factory()->halted()->create([
        'donor_name' => 'Halted Donor',
        'donor_email' => 'halted@example.com',
    ]);

    $order = DonationOrder::create([
        'donation_subscription_id' => $subscription->id,
        'billing_cycle_number' => 3,
        'is_recurring' => true,
        'payment_provider' => 'razorpay',
        'provider_payment_id' => 'pay_halted_cycle',
        'donor_name' => 'Halted Donor',
        'donor_email' => 'halted@example.com',
        'donor_phone' => '9999999993',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->where('donation.is_recurring', true)
            ->where('donation.billing_cycle_number', 3)
            ->where('donation.subscription.uuid', $subscription->subscription_uuid)
            ->where('donation.subscription.status', DonationSubscription::STATUS_HALTED)
            ->where('donation.subscription.url', route('admin.subscriptions.show', $subscription)));
});
