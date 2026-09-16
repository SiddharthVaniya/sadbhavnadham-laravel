<?php

use App\Models\DonationOrder;
use App\Models\Donor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deduplicates donors using email and phone during snapshot resolution', function () {
    $first = Donor::resolveFromDonationSnapshot([
        'donor_name' => 'Asha Patel',
        'donor_email' => 'Asha@example.com',
        'donor_phone' => '9876543210',
        'city' => 'Ahmedabad',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'consent_indian_citizen' => true,
    ]);

    $second = Donor::resolveFromDonationSnapshot([
        'donor_name' => 'Asha P',
        'donor_email' => 'asha@EXAMPLE.com',
        'donor_phone' => '9876543210',
        'city' => 'Vadodara',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'consent_indian_citizen' => true,
    ]);

    expect($first->id)->toBe($second->id);
    expect(Donor::query()->count())->toBe(1);

    $donor = Donor::query()->firstOrFail();
    expect($donor->name)->toBe('Asha P');
    expect($donor->city)->toBe('Vadodara');
});

it('links donation orders to donor while preserving snapshot fields', function () {
    $donor = Donor::resolveFromDonationSnapshot([
        'donor_name' => 'Ravi Shah',
        'donor_email' => 'ravi@example.com',
        'donor_phone' => '9876500000',
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'consent_indian_citizen' => true,
    ]);

    $order = DonationOrder::create([
        'donor_id' => $donor->id,
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order-donor-link',
        'donor_name' => 'Ravi Shah',
        'donor_email' => 'ravi@example.com',
        'donor_phone' => '9876500000',
        'country' => 'INDIA',
        'donor_country_code' => 'IN',
        'consent_indian_citizen' => true,
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    expect($order->donor)->not->toBeNull();
    expect($order->donor?->email)->toBe('ravi@example.com');
    expect($order->donor_name)->toBe('Ravi Shah');
});
