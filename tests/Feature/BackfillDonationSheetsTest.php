<?php

use App\Jobs\LogDonationToSheetJob;
use App\Models\DonationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists paid donations missing from google sheets in dry run mode', function () {
    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-logged',
        'donor_name' => 'Logged Donor',
        'donor_email' => 'logged@example.com',
        'donor_phone' => '9999999991',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'sheet_logged_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-missing',
        'donor_name' => 'Missing Donor',
        'donor_email' => 'missing@example.com',
        'donor_phone' => '9999999992',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => '548',
    ]);

    $this->artisan('donations:backfill-sheets --dry-run')
        ->expectsOutputToContain('Found 1 paid donation(s) not logged to Google Sheets.')
        ->assertSuccessful();
});

it('queues sheet logging jobs for missing paid donations', function () {
    Bus::fake();

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-missing-1',
        'donor_name' => 'Missing Donor',
        'donor_email' => 'missing@example.com',
        'donor_phone' => '9999999992',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $this->artisan('donations:backfill-sheets')
        ->expectsOutputToContain('Queued 1 donation(s) for Google Sheets logging.')
        ->assertSuccessful();

    Bus::assertDispatched(LogDonationToSheetJob::class, 1);
});

it('clears failed sheet jobs before backfilling', function () {
    Bus::fake();

    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\LogDonationToSheetJob']),
        'exception' => 'test',
        'failed_at' => now(),
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order-missing-2',
        'donor_name' => 'Missing Donor',
        'donor_email' => 'missing@example.com',
        'donor_phone' => '9999999992',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $this->artisan('donations:backfill-sheets')
        ->expectsOutputToContain('Clearing 1 failed Google Sheet job(s) before backfill.')
        ->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});
