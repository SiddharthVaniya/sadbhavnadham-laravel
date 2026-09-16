<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Support\DonorPortalSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from my donations', function () {
    $this->get(route('donate.portal.index'))
        ->assertRedirect(route('donate.index', ['signin' => 1]));
});

it('lists paid donations for the signed-in donor and serves their receipt', function () {
    $donor = Donor::factory()->create([
        'name' => 'Monil Shah',
        'email' => 'monil@example.com',
        'phone' => '7600280806',
    ]);

    $ownOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subDay(),
        'receipt_number' => 42,
    ]);

    $cause = Cause::query()->create([
        'slug' => 'old-age-home',
        'title' => 'Old Age Home',
        'excerpt' => 'Care for elders',
        'description' => 'Care for elders',
        'images' => [],
        'details' => [],
        'default_title' => 'General Donation',
        'cta_text' => 'Donate',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    DonationItem::create([
        'donation_order_id' => $ownOrder->id,
        'cause_id' => $cause->id,
        'cause' => 'old-age-home',
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
        'meta' => ['cause_title' => 'Old Age Home'],
    ]);

    $otherOrder = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Someone Else',
        'donor_email' => 'other@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subDays(2),
        'receipt_number' => 43,
    ]);

    app(DonorPortalSession::class)->login($donor);

    $this->get(route('donate.portal.index'))
        ->assertOk()
        ->assertSee('My donations')
        ->assertSee('Old Age Home')
        ->assertSee('Custom Donation')
        ->assertSee('₹1,100.00')
        ->assertSee('Total supported')
        ->assertSee($ownOrder->receiptNumberFormatted())
        ->assertDontSee('₹500.00');

    $this->get(route('donate.portal.receipt', $ownOrder))
        ->assertOk()
        ->assertSee($ownOrder->receiptNumberFormatted())
        ->assertSee('Monil Shah');

    $this->get(route('donate.portal.receipt', $otherOrder))
        ->assertNotFound();
});

it('hides unpaid donations from the donor portal list', function () {
    $donor = Donor::factory()->create([
        'phone' => '9000011111',
        'email' => 'pending@example.com',
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_id' => $donor->id,
        'donor_name' => 'Pending Donor',
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'total_amount' => 250,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(DonorPortalSession::class)->login($donor);

    $this->get(route('donate.portal.index'))
        ->assertOk()
        ->assertSee('No paid donations yet')
        ->assertDontSee('₹250.00');
});
