<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Support\AdminDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('batches partner referral stats without per-partner count queries', function () {
    $riya = User::factory()->create([
        'name' => 'Riya Patel',
        'referral_code' => 'rp',
    ]);
    User::factory()->create([
        'name' => 'Amit Shah',
        'referral_code' => 'as',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_perf_rp_1',
        'donor_name' => 'Daily Donor',
        'donor_email' => 'daily@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 2500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_content' => 'rp',
    ]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_perf_rp_2',
        'donor_name' => 'Second Donor',
        'donor_email' => 'second@example.com',
        'donor_phone' => '9898237949',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'partner_user_id' => $riya->id,
        'partner_code' => 'rp',
    ]);

    DB::enableQueryLog();
    $payload = AdminDashboardData::dailyPartnerReferrals();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($payload['total_orders'])->toBe(2)
        ->and($payload['total_revenue'])->toBe(3000.0)
        ->and($payload['partners'][0]['name'])->toBe('Riya Patel')
        ->and($payload['partners'][0]['paid_orders'])->toBe(2)
        ->and($payload['partners'][0]['revenue'])->toBe(3000.0);

    $partnerCountQueries = collect($queries)->filter(function (array $query): bool {
        $sql = strtolower($query['query']);

        return str_contains($sql, 'donation_orders')
            && str_contains($sql, 'count(')
            && str_contains($sql, 'partner_user_id');
    });

    // Exact stats use one GROUP BY; legacy uses one SELECT — not N partner COUNT(*) round-trips.
    expect($partnerCountQueries->count())->toBeLessThanOrEqual(2);
});

it('builds month options from a provided monthly trend without a second query', function () {
    $trend = AdminDashboardData::monthlyTrend();

    DB::enableQueryLog();
    $options = AdminDashboardData::monthOptions($trend);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($options)->toHaveCount(count($trend))
        ->and($queries)->toBeEmpty();
});
