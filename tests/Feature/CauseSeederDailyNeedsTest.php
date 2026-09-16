<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use Database\Seeders\CauseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the daily need cause and reassigns daily needs donation items', function () {
    $oldAgeHome = Cause::factory()->create([
        'slug' => 'old-age-home',
        'title' => 'Old Age Home',
        'contact_address' => 'Sadbhavna Vrudhashram',
        'contact_phone' => '+91 85301 38001',
        'is_active' => true,
    ]);

    $order = DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_daily_needs_test',
        'donor_name' => 'Siddharth Vaniya',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 4967,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $item = DonationItem::query()->create([
        'donation_order_id' => $order->id,
        'cause_id' => $oldAgeHome->id,
        'cause' => $oldAgeHome->slug,
        'title' => 'Daily Needs – Moong Beans (1 kg) (5 days)',
        'quantity' => 1,
        'unit_amount' => 4967,
        'amount' => 4967,
        'meta' => [
            'cause_title' => $oldAgeHome->title,
            'cause_slug' => $oldAgeHome->slug,
        ],
    ]);

    (new CauseSeeder)->seedDailyNeedsOnly();

    $dailyNeeds = Cause::query()->where('slug', Cause::SLUG_DAILY_NEEDS)->first();

    expect($dailyNeeds)->not->toBeNull()
        ->and($dailyNeeds->title)->toBe('Daily Need')
        ->and($dailyNeeds->contact_address)->toBe('Sadbhavna Vrudhashram')
        ->and($dailyNeeds->is_active)->toBeTrue();

    $item->refresh();

    expect($item->cause_id)->toBe($dailyNeeds->id)
        ->and($item->cause)->toBe(Cause::SLUG_DAILY_NEEDS)
        ->and($item->meta['cause_title'] ?? null)->toBe('Daily Need')
        ->and($item->meta['cause_slug'] ?? null)->toBe(Cause::SLUG_DAILY_NEEDS);

    expect($dailyNeeds->packages()->count())->toBe(14)
        ->and($dailyNeeds->packages()->where('title', 'Moong Beans')->value('amount'))->toBe('123.00');
});
