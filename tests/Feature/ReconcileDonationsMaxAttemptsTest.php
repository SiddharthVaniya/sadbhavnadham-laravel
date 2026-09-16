<?php

use App\Jobs\SendCertificateWhatsAppJob;
use App\Jobs\SendDonationReceiptJob;
use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Support\DonationNotificationRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function maxAttemptsCause(bool $thankYou = false, bool $certificate = true): Cause
{
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_RECEIPT_EMAIL],
        ['value' => '1', 'label' => 'Receipt', 'group' => 'notifications'],
    );
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_WHATSAPP_THANK_YOU],
        ['value' => $thankYou ? '1' : '0', 'label' => 'Thank you', 'group' => 'notifications'],
    );
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => $certificate ? '1' : '0', 'label' => 'Certificate', 'group' => 'notifications'],
    );

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    return Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => $thankYou,
        'aisensy_send_certificate' => $certificate,
    ]);
}

function createPaidOrderForMaxAttempts(Cause $cause, array $overrides = []): DonationOrder
{
    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order-max-'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subHour(),
        'receipt_sent_at' => now(),
        'sheet_logged_at' => now(),
        'whatsapp_sent_at' => now(),
        'certificate_whatsapp_sent_at' => null,
    ], $overrides));

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

function maxAttemptsJobOrderId(object $job): int
{
    $property = new ReflectionProperty($job, 'order');

    return (int) $property->getValue($job)->id;
}

it('stops requeueing certificate whatsapp after max reconcile attempts', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->update(['value' => '2']);

    $cause = maxAttemptsCause(thankYou: false, certificate: true);
    $order = createPaidOrderForMaxAttempts($cause, [
        'certificate_whatsapp_notify_attempts' => 2,
    ]);

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertNotDispatched(SendCertificateWhatsAppJob::class);
    expect($order->fresh()->certificate_whatsapp_notify_attempts)->toBe(2);
});

it('increments attempt counter when requeueing a missed notification', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->update(['value' => '5']);

    $cause = maxAttemptsCause(thankYou: false, certificate: true);
    $order = createPaidOrderForMaxAttempts($cause, [
        'certificate_whatsapp_notify_attempts' => 1,
    ]);

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertDispatched(SendCertificateWhatsAppJob::class, fn ($job) => maxAttemptsJobOrderId($job) === $order->id);
    expect($order->fresh()->certificate_whatsapp_notify_attempts)->toBe(2);
});

it('marks receipt exhausted when donor email is missing and does not requeue forever', function () {
    Bus::fake();
    Mail::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->update(['value' => '5']);

    $cause = maxAttemptsCause(thankYou: false, certificate: false);
    $order = createPaidOrderForMaxAttempts($cause, [
        'donor_email' => '',
        'receipt_sent_at' => null,
        'certificate_whatsapp_sent_at' => now(),
    ]);

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertNotDispatched(SendDonationReceiptJob::class);

    $order->refresh();
    expect($order->receipt_notify_attempts)->toBe(5)
        ->and($order->receipt_failed_at)->not->toBeNull()
        ->and($order->receipt_last_error)->toBe('No donor email on order');

    $this->artisan('donations:reconcile')->assertSuccessful();
    Bus::assertNotDispatched(SendDonationReceiptJob::class);
});

it('marks receipt exhausted from the job when email is missing', function () {
    Mail::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->update(['value' => '5']);

    $cause = maxAttemptsCause(thankYou: false, certificate: false);
    $order = createPaidOrderForMaxAttempts($cause, [
        'donor_email' => '',
        'receipt_sent_at' => null,
        'certificate_whatsapp_sent_at' => now(),
    ]);

    (new SendDonationReceiptJob($order))->handle();

    $order->refresh();
    expect($order->receipt_sent_at)->toBeNull()
        ->and($order->receipt_notify_attempts)->toBe(DonationNotificationRetry::maxReconcileAttempts())
        ->and($order->receipt_last_error)->toBe('No donor email on order');
});

it('stops requeueing thank-you whatsapp after max reconcile attempts', function () {
    Bus::fake();
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_RETRY_HOURS)->update(['value' => '48']);
    Setting::query()->where('key', Setting::RECONCILE_NOTIFICATION_MAX_ATTEMPTS)->update(['value' => '3']);

    $cause = maxAttemptsCause(thankYou: true, certificate: false);
    $order = createPaidOrderForMaxAttempts($cause, [
        'whatsapp_sent_at' => null,
        'certificate_whatsapp_sent_at' => now(),
        'whatsapp_notify_attempts' => 3,
    ]);

    $this->artisan('donations:reconcile')->assertSuccessful();

    Bus::assertNotDispatched(SendThankYouWhatsAppJob::class);
    expect($order->fresh()->whatsapp_notify_attempts)->toBe(3);
});
