<?php

use App\Jobs\SendPaymentLinkWhatsAppJob;
use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Services\AiSensyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function createNudgeLoggingOrder(array $overrides = []): DonationOrder
{
    $createdAt = $overrides['created_at'] ?? now()->subMinutes(20);
    unset($overrides['created_at'], $overrides['updated_at']);

    $order = DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_nudge_log_'.fake()->unique()->numerify('#####'),
        'donor_name' => 'Nudge Log Donor',
        'donor_email' => 'nudge-log@example.com',
        'donor_phone' => '9898237948',
        'currency' => 'INR',
        'total_amount' => 1100,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now()->subMinutes(20),
    ], $overrides));

    $order->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $order->fresh();
}

it('does not send payment-link whatsapp when the order is already paid', function () {
    Http::fake();

    $order = createNudgeLoggingOrder([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'payment_link_url' => 'https://rzp.io/i/paid',
    ]);

    (new SendPaymentLinkWhatsAppJob($order->id, true))->handle();

    expect($order->refresh()->payment_link_sent_at)->toBeNull();
    Http::assertNothingSent();
});

it('logs aisensy details when payment-fail whatsapp is rejected', function () {
    Log::spy();
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response([
            'message' => 'Template params does not match the campaign',
        ], 400),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Recovery Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'slug' => 'tree-plantation',
        'aisensy_account_id' => $account->id,
        'aisensy_payment_link_campaign' => 'payment_failed_retry_payment',
    ]);

    $order = createNudgeLoggingOrder([
        'donor_name' => 'Sandip Sabaliya',
        'payment_link_id' => 'plink_mismatch',
        'payment_link_url' => 'https://rzp.io/i/mismatch',
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Tree Plantation',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
    ]);

    $sent = app(AiSensyService::class)->sendPaymentLinkWhatsApp($order->fresh());

    expect($sent)->toBeFalse();

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
        return $message === 'AiSensy WhatsApp payment_link failed'
            && ($context['campaign_name'] ?? null) === 'payment_failed_retry_payment'
            && ($context['http_status'] ?? null) === 400
            && ($context['aisensy_message'] ?? null) === 'Template params does not match the campaign'
            && ($context['template_param_count'] ?? null) === 4
            && ($context['payload']['apiKey'] ?? null) === '[redacted]';
    });
});

it('logs missing aisensy payment-link campaign when cause and env fallback are empty', function () {
    config(['services.aisensy.default.payment_link_campaign' => null]);

    Log::spy();
    Http::fake();

    $account = AisensyAccount::create([
        'name' => 'Recovery Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'slug' => 'old-age-home',
        'aisensy_account_id' => $account->id,
        'aisensy_payment_link_campaign' => null,
    ]);

    $order = createNudgeLoggingOrder([
        'payment_link_url' => 'https://rzp.io/i/no-campaign',
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Old Age Home',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
    ]);

    $sent = app(AiSensyService::class)->sendPaymentLinkWhatsApp($order->fresh());

    expect($sent)->toBeFalse();

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($order, $cause): bool {
        return $message === 'AiSensy config missing (payment link)'
            && ($context['order_id'] ?? null) === $order->id
            && ($context['cause_id'] ?? null) === $cause->id
            && ($context['has_campaign'] ?? null) === false
            && ($context['has_api_key'] ?? null) === true;
    });

    Http::assertNothingSent();
});

it('falls back to env payment-link campaign when cause campaign is empty', function () {
    config(['services.aisensy.default.payment_link_campaign' => 'payment_failed_retry_payment']);

    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['success' => 'true'], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Recovery Account',
        'api_key' => 'test-api-key',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $cause = Cause::factory()->create([
        'slug' => 'dog-shelter',
        'aisensy_account_id' => $account->id,
        'aisensy_payment_link_campaign' => null,
    ]);

    $order = createNudgeLoggingOrder([
        'payment_link_url' => 'https://rzp.io/i/fallback',
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Dog Shelter',
        'quantity' => 1,
        'unit_amount' => 1100,
        'amount' => 1100,
    ]);

    expect(app(AiSensyService::class)->sendPaymentLinkWhatsApp($order->fresh()))->toBeTrue();

    Http::assertSent(fn ($request) => ($request->data()['campaignName'] ?? null) === 'payment_failed_retry_payment');
});
