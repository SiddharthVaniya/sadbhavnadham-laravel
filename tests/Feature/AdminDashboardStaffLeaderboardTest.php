<?php

use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('includes a staff utm_content leaderboard on the dashboard', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_staff_a',
        'donor_name' => 'Donor A',
        'donor_email' => 'a@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'staff-riya',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_staff_b',
        'donor_name' => 'Donor B',
        'donor_email' => 'b@example.com',
        'donor_phone' => '9898237949',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'staff-amit',
    ]);

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('staffLeaderboard', 2)
            ->where('staffLeaderboard.0.content', 'staff-riya')
            ->where('staffLeaderboard.0.revenue', 3000)
            ->where('staffLeaderboard.1.content', 'staff-amit')
            ->where('staffLeaderboard.1.revenue', 1500));
});
