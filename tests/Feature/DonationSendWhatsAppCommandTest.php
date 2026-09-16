<?php

use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createCommandTestOrder(Cause $cause): DonationOrder
{
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_cmd_'.fake()->unique()->randomNumber(),
        'provider_payment_id' => 'pay_CMD'.fake()->unique()->randomNumber(),
        'donor_name' => 'Monil Vekariya',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 10,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Donation',
        'quantity' => 1,
        'unit_amount' => 10,
        'amount' => 10,
    ]);

    return $order->fresh();
}

it('generates certificate and sends both whatsapp messages when enabled', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('certificate-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    config()->set('donation.certificate.enabled', true);

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
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_certificate_campaign' => 'certificate-campaign',
        'aisensy_send_thank_you' => true,
        'aisensy_send_certificate' => true,
    ]);

    $order = createCommandTestOrder($cause);

    $this->artisan('donations:send-whatsapp', [
        'order' => $order->provider_payment_id,
        '--force' => true,
    ])->assertSuccessful();

    $order->refresh();

    expect($order->whatsapp_sent_at)->not->toBeNull()
        ->and($order->certificate_whatsapp_sent_at)->not->toBeNull();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://backend.aisensy.com/campaign/t1/api/v2'
            && ($request->data()['campaignName'] ?? null) === 'thank-you-campaign';
    });

    Http::assertSent(function (Request $request) use ($order): bool {
        if ($request->url() !== 'https://backend.aisensy.com/campaign/t1/api/v2') {
            return false;
        }

        $data = $request->data();
        $mediaUrl = (string) ($data['media']['url'] ?? '');

        return ($data['campaignName'] ?? null) === 'certificate-campaign'
            && (
                str_contains($mediaUrl, 'certificates/sanman-'.$order->id.'-whatsapp.jpg')
                || str_contains($mediaUrl, 'certificates/sanman-'.$order->id.'.png')
            );
    });
});

it('skips thank you when disabled on the cause', function () {
    Http::fake([
        'https://backend.aisensy.com/*' => Http::response(['status' => 'ok'], 200),
        '*' => Http::response('certificate-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    config()->set('donation.certificate.enabled', true);

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
        'aisensy_thank_you_campaign' => 'thank-you-campaign',
        'aisensy_certificate_campaign' => 'certificate-campaign',
        'aisensy_send_thank_you' => false,
        'aisensy_send_certificate' => true,
    ]);

    $order = createCommandTestOrder($cause);

    $this->artisan('donations:send-whatsapp', [
        'order' => (string) $order->id,
        '--force' => true,
    ])->assertSuccessful();

    $order->refresh();

    expect($order->whatsapp_sent_at)->toBeNull()
        ->and($order->certificate_whatsapp_sent_at)->not->toBeNull();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://backend.aisensy.com/campaign/t1/api/v2'
            && ($request->data()['campaignName'] ?? null) === 'certificate-campaign';
    });
});
