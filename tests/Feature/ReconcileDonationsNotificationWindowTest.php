<?php

use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function createPaidOrderMissingCertificate(Cause $cause, ?\Carbon\CarbonInterface $paidAt): DonationOrder
{
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order-reconcile-'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $paidAt,
        'receipt_sent_at' => now(),
        'sheet_logged_at' => now(),
        'whatsapp_sent_at' => now(),
        'certificate_whatsapp_sent_at' => null,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
    ]);

    return $order->fresh();
}

function enableCertificateOnlyCause(): Cause
{
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_RECEIPT_EMAIL],
        ['value' => '0', 'label' => 'Receipt', 'group' => 'notifications'],
    );
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_WHATSAPP_THANK_YOU],
        ['value' => '0', 'label' => 'Thank you', 'group' => 'notifications'],
    );
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications'],
    );

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    return Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => false,
        'aisensy_send_certificate' => true,
    ]);
}

function jobOrderId(object $job): int
{
    $property = new ReflectionProperty($job, 'order');

    return (int) $property->getValue($job)->id;
}

it('requeues certificate whatsapp for a recently paid donation', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);

    $cause = enableCertificateOnlyCause();
    $recent = createPaidOrderMissingCertificate($cause, now()->subHours(12));

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertDispatched(SendCertificateWhatsAppJob::class, fn ($job) => jobOrderId($job) === $recent->id);
    Bus::assertNotDispatched(SendDonationReceiptJob::class);
    Bus::assertNotDispatched(SendThankYouWhatsAppJob::class);
});

it('does not requeue certificate whatsapp for old paid donations', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);

    $cause = enableCertificateOnlyCause();
    createPaidOrderMissingCertificate($cause, now()->subDays(10));

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertNotDispatched(SendCertificateWhatsAppJob::class);
});

it('requeues all missed notifications when the all-missed-notifications flag is used', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);

    $cause = enableCertificateOnlyCause();
    $old = createPaidOrderMissingCertificate($cause, now()->subDays(10));

    $this->artisan('donations:reconcile', ['--all-missed-notifications' => true])->assertSuccessful();

    Bus::assertDispatched(SendCertificateWhatsAppJob::class, fn ($job) => jobOrderId($job) === $old->id);
});
