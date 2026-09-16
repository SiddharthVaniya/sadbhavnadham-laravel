<?php

use App\Mail\DonorOtpMail;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\LinkTrackingSummary;
use App\Models\LinkTrackingVisit;
use App\Support\DonationThankYouUrl;
use App\Support\PublicMediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function donateApiCause(array $overrides = []): Cause
{
    return Cause::factory()->create(array_merge([
        'slug' => 'old-age-home',
        'title' => 'Old Age Home',
        'excerpt' => 'Care for elders',
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
        'default_amount' => 100,
        'default_title' => 'General Donation',
    ], $overrides));
}

it('lists active causes as json', function () {
    $active = donateApiCause([
        'hero_image' => 'storage/causes/hero/test.jpg',
    ]);
    CausePackage::factory()->for($active)->create([
        'title' => 'Bhojan Seva',
        'amount' => 100,
        'is_active' => true,
        'is_default' => true,
        'image' => 'storage/causes/packages/meal.jpg',
    ]);
    Cause::factory()->create([
        'slug' => 'hidden-cause',
        'is_active' => false,
    ]);

    $this->getJson('/api/donate/causes')
        ->assertOk()
        ->assertJsonCount(1, 'causes')
        ->assertJsonPath('causes.0.slug', 'old-age-home')
        ->assertJsonPath('causes.0.title', 'Old Age Home')
        ->assertJsonPath('causes.0.hero_image', PublicMediaUrl::fromStoredPath('storage/causes/hero/test.jpg'))
        ->assertJsonPath('causes.0.packages.0.image', PublicMediaUrl::fromStoredPath('storage/causes/packages/meal.jpg'));
});

it('returns a cause by slug with packages and defaults', function () {
    $cause = donateApiCause(['default_amount' => 250]);
    $package = CausePackage::factory()->for($cause)->create([
        'title' => 'Bhojan Seva',
        'amount' => 100,
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $this->getJson('/api/donate/causes/old-age-home')
        ->assertOk()
        ->assertJsonPath('cause.slug', 'old-age-home')
        ->assertJsonPath('cause.packages.0.title', 'Bhojan Seva')
        ->assertJsonPath('default_package_id', $package->id)
        ->assertJsonPath('default_amount', 100);
});

it('returns 404 for an unknown or inactive cause slug', function () {
    donateApiCause(['is_active' => false]);

    $this->getJson('/api/donate/causes/old-age-home')->assertNotFound();
    $this->getJson('/api/donate/causes/missing')->assertNotFound();
});

it('returns public razorpay config without secrets', function () {
    config([
        'payments.razorpay.key' => 'rzp_test_public',
        'payments.razorpay.secret' => 'super-secret',
        'payments.razorpay.subscriptions_enabled' => true,
        'payments.razorpay.subscription_min_amount' => 10,
        'donation.pan_threshold_inr' => 100000,
    ]);

    $this->getJson('/api/donate/config')
        ->assertOk()
        ->assertJsonPath('razorpay_key', 'rzp_test_public')
        ->assertJsonPath('subscriptions_enabled', true)
        ->assertJsonPath('subscription_min_amount', 10)
        ->assertJsonPath('pan_threshold', 100000)
        ->assertJsonPath('media.origin', PublicMediaUrl::origin())
        ->assertJsonPath('payment_logos', PublicMediaUrl::fromStoredPath('/images/payments/payment-logos.png'))
        ->assertJsonMissingPath('payments.razorpay.secret')
        ->assertJsonMissing(['razorpay_secret' => 'super-secret']);
});

it('looks up a 6-digit pincode', function () {
    Http::fake([
        'api.postalpincode.in/*' => Http::response([[
            'Status' => 'Success',
            'PostOffice' => [[
                'Name' => 'Rajkot',
                'District' => 'Rajkot',
                'State' => 'Gujarat',
            ]],
        ]]),
    ]);

    $this->getJson('/api/donate/pincode/360001')
        ->assertOk()
        ->assertJson([
            'found' => true,
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'country' => 'INDIA',
        ]);
});

it('rejects invalid pincodes and missing pincodes', function () {
    $this->getJson('/api/donate/pincode/12')->assertStatus(422);

    Http::fake([
        'api.postalpincode.in/*' => Http::response([['Status' => 'Error', 'PostOffice' => null]]),
        'api.zippopotam.us/*' => Http::response(null, 404),
    ]);

    $this->getJson('/api/donate/pincode/000000')
        ->assertNotFound()
        ->assertJsonPath('found', false);
});

it('returns bank details from branding', function () {
    $this->getJson('/api/donate/bank-details')
        ->assertOk()
        ->assertJsonStructure([
            'account_name',
            'account_number',
            'ifsc',
            'bank_name',
            'upi_id',
            'contact',
        ]);
});

it('evaluates pan requirement without a wordpress token', function () {
    donateApiCause(['pan_required' => true, 'allow_custom_amount' => true]);

    $this->postJson('/api/donate/pan-requirement', [
        'cause' => 'old-age-home',
        'amount' => 100000,
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJsonPath('required', true)
        ->assertJsonPath('threshold', 100000);
});

it('rejects checkout without the wordpress token', function () {
    donateApiCause();

    $this->postJson('/api/donate/checkout', [
        'cause' => 'old-age-home',
        'amount' => 100,
    ])->assertForbidden();
});

it('rejects subscription checkout without the wordpress token', function () {
    donateApiCause(['allow_recurring' => true]);

    $this->postJson('/api/donate/checkout/subscription', [
        'cause' => 'old-age-home',
        'amount' => 100,
        'frequency' => 'monthly',
        'consent_recurring' => true,
    ])->assertForbidden();
});

it('rejects wordpress subscription checkout without a token', function () {
    donateApiCause(['allow_recurring' => true]);

    $this->postJson('/api/wp-razorpay/subscription', [
        'cause' => 'old-age-home',
        'amount' => 100,
    ])->assertForbidden();
});

it('records a unique tracking visit and ignores duplicates', function () {
    $visitorId = (string) Str::uuid();

    $first = $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'amt' => '100',
        'landing_url' => 'https://sadbhavnadham.org/donate/old-age-home?sid=zmupe',
        'page_path' => '/donate/old-age-home',
        'referrer' => 'https://www.facebook.com/',
    ])->assertOk();

    $first->assertJsonPath('recorded', true)
        ->assertJsonPath('unique', true)
        ->assertJsonPath('sid', 'zmupe')
        ->assertJsonPath('page_path', '/donate/old-age-home');

    expect(LinkTrackingVisit::query()->count())->toBe(1)
        ->and(LinkTrackingSummary::query()->where('sid', 'zmupe')->value('unique_visitors'))->toBe(1);

    $second = $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'landing_url' => 'https://sadbhavnadham.org/donate/old-age-home?sid=zmupe',
        'page_path' => '/donate/old-age-home',
    ])->assertOk();

    $second->assertJsonPath('recorded', true)
        ->assertJsonPath('unique', false)
        ->assertJsonPath('visit_id', $first->json('visit_id'));

    expect(LinkTrackingVisit::query()->count())->toBe(1)
        ->and(LinkTrackingSummary::query()->where('sid', 'zmupe')->value('unique_visitors'))->toBe(1);
});

it('stores mobile device type from the visitor user agent', function () {
    $this->withHeader(
        'User-Agent',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    )->postJson('/api/donate/track', [
        'visitor_id' => (string) Str::uuid(),
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'page_path' => '/donate/old-age-home',
    ])->assertOk();

    expect(LinkTrackingVisit::query()->value('device_type'))->toBe('mobile');
});

it('prefers the browser user agent posted in the tracking payload over the nextjs server agent', function () {
    $this->withHeader('User-Agent', 'node')
        ->postJson('/api/donate/track', [
            'visitor_id' => (string) Str::uuid(),
            'sid' => 'zmupe',
            'utm_source' => 'meta',
            'page_path' => '/donate/old-age-home',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'device_type' => 'mobile',
        ])->assertOk();

    $visit = LinkTrackingVisit::query()->first();

    expect($visit?->device_type)->toBe('mobile')
        ->and($visit?->user_agent)->toContain('Android');
});

it('does not treat the nextjs node user agent as desktop', function () {
    $this->withHeader('User-Agent', 'node')
        ->postJson('/api/donate/track', [
            'visitor_id' => (string) Str::uuid(),
            'sid' => 'zmupe',
            'utm_source' => 'meta',
            'page_path' => '/donate/old-age-home',
        ])->assertOk();

    expect(LinkTrackingVisit::query()->value('device_type'))->toBe('unknown');
});

it('records a new row when the same visitor opens another page', function () {
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'utm_campaign' => 'Pritesh - 1908 Old age home',
        'landing_url' => 'https://sadbhavnadham.org/donate/old-age-home?utm_source=meta',
        'page_path' => '/donate/old-age-home',
    ])->assertOk()->assertJsonPath('unique', true);

    $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'utm_campaign' => 'Pritesh - 1908 Old age home',
        'landing_url' => 'https://sadbhavnadham.org/know-us',
        'page_path' => '/know-us',
    ])->assertOk()->assertJsonPath('unique', false)->assertJsonPath('page_path', '/know-us');

    expect(LinkTrackingVisit::query()->count())->toBe(2)
        ->and(LinkTrackingVisit::query()->where('is_unique', true)->count())->toBe(1)
        ->and(LinkTrackingSummary::query()->where('sid', 'zmupe')->value('unique_visitors'))->toBe(1)
        ->and(LinkTrackingSummary::query()->where('sid', 'zmupe')->value('total_clicks'))->toBe(2);
});

it('does not record a visit without campaign params', function () {
    $this->postJson('/api/donate/track', [
        'visitor_id' => (string) Str::uuid(),
    ])
        ->assertOk()
        ->assertJson([
            'recorded' => false,
            'reason' => 'no_tracking_params',
        ]);

    expect(LinkTrackingVisit::query()->count())->toBe(0);
});

it('requires a uuid visitor_id for tracking', function () {
    $this->postJson('/api/donate/track', [
        'visitor_id' => 'not-a-uuid',
        'sid' => 'zmupe',
    ])->assertStatus(422);
});

it('attaches a tracked visit when checkout starts and marks conversion on payment', function () {
    $cause = donateApiCause();
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
        'utm_source' => 'meta',
        'amt' => '500',
    ])->assertOk();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_track_1',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    app(\App\Services\LinkTrackingService::class)->attachToOrder($order, [
        'visitor_id' => $visitorId,
        'sid' => 'zmupe',
    ]);

    $visit = LinkTrackingVisit::query()->first();
    expect($visit->donation_order_id)->toBe($order->id);

    $order->update([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    app(\App\Services\LinkTrackingService::class)->markConverted($order->fresh());

    $visit->refresh();
    $summary = LinkTrackingSummary::query()->where('sid', 'zmupe')->first();

    expect($visit->converted)->toBeTrue()
        ->and((float) $visit->converted_amount)->toBe(500.0)
        ->and($summary->total_donations)->toBe(1)
        ->and((float) $summary->total_amount)->toBe(500.0);
});

it('creates a visit from checkout cookies when the landing track was missed', function () {
    $visitorId = (string) Str::uuid();

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_track_missed',
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 3000,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    expect(LinkTrackingVisit::query()->count())->toBe(0);

    $visit = app(\App\Services\LinkTrackingService::class)->attachToOrder($order, [
        'visitor_id' => $visitorId,
        'sid' => 'xvjsrg',
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Desktop_Feed',
        'utm_campaign' => '22/08 | Sadbhavna | Tree Plantation',
        'amt' => '3000',
        'landing_url' => 'https://sadbhavnadham.org/donate/tree-plantation?sid=xvjsrg',
        'landing_path' => '/donate/tree-plantation',
    ]);

    expect($visit)->not->toBeNull()
        ->and($visit->donation_order_id)->toBe($order->id)
        ->and($visit->page_path)->toBe('/donate/tree-plantation')
        ->and($visit->converted)->toBeFalse()
        ->and($visit->extra_params['pid'] ?? null)->toBeNull();

    $order->update([
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    app(\App\Services\LinkTrackingService::class)->markConverted($order->fresh());

    $visit->refresh();
    expect($visit->converted)->toBeTrue()
        ->and((float) $visit->converted_amount)->toBe(3000.0)
        ->and((int) LinkTrackingSummary::query()->where('sid', 'xvjsrg')->value('total_donations'))->toBe(1);
});

it('records a visit from a leftover pid as sid and does not store pid', function () {
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'pid' => 'xvjsrg',
        'utm_source' => 'staff',
        'landing_url' => 'https://sadbhavnadham.org/?pid=xvjsrg',
        'page_path' => '/',
    ])
        ->assertOk()
        ->assertJsonPath('recorded', true)
        ->assertJsonPath('sid', 'xvjsrg');

    $visit = LinkTrackingVisit::query()->first();

    expect($visit)->not->toBeNull()
        ->and($visit->sid)->toBe('xvjsrg')
        ->and($visit->extra_params['pid'] ?? null)->toBeNull()
        ->and(LinkTrackingSummary::query()->where('sid', 'xvjsrg')->exists())->toBeTrue();
});

it('stores url-encoded campaign names as readable text', function () {
    $this->postJson('/api/donate/track', [
        'visitor_id' => (string) Str::uuid(),
        'sid' => 'xvjsrg',
        'utm_source' => 'meta',
        'utm_medium' => 'Facebook_Right_Column',
        'utm_campaign' => 'Ashwini+%7C+10%2F08+%7C+Sadbhavna+Ghau+Dan',
        'utm_content' => 'Ashwini+%7C+19%2F08+%7C+Sadbhavna+Ghau+Dan',
        'page_path' => '/donate/old-age-home',
    ])->assertOk();

    $visit = LinkTrackingVisit::query()->first();

    expect($visit?->utm_campaign)->toBe('Ashwini | 10/08 | Sadbhavna Ghau Dan')
        ->and($visit?->utm_content)->toBe('Ashwini | 19/08 | Sadbhavna Ghau Dan');
});

it('keeps a leftover pid as the visit sid when sid is a numeric ad set id', function () {
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/donate/track', [
        'visitor_id' => $visitorId,
        'sid' => '120212',
        'pid' => 'xvjsrg',
        'utm_source' => 'meta',
        'page_path' => '/donate/tree-plantation',
    ])
        ->assertOk()
        ->assertJsonPath('sid', 'xvjsrg');

    $visit = LinkTrackingVisit::query()->first();

    expect($visit->sid)->toBe('xvjsrg')
        ->and($visit->utm_term)->toBe('120212');
});

it('issues a sanctum token on otp verify and serves the donor profile', function () {
    Mail::fake();

    $donor = Donor::factory()->create([
        'name' => 'Ravi Shah',
        'email' => 'ravi@example.com',
        'phone' => '9876543210',
    ]);

    $this->postJson('/api/donate/otp/send', [
        'login_method' => 'phone',
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
    ])->assertOk()->assertJsonPath('found', true);

    $otp = null;
    Mail::assertSent(DonorOtpMail::class, function (DonorOtpMail $mail) use (&$otp) {
        $otp = $mail->otp;

        return true;
    });

    $verify = $this->postJson('/api/donate/otp/verify', [
        'login_method' => 'phone',
        'donor_phone' => '9876543210',
        'phone_dial_code' => '91',
        'otp' => $otp,
    ])->assertOk();

    $verify->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('profile.donor_email', 'ravi@example.com');

    $token = $verify->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->getJson('/api/donate/me')->assertUnauthorized();

    $this->withToken($token)
        ->getJson('/api/donate/me')
        ->assertOk()
        ->assertJsonPath('profile.donor_name', 'Ravi Shah');

    $this->withToken($token)
        ->postJson('/api/donate/logout')
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/donate/me')
        ->assertUnauthorized();
});

it('returns thank-you json with a valid signed url or wordpress token', function () {
    $cause = donateApiCause();
    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_name' => 'Ravi Shah',
        'donor_email' => 'ravi@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $this->getJson('/api/donate/thank-you/'.$order->order_uuid)->assertForbidden();

    $signed = DonationThankYouUrl::forApiOrder($order);
    $query = parse_url($signed, PHP_URL_QUERY);

    $this->getJson('/api/donate/thank-you/'.$order->order_uuid.'?'.$query)
        ->assertOk()
        ->assertJsonPath('thank_you.type', 'order')
        ->assertJsonPath('thank_you.donor_name', 'Ravi Shah')
        ->assertJsonPath('thank_you.amount', 500)
        ->assertJsonPath('thank_you.headline', 'Thank you for your donation');

    config(['app.wp_api_token' => 'api-token']);

    $this->withHeader('X-WP-TOKEN', 'api-token')
        ->getJson('/api/donate/thank-you/'.$order->order_uuid)
        ->assertOk()
        ->assertJsonPath('thank_you.reference', $order->order_uuid);
});
