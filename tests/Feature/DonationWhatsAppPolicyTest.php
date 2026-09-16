<?php

use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPaidOrderForCause(Cause $cause): DonationOrder
{
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order-policy-'.$cause->id.'-'.fake()->unique()->randomNumber(),
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
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

it('allows thank you only when enabled globally and on the cause', function () {
    Setting::query()->updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1', 'label' => 'Thank you', 'group' => 'notifications']);
    Setting::query()->updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications']);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => true,
        'aisensy_send_certificate' => false,
    ]);

    $policy = app(DonationWhatsAppPolicy::class);
    $order = createPaidOrderForCause($cause);

    expect($policy->shouldSendThankYou($order))->toBeTrue()
        ->and($policy->shouldSendCertificate($order))->toBeFalse();
});

it('allows certificate only when enabled globally and on the cause', function () {
    Setting::query()->updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1', 'label' => 'Thank you', 'group' => 'notifications']);
    Setting::query()->updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications']);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => false,
        'aisensy_send_certificate' => true,
    ]);

    $policy = app(DonationWhatsAppPolicy::class);
    $order = createPaidOrderForCause($cause);

    expect($policy->shouldSendThankYou($order))->toBeFalse()
        ->and($policy->shouldSendCertificate($order))->toBeTrue();
});

it('allows both messages when enabled globally and on the cause', function () {
    Setting::query()->updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1', 'label' => 'Thank you', 'group' => 'notifications']);
    Setting::query()->updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications']);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => true,
        'aisensy_send_certificate' => true,
    ]);

    $policy = app(DonationWhatsAppPolicy::class);
    $order = createPaidOrderForCause($cause);

    expect($policy->shouldSendThankYou($order))->toBeTrue()
        ->and($policy->shouldSendCertificate($order))->toBeTrue();
});

it('blocks messages for placeholder phones and razorpay qr orders', function () {
    Setting::query()->updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1', 'label' => 'Thank you', 'group' => 'notifications']);
    Setting::query()->updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications']);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => true,
        'aisensy_send_certificate' => true,
    ]);

    $policy = app(DonationWhatsAppPolicy::class);

    $placeholderPhoneOrder = createPaidOrderForCause($cause);
    $placeholderPhoneOrder->update(['donor_phone' => 'upi-a1b2c3d4e5']);

    $qrOrder = createPaidOrderForCause($cause);
    $qrOrder->update([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
        'donor_phone' => '9876543210',
    ]);

    expect($policy->shouldSendThankYou($placeholderPhoneOrder->fresh()))->toBeFalse()
        ->and($policy->shouldSendCertificate($placeholderPhoneOrder->fresh()))->toBeFalse()
        ->and($policy->shouldSendThankYou($qrOrder->fresh()))->toBeFalse()
        ->and($policy->shouldSendCertificate($qrOrder->fresh()))->toBeFalse();
});

it('blocks messages when disabled on the cause even if global settings are on', function () {
    Setting::query()->updateOrCreate(['key' => Setting::SEND_WHATSAPP_THANK_YOU], ['value' => '1', 'label' => 'Thank you', 'group' => 'notifications']);
    Setting::query()->updateOrCreate(['key' => Setting::SEND_DONATION_CERTIFICATE], ['value' => '1', 'label' => 'Certificate', 'group' => 'notifications']);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_send_thank_you' => false,
        'aisensy_send_certificate' => false,
    ]);

    $policy = app(DonationWhatsAppPolicy::class);
    $order = createPaidOrderForCause($cause);

    expect($policy->shouldSendThankYou($order))->toBeFalse()
        ->and($policy->shouldSendCertificate($order))->toBeFalse();
});
