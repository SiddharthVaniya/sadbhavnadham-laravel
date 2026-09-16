<?php

use App\Models\DonationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates a certificate from the artisan command', function () {
    Storage::fake('public');

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_artisan_cert',
        'donor_name' => 'વિજયભાઈ ડોબરીયા',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::create(2026, 5, 26),
    ]);

    $this->artisan('donations:certificate', ['order' => $order->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Certificate generated successfully.')
        ->expectsOutputToContain('વિજયભાઈ ડોબરીયા')
        ->expectsOutputToContain('તારીખ : 26-05-2026');
});
