<?php

use App\Jobs\LogDonationToSheetJob;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\GoogleSheetsLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function createSheetTestOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'sheet-order-'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Sheet Donor',
        'donor_email' => 'sheet@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_FAILED,
    ], $overrides));
}

it('skips google sheet logging for failed donations when the setting is off', function () {
    Setting::query()->updateOrCreate(
        ['key' => Setting::LOG_FAILED_DONATIONS_TO_SHEET],
        [
            'value' => '0',
            'label' => 'Log failed donations to Google Sheet',
            'description' => 'test',
            'group' => 'integrations',
        ],
    );

    $order = createSheetTestOrder();

    $this->mock(GoogleSheetsLogger::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('logDonation');
    });

    (new LogDonationToSheetJob($order, 'failed'))->handle(app(GoogleSheetsLogger::class));
});

it('logs failed donations to google sheet when the setting is on', function () {
    Setting::query()->updateOrCreate(
        ['key' => Setting::LOG_FAILED_DONATIONS_TO_SHEET],
        [
            'value' => '1',
            'label' => 'Log failed donations to Google Sheet',
            'description' => 'test',
            'group' => 'integrations',
        ],
    );

    $order = createSheetTestOrder();

    $this->mock(GoogleSheetsLogger::class, function (MockInterface $mock) use ($order) {
        $mock->shouldReceive('logDonation')
            ->once()
            ->withArgs(fn (DonationOrder $logged, string $status) => $logged->is($order) && $status === 'failed');
    });

    (new LogDonationToSheetJob($order, 'failed'))->handle(app(GoogleSheetsLogger::class));
});

it('still logs captured donations when failed sheet logging is off', function () {
    Setting::query()->updateOrCreate(
        ['key' => Setting::LOG_FAILED_DONATIONS_TO_SHEET],
        [
            'value' => '0',
            'label' => 'Log failed donations to Google Sheet',
            'description' => 'test',
            'group' => 'integrations',
        ],
    );

    $order = createSheetTestOrder([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'sheet_logged_at' => null,
    ]);

    $this->mock(GoogleSheetsLogger::class, function (MockInterface $mock) use ($order) {
        $mock->shouldReceive('logDonation')
            ->once()
            ->withArgs(fn (DonationOrder $logged, string $status) => $logged->is($order) && $status === 'captured');
    });

    (new LogDonationToSheetJob($order, 'captured'))->handle(app(GoogleSheetsLogger::class));

    expect($order->fresh()->sheet_logged_at)->not->toBeNull();
});
