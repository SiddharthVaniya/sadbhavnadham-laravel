<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Support\DonationThankYouUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds receipt json for a paid donation without serving a laravel html page', function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'app.url' => 'https://admin.sadbhavnadham.org',
    ]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Old Age Home Support',
        'slug' => 'old-age-home',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_thank_you_1',
        'donor_name' => 'Ravi Shah',
        'donor_email' => 'ravi@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'receipt_number' => 10492,
        'utm_campaign' => 'Ashvini | 10/08 | Sadbhavna | Old Age Home',
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->slug,
        'title' => 'Old Age Home Support',
        'quantity' => 1,
        'unit_amount' => 500,
        'amount' => 500,
    ]);

    $payload = \App\Support\DonationThankYouData::forOrder($order);

    expect($payload['headline'])->toBe('Thank you for your donation')
        ->and($payload['donor_name'])->toBe('Ravi Shah')
        ->and($payload['cause_title'])->toBe('Old Age Home Support')
        ->and($payload['amount_label'])->toBe('₹500.00')
        ->and($payload['receipt_number'])->toBe('MSCT-RZP-10492')
        ->and($payload['ecommerce']['transaction_id'])->toBe($order->order_uuid)
        ->and($payload['ecommerce']['value'])->toEqual(500)
        ->and($payload['ecommerce']['currency'])->toBe('INR')
        ->and($payload['ecommerce']['items'][0]['item_id'])->toBe('old-age-home')
        ->and($payload['ecommerce']['items'][0]['item_name'])->toBe('Old Age Home Support')
        ->and($payload['utm_campaign'])->toBe('Ashvini | 10/08 | Sadbhavna | Old Age Home');

    $response = $this->get(DonationThankYouUrl::forLaravelPage($order));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/thank-you/'.$order->order_uuid)
        ->and((string) $response->headers->get('Location'))
        ->toContain('signature=');
});

it('redirects a signed laravel subscription thank-you page to nextjs', function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'app.url' => 'https://admin.sadbhavnadham.org',
    ]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
    ]);

    $subscription = DonationSubscription::factory()->create([
        'cause_id' => $cause->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_amount' => 300,
        'total_amount' => 300,
        'currency' => 'INR',
        'item_title' => 'Monthly Tree Support',
        'status' => DonationSubscription::STATUS_CREATED,
        'donor_name' => 'Meera Desai',
        'donor_email' => 'meera@example.com',
        'donor_phone' => '9988776655',
        'consent_indian_citizen' => true,
        'consent_recurring' => true,
    ]);

    $response = $this->get(DonationThankYouUrl::forLaravelSubscriptionPage($subscription));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/thank-you/subscription/'.$subscription->subscription_uuid);
});

it('redirects pending laravel thank-you urls to the nextjs receipt', function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'app.url' => 'https://admin.sadbhavnadham.org',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_thank_you_pending',
        'donor_name' => 'Pending Donor',
        'donor_email' => 'pending@example.com',
        'donor_phone' => '9000000000',
        'currency' => 'INR',
        'total_amount' => 251,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    $response = $this->get(DonationThankYouUrl::forLaravelPage($order));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/thank-you/'.$order->order_uuid);
});

it('rejects unsigned thank-you urls', function () {
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_thank_you_unsigned',
        'donor_name' => 'Secret Donor',
        'donor_email' => 'secret@example.com',
        'donor_phone' => '9111111111',
        'currency' => 'INR',
        'total_amount' => 100,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $this->get(route('donate.thank-you', $order))
        ->assertForbidden();
});

it('builds checkout thank-you urls on the nextjs origin with an api signature', function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'app.url' => 'https://admin.sadbhavnadham.org',
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_name' => 'Frontend Donor',
        'donor_email' => 'front@example.com',
        'donor_phone' => '9000000001',
        'currency' => 'INR',
        'total_amount' => 10,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
        'utm_source' => 'meta',
        'utm_medium' => 'Instagram_Reels',
        'utm_campaign' => 'Ashvini | 18/08 | Sadbhavna | Billi Patra Tree',
        'utm_content' => 'Ashvini | 18/08 | Sadbhavna | Billi Patra Tree',
        'partner_code' => 'xvjsrg',
        'meta_campaign_id' => '120252297019310236',
        'meta_adset_id' => '120252297019330236',
        'utm_term' => '120252297019330236',
    ]);

    $url = DonationThankYouUrl::forOrder($order);

    expect($url)->toStartWith('https://sadbhavnadham.org/donate/thank-you/'.$order->order_uuid)
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=')
        ->and($url)->toContain('utm_source=meta')
        ->and($url)->toContain('utm_campaign=')
        ->and($url)->toContain('sid=xvjsrg');
});

it('redirects a signed laravel thank-you page to the nextjs receipt', function () {
    config([
        'donation.public_frontend_url' => 'https://sadbhavnadham.org',
        'donation.redirect_public_donate_to_frontend' => true,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_name' => 'Redirect Donor',
        'donor_email' => 'redirect@example.com',
        'donor_phone' => '9000000002',
        'currency' => 'INR',
        'total_amount' => 10,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $response = $this->get(DonationThankYouUrl::forLaravelPage($order));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith('https://sadbhavnadham.org/donate/thank-you/'.$order->order_uuid);
});
