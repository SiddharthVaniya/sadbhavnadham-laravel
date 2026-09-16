<?php

use App\Jobs\SendThankYouWhatsAppJob;
use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\AiSensyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('uses thank you campaign for thank you whatsapp flow', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_payment_link_campaign' => 'payment-link-campaign',
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_123',
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

    $sent = app(AiSensyService::class)->sendThankYouWhatsApp($order->fresh());

    expect($sent)->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $request->url() === 'https://backend.aisensy.com/campaign/t1/api/v2'
            && ($data['campaignName'] ?? null) === 'thank-you-campaign'
            && ($data['campaignName'] ?? null) !== 'payment-link-campaign'
            && ! array_key_exists('templateParams', $data);
    });
});

it('does not mark thank you whatsapp sent when aisensy rejects the request', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'failed'], 500),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_failed_whatsapp',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor-failed@example.com',
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

    try {
        app(SendThankYouWhatsAppJob::class, [
            'order' => $order,
        ])->handle(app(AiSensyService::class), app(\App\Services\DonationWhatsAppPolicy::class));
    } catch (RuntimeException) {
        expect($order->refresh()->whatsapp_sent_at)->toBeNull();

        return;
    }

    $this->fail('Expected the WhatsApp job to throw when AiSensy rejects the request.');
});

it('redacts aisensy secrets from failure logs', function () {
    Log::spy();
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'failed'], 500),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'secret-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_redacted_log',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor-log@example.com',
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

    app(AiSensyService::class)->sendThankYouWhatsApp($order);

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
        return str_contains($message, 'AiSensy WhatsApp thank_you failed')
            && ($context['payload']['apiKey'] ?? null) === '[redacted]'
            && ($context['payload']['destination'] ?? null) === '[redacted]'
            && ($context['payload']['templateParams'] ?? null) !== ['Test Donor'];
    });
});

it('builds thank you message from cause template placeholders', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'title' => 'Meals',
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_thank_you_message_mode' => 'template',
        'aisensy_thank_you_message_template' => 'Hi {name}, thanks for Rs {amount} for {cause}. Receipt {receipt_number}',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_template_message',
        'donor_name' => 'Asha',
        'donor_email' => 'asha@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 750,
        'receipt_number' => '1234',
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 750,
        'amount' => 750,
    ]);

    app(AiSensyService::class)->sendThankYouWhatsApp($order->fresh());

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();
        $message = (string) ($data['templateParams'][0] ?? '');

        return str_contains($message, 'Hi Asha')
            && str_contains($message, 'Rs 750')
            && str_contains($message, 'Meals')
            && str_contains($message, 'MSCT-RZP-1234');
    });
});

it('builds thank you message using builder toggles only', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'title' => 'Education',
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_thank_you_message_mode' => 'builder',
        'aisensy_thank_you_include_name' => false,
        'aisensy_thank_you_include_amount' => true,
        'aisensy_thank_you_include_cause' => false,
        'aisensy_thank_you_include_receipt' => false,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_builder_message',
        'donor_name' => 'Builder Donor',
        'donor_email' => 'builder@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1200,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 1200,
        'amount' => 1200,
    ]);

    app(AiSensyService::class)->sendThankYouWhatsApp($order->fresh());

    Http::assertSent(function (Request $request): bool {
        $message = (string) (($request->data()['templateParams'][0] ?? ''));

        return str_contains($message, 'Thank you')
            && str_contains($message, 'Rs 1200')
            && ! str_contains($message, 'Builder Donor')
            && ! str_contains($message, 'Education');
    });
});

it('uses aisensy template default when configured template is blank', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'title' => 'Medical Support',
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_thank_you_message_mode' => 'template',
        'aisensy_thank_you_message_template' => '   ',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_blank_template',
        'donor_name' => 'Fallback Donor',
        'donor_email' => 'fallback@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    app(AiSensyService::class)->sendThankYouWhatsApp($order->fresh());

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return ! array_key_exists('templateParams', $data);
    });
});

it('sends generated certificate media url in a separate certificate whatsapp payload', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('certificate-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    config()->set('donation.certificate.enabled', true);

    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => '1', 'label' => 'Send Donation Certificate on WhatsApp', 'group' => 'notifications'],
    );

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_certificate_campaign' => 'certificate-campaign',
        'aisensy_thank_you_image' => 'images/static-thank-you.png',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_certificate_media',
        'donor_name' => 'વિજયભાઈ ડોબરીયા',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
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

    app(AiSensyService::class)->sendCertificateWhatsApp($order->fresh());

    Http::assertSent(function (Request $request) use ($order): bool {
        $data = $request->data();
        $mediaUrl = (string) ($data['media']['url'] ?? '');

        return ($data['campaignName'] ?? null) === 'certificate-campaign'
            && (
                str_contains($mediaUrl, 'certificates/sanman-'.$order->id.'-whatsapp.jpg')
                || str_contains($mediaUrl, 'certificates/sanman-'.$order->id.'.png')
            );
    });
});

it('does not attach certificate media to thank you whatsapp payload', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('certificate-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    config()->set('donation.certificate.enabled', true);

    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => '1', 'label' => 'Send Donation Certificate on WhatsApp', 'group' => 'notifications'],
    );

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_certificate_campaign' => 'certificate-campaign',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_thank_you_without_media',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
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

    app(AiSensyService::class)->sendThankYouWhatsApp($order->fresh());

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return ($data['campaignName'] ?? null) === 'thank-you-campaign'
            && ! array_key_exists('media', $data);
    });
});

it('prefers the otp aisensy account key over a stale env otp key', function () {
    config([
        'services.aisensy.otp.key' => 'stale-env-otp-key',
        'services.aisensy.otp.campaign' => 'login_otp',
    ]);

    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'OTP Account',
        'api_key' => 'account-otp-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    expect(app(AiSensyService::class)->sendOtpCampaign(
        '9876543210',
        'Test Donor',
        '123456',
        $account,
    ))->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return ($data['apiKey'] ?? null) === 'account-otp-key'
            && ($data['campaignName'] ?? null) === 'login_otp';
    });
});

it('sends payment-fail whatsapp with four template params matching payment_failed_retry_payment', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['success' => 'true'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Default Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'aisensy_account_id' => $account->id,
        'aisensy_payment_link_campaign' => 'payment_failed_retry_payment',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_TXrzm4D8yGHwbk',
        'donor_name' => 'JIGAR FALDU',
        'donor_email' => 'jigar@example.com',
        'donor_phone' => '9426025598',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
        'payment_link_id' => 'plink_retry',
        'payment_link_url' => 'https://rzp.io/i/retry-link',
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
    ]);

    $sent = app(AiSensyService::class)->sendPaymentLinkWhatsApp($order->fresh());

    expect($sent)->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();
        $params = $data['templateParams'] ?? null;

        return ($data['campaignName'] ?? null) === 'payment_failed_retry_payment'
            && ($data['destination'] ?? null) === '919426025598'
            && is_array($params)
            && count($params) === 4
            && $params[0] === 'JIGAR FALDU'
            && $params[1] === '#order_TXrzm4D8yGHwbk'
            && $params[2] === '1100'
            && $params[3] === 'https://rzp.io/i/retry-link';
    });
});
