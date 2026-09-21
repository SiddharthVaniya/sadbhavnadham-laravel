<?php

use App\Models\AnalyticsEvent;
use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\DonationPaymentService;
use App\Services\DonationSubscriptionWebhookService;
use App\Support\AdminStaffReferralsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function auditOrder(string $providerOrderId, int $amount = 1000): DonationOrder
{
    return DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => $providerOrderId,
        'donor_name' => 'Audit Donor',
        'donor_email' => $providerOrderId.'@example.com',
        'donor_phone' => '9876500099',
        'currency' => 'INR',
        'total_amount' => $amount,
        'status' => DonationOrder::STATUS_PENDING,
    ]);
}

function checkoutWithCookie(DonationOrder $order, array $form, ?array $cookie): void
{
    $request = Request::create('/donate/razorpay', 'POST', $form);

    if ($cookie !== null) {
        $request->cookies->set(
            'donation_analytics_utm',
            json_encode($cookie, JSON_UNESCAPED_UNICODE)
        );
    }

    app(AnalyticsService::class)->trackCheckoutStarted($request, $order);
}

function capturedPayment(DonationOrder $order, string $paymentId): array
{
    return [
        'id' => $paymentId,
        'order_id' => $order->provider_order_id,
        'amount' => (int) round(((float) $order->total_amount) * 100),
        'currency' => 'INR',
        'status' => 'captured',
    ];
}

it('stamps a direct checkout without inventing a marketing source', function () {
    $order = auditOrder('audit-direct-1');

    checkoutWithCookie($order, [], null);

    $order->refresh();

    expect($order->utm_source)->toBeNull()
        ->and($order->partner_user_id)->toBeNull()
        ->and($order->source_channel)->toBe('web');
});

it('keeps original campaign utms when checkout happens on a different page with only the cookie', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $tree = Cause::factory()->create(['slug' => 'tree-plantation', 'is_active' => true]);
    $home = Cause::factory()->create(['slug' => 'old-age-home', 'is_active' => true]);

    $this->get('/donate/tree-plantation?utm_source=meta&utm_medium=paid_social&utm_campaign=monsoon&sid=ashvini&utm_id=120211')
        ->assertOk()
        ->assertCookie('donation_analytics_utm');

    $this->withUnencryptedCookie('donation_analytics_utm', json_encode([
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'monsoon',
        'sid' => 'ashvini',
        'utm_id' => '120211',
        'landing_path' => 'donate/tree-plantation?utm_source=meta&utm_medium=paid_social&utm_campaign=monsoon&sid=ashvini&utm_id=120211',
    ], JSON_UNESCAPED_UNICODE))
        ->get('/donate/old-age-home')
        ->assertOk();

    $order = auditOrder('audit-nav-cookie-only');

    checkoutWithCookie($order, [], [
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'monsoon',
        'sid' => 'ashvini',
        'utm_id' => '120211',
        'landing_path' => 'donate/tree-plantation?utm_source=meta&utm_medium=paid_social&utm_campaign=monsoon&sid=ashvini&utm_id=120211',
    ]);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_campaign)->toBe('monsoon')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->meta_campaign_id)->toBe('120211')
        ->and($order->landing_path)->toStartWith('donate/tree-plantation')
        ->and($order->landing_path)->not->toContain('razorpay');

    expect($tree->slug)->toBe('tree-plantation')
        ->and($home->slug)->toBe('old-age-home');
});

it('sets the attribution cookie when the visitor lands on bank details', function () {
    $this->get('/bank-details?utm_source=google&utm_medium=cpc&utm_campaign=search')
        ->assertOk()
        ->assertCookie('donation_analytics_utm');
});

it('keeps first-touch meta when a later employee link is opened', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = auditOrder('audit-meta-then-staff');

    checkoutWithCookie($order, [
        'utm_source' => 'staff',
        'utm_medium' => 'referral',
        'utm_content' => 'ashvini',
        'sid' => 'ashvini',
    ], [
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'tree_plantation_august',
        'utm_id' => '120211',
        'aid' => '120213',
    ]);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->utm_campaign)->toBe('tree_plantation_august')
        ->and($order->meta_campaign_id)->toBe('120211')
        ->and($order->meta_ad_id)->toBe('120213')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('ashvini')
        ->and($order->attr_source)->toBe('meta');
});

it('lets a later Meta ad click replace a prior WhatsApp/staff cookie touch', function () {
    $staff = User::factory()->create(['referral_code' => 'pr', 'name' => 'Pritesh Rathod']);
    $urvi = User::factory()->create(['referral_code' => 'cpufaju', 'name' => 'Urvi Soni']);
    $order = auditOrder('audit-whatsapp-then-meta');

    checkoutWithCookie($order, [
        'utm_source' => 'meta',
        'utm_medium' => 'Instagram_Feed',
        'utm_campaign' => 'Urvi |  Sales | CBO | Bull | 5x | 16/9/26',
        'utm_content' => 'Urvi | Sales | Vishal Fodder R | 200',
        'sid' => 'cpufaju',
        'utm_id' => '120999',
        'aid' => '120998',
    ], [
        'utm_source' => 'meta',
        'utm_medium' => 'whatsapp',
        'utm_campaign' => 'tree',
        'sid' => 'pr',
        'landing_path' => '/donate/tree-plantation',
    ]);

    $order->refresh();

    expect($order->partner_user_id)->toBe($urvi->id)
        ->and($order->partner_code)->toBe('cpufaju')
        ->and($order->utm_medium)->toBe('Instagram_Feed')
        ->and($order->utm_campaign)->toBe('Urvi |  Sales | CBO | Bull | 5x | 16/9/26')
        ->and($order->utm_content)->toBe('Urvi | Sales | Vishal Fodder R | 200')
        ->and($staff->id)->not->toBe($order->partner_user_id);
});

it('keeps first-touch Meta when a later WhatsApp link without Meta placement arrives', function () {
    $partner = User::factory()->create(['referral_code' => 'ashvini']);
    $order = auditOrder('audit-meta-then-whatsapp');

    checkoutWithCookie($order, [
        'utm_source' => 'meta',
        'utm_medium' => 'whatsapp',
        'utm_campaign' => 'tree',
        'sid' => 'someone-else',
    ], [
        'utm_source' => 'meta',
        'utm_medium' => 'Instagram_Feed',
        'utm_campaign' => 'first-meta',
        'utm_content' => 'Ashvini | Retargeting',
        'sid' => 'ashvini',
        'utm_id' => '120211',
    ]);

    $order->refresh();

    expect($order->utm_campaign)->toBe('first-meta')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('ashvini');
});

it('fills an empty partner slot when a later employee link arrives after meta', function () {
    $partner = User::factory()->create(['referral_code' => 'kiran']);
    $order = auditOrder('audit-meta-fill-sid');

    checkoutWithCookie($order, [
        'sid' => 'kiran',
        'utm_source' => 'staff',
        'utm_content' => 'kiran',
    ], [
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
    ]);

    $order->refresh();

    expect($order->utm_source)->toBe('meta')
        ->and($order->partner_user_id)->toBe($partner->id);
});

it('still has attribution after payment is confirmed only by webhook', function () {
    Bus::fake();
    $partner = User::factory()->create(['referral_code' => 'paz']);
    $order = auditOrder('audit-webhook-1', 2500);

    checkoutWithCookie($order, [
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'sid' => 'paz',
        'utm_id' => '1201',
    ], null);

    app(DonationPaymentService::class)->handleCaptured(capturedPayment($order, 'pay_audit_1'));

    $order->refresh();

    expect($order->isPaid())->toBeTrue()
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->utm_source)->toBe('meta')
        ->and($order->meta_campaign_id)->toBe('1201');
});

it('does not double-count when capture callback and webhook both run', function () {
    Bus::fake();
    $order = auditOrder('audit-dup-capture', 501);

    checkoutWithCookie($order, ['utm_source' => 'google', 'utm_medium' => 'cpc'], null);

    $payment = capturedPayment($order, 'pay_audit_dup');
    $service = app(DonationPaymentService::class);
    $service->handleCaptured($payment);
    $service->handleCaptured($payment);

    expect(DonationOrder::query()->where('provider_order_id', 'audit-dup-capture')->count())->toBe(1)
        ->and($order->fresh()->isPaid())->toBeTrue()
        ->and((float) $order->fresh()->total_amount)->toBe(501.0)
        ->and(
            AnalyticsEvent::query()
                ->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID)
                ->where('donation_order_id', $order->id)
                ->count()
        )->toBe(1);
});

it('keeps attribution on a failed payment and excludes it from paid revenue', function () {
    Bus::fake();
    $partner = User::factory()->create(['referral_code' => 'mjv']);
    $order = auditOrder('audit-failed-1', 9000);

    checkoutWithCookie($order, [
        'utm_source' => 'staff',
        'utm_content' => 'mjv',
        'sid' => 'mjv',
    ], null);

    app(DonationPaymentService::class)->handleFailed(capturedPayment($order, 'pay_failed_1'));

    $order->refresh();

    expect($order->isFailed())->toBeTrue()
        ->and($order->partner_user_id)->toBe($partner->id);

    $paid = DonationOrder::query()
        ->where('status', DonationOrder::STATUS_PAID)
        ->tap(fn ($query) => AdminStaffReferralsData::applyPartnerAttributionFilter($query, $partner))
        ->sum('total_amount');

    expect((float) $paid)->toBe(0.0);
});

it('counts pending and failed separately from paid partner revenue', function () {
    $partner = User::factory()->create(['name' => 'Ashvini', 'referral_code' => 'ashvini']);

    auditOrder('audit-sum-paid', 75000)->forceFill([
        'partner_user_id' => $partner->id,
        'partner_code' => 'ashvini',
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ])->save();

    auditOrder('audit-sum-pending', 5000)->forceFill([
        'partner_user_id' => $partner->id,
        'partner_code' => 'ashvini',
        'status' => DonationOrder::STATUS_PENDING,
    ])->save();

    auditOrder('audit-sum-failed', 9000)->forceFill([
        'partner_user_id' => $partner->id,
        'partner_code' => 'ashvini',
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => now(),
    ])->save();

    $admin = User::factory()->create(['referral_code' => 'admin-code']);
    $admin->givePermissionTo(
        collect(['view staff referrals', 'view all donations'])->each(
            fn (string $permission) => \Spatie\Permission\Models\Permission::findOrCreate($permission)
        )->all()
    );

    $page = AdminStaffReferralsData::pageFromRequest(
        $admin,
        Request::create('/admin/referrals', 'GET', [
            'duration' => 'all',
            'partner_user_id' => $partner->id,
        ])
    );

    expect($page['summary']['paid_orders'])->toBe(1)
        ->and($page['summary']['revenue'])->toBe(75000.0)
        ->and($page['summary']['pending_orders'])->toBe(1)
        ->and($page['summary']['pending_amount'])->toBe(5000.0)
        ->and($page['summary']['failed_orders'])->toBe(1)
        ->and($page['summary']['failed_amount'])->toBe(9000.0);
});

it('marks a previously failed order paid once on retry without duplicating the row', function () {
    Bus::fake();
    $order = auditOrder('audit-retry-1', 1200);

    User::factory()->create(['referral_code' => 'paz']);
    checkoutWithCookie($order, ['utm_source' => 'whatsapp', 'utm_medium' => 'social', 'sid' => 'paz'], null);

    app(DonationPaymentService::class)->handleFailed(capturedPayment($order, 'pay_retry_fail'));
    expect($order->fresh()->isFailed())->toBeTrue();

    app(DonationPaymentService::class)->handleCaptured(capturedPayment($order, 'pay_retry_ok'));

    $fresh = $order->fresh();

    expect(DonationOrder::query()->where('provider_order_id', 'audit-retry-1')->count())->toBe(1)
        ->and($fresh->isPaid())->toBeTrue()
        ->and($fresh->utm_source)->toBe('whatsapp')
        ->and($fresh->partner_code)->toBe('paz');
});

it('copies signup attribution onto recurring orders created by the webhook', function () {
    Bus::fake();

    $partner = User::factory()->create(['referral_code' => 'riya']);
    $subscription = DonationSubscription::factory()->create([
        'status' => DonationSubscription::STATUS_AUTHENTICATED,
        'billing_cycle_count' => 0,
        'total_amount' => 500,
        'unit_amount' => 500,
        'utm_source' => 'meta',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'monthly-trees',
        'partner_user_id' => $partner->id,
        'partner_code' => 'riya',
        'meta_campaign_id' => '555',
        'attr_source' => 'meta',
        'attr_medium' => 'paid_social',
    ]);

    app(DonationSubscriptionWebhookService::class)->handle(
        'subscription.charged',
        [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'id' => $subscription->razorpay_subscription_id,
                        'status' => 'active',
                        'paid_count' => 1,
                        'charge_at' => now()->addMonth()->timestamp,
                    ],
                ],
                'payment' => [
                    'entity' => [
                        'id' => 'pay_sub_attr_1',
                        'amount' => 50000,
                        'currency' => 'INR',
                        'subscription_id' => $subscription->razorpay_subscription_id,
                    ],
                ],
            ],
        ]
    );

    $order = DonationOrder::query()
        ->where('donation_subscription_id', $subscription->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->isPaid())->toBeTrue()
        ->and($order->utm_source)->toBe('meta')
        ->and($order->partner_user_id)->toBe($partner->id)
        ->and($order->partner_code)->toBe('riya')
        ->and($order->meta_campaign_id)->toBe('555');
});
