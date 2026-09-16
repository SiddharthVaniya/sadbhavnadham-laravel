<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Support\DonorPortalData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats donation item labels with a proper cause title', function () {
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

    $item = DonationItem::make([
        'cause_id' => $cause->id,
        'cause' => 'old-age-home',
        'title' => 'Custom Donation',
    ]);
    $item->setRelation('causeModel', $cause);

    expect(DonorPortalData::itemHeadline($item))
        ->toBe('Old Age Home · Custom Donation');
});

it('humanizes cause slugs when the cause relation is missing', function () {
    $item = DonationItem::make([
        'cause' => 'old-age-home',
        'title' => 'Monthly Donation',
    ]);

    expect(DonorPortalData::itemHeadline($item))
        ->toBe('Old Age Home · Monthly Donation');
});

it('uses stored cause title from item meta when available', function () {
    $item = DonationItem::make([
        'cause' => 'old-age-home',
        'title' => 'Test',
        'meta' => ['cause_title' => 'Old Age Home'],
    ]);

    expect(DonorPortalData::itemHeadline($item))
        ->toBe('Old Age Home · Test');
});

it('builds order cards with formatted money and receipt labels', function () {
    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Monil Shah',
        'donor_email' => 'monil@example.com',
        'donor_phone' => '7600280806',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 42,
    ]);

    $item = DonationItem::create([
        'donation_order_id' => $order->id,
        'cause' => 'old-age-home',
        'title' => 'Custom Donation',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
        'meta' => ['cause_title' => 'Old Age Home'],
    ]);

    $order->setRelation('items', collect([$item]));

    $card = DonorPortalData::orderCard($order);

    expect($card['cause_title'])->toBe('Old Age Home')
        ->and($card['item_title'])->toBe('Custom Donation')
        ->and($card['amount_label'])->toBe('₹1,100.00')
        ->and($card['receipt_label'])->toBe($order->receiptNumberFormatted());
});
