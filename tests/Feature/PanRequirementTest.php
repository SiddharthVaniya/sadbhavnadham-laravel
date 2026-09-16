<?php

use App\Http\Requests\StoreDonationRequest;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\Donor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function donationPayload(Cause $cause, ?CausePackage $package = null, array $overrides = []): array
{
    return array_merge([
        'cause' => $cause->slug,
        'package_id' => $package?->id,
        'amount' => $package ? null : 5000,
        'quantity' => 1,
        'donor_name' => 'Test Donor',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'address' => 'Test address line',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'INDIA',
        'donor_country' => 'IN',
        'consent_indian_citizen' => '1',
        'pan_number' => null,
    ], $overrides);
}

it('returns pan not required for small donations via api', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    $this->postJson(route('donate.pan-requirement'), [
        'cause' => $cause->slug,
        'amount' => 5000,
        'quantity' => 1,
        'donor_email' => 'new@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJson([
            'required' => false,
            'current_amount' => 5000,
        ]);
});

it('returns pan required when current donation reaches threshold', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    $this->postJson(route('donate.pan-requirement'), [
        'cause' => $cause->slug,
        'amount' => 100000,
        'quantity' => 1,
        'donor_email' => 'new@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJson([
            'required' => true,
            'current_amount' => 100000,
        ]);
});

it('returns pan required when same phone has fy donations even with a different email', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 1));

    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_email' => 'old@example.com',
        'donor_phone' => '9876543210',
        'donor_name' => 'Repeat Donor',
        'currency' => 'INR',
        'total_amount' => 50000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subWeek(),
    ]);

    $this->postJson(route('donate.pan-requirement'), [
        'cause' => $cause->slug,
        'amount' => 51000,
        'quantity' => 1,
        'donor_email' => 'new.email@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJson([
            'required' => true,
            'fy_paid_total' => 50000,
            'combined_total' => 101000,
        ]);
});

it('returns pan required when fy donations plus current amount reach threshold', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 1));

    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_email' => 'repeat@example.com',
        'donor_phone' => '9876543210',
        'donor_name' => 'Repeat Donor',
        'currency' => 'INR',
        'total_amount' => 50000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->subWeek(),
    ]);

    $this->postJson(route('donate.pan-requirement'), [
        'cause' => $cause->slug,
        'amount' => 51000,
        'quantity' => 1,
        'donor_email' => 'repeat@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJson([
            'required' => true,
            'fy_paid_total' => 50000,
            'combined_total' => 101000,
        ]);
});

it('rejects razorpay checkout when pan is required but missing', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    $this->postJson(route('donate.razorpay'), donationPayload($cause, null, [
        'amount' => 100000,
        'pan_number' => null,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pan_number']);
});

it('accepts small donations without pan during server validation', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);
    $payload = donationPayload($cause, null, [
        'amount' => 5000,
        'pan_number' => null,
    ]);

    $request = StoreDonationRequest::createFrom(request()->merge($payload));
    $request->setContainer(app());
    $validator = Validator::make($payload, $request->rules(), $request->messages());
    $request->withValidator($validator);

    expect($validator->passes())->toBeTrue();
});

it('requires pan during server validation when threshold is reached', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);
    $payload = donationPayload($cause, null, [
        'amount' => 100000,
        'pan_number' => null,
    ]);

    $request = StoreDonationRequest::createFrom(request()->merge($payload));
    $request->setContainer(app());
    $validator = Validator::make($payload, $request->rules(), $request->messages());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('pan_number'))->toBeTrue();
});

it('hides pan field on cause page when pan collection is disabled', function () {
    $cause = Cause::factory()->create(['pan_required' => false, 'is_active' => true]);

    $this->get(route('donate.show', $cause))
        ->assertOk()
        ->assertSee('data-pan-enabled="0"', false)
        ->assertSee('id="panFieldWrap"', false);
});

it('shows pan field as optional on cause page when pan collection is enabled', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'is_active' => true]);

    $this->get(route('donate.show', $cause))
        ->assertOk()
        ->assertSee('data-pan-enabled="1"', false)
        ->assertSee('id="panOptionalMark"', false)
        ->assertSee('(optional)', false)
        ->assertDontSee('id="panFieldWrap" style="display: none;"', false);
});

it('returns known pan even when current donation is below threshold', function () {
    $cause = Cause::factory()->create(['pan_required' => true, 'allow_custom_amount' => true]);

    Donor::factory()->create([
        'email' => 'known@example.com',
        'phone' => '9876543210',
        'pan_number' => 'ABCDE1234F',
    ]);

    $this->postJson(route('donate.pan-requirement'), [
        'cause' => $cause->slug,
        'amount' => 500,
        'quantity' => 1,
        'donor_email' => 'known@example.com',
        'donor_phone' => '9876543210',
    ])
        ->assertOk()
        ->assertJson([
            'required' => false,
            'known_pan_number' => 'ABCDE1234F',
        ]);
});

it('resolves the indian financial year from april to march', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15));

    [$start, $end] = \App\Support\IndianFinancialYear::range();

    expect($start->toDateString())->toBe('2026-04-01')
        ->and($end->toDateString())->toBe('2027-03-31');

    Carbon::setTestNow(Carbon::create(2026, 2, 10));

    [$start, $end] = \App\Support\IndianFinancialYear::range();

    expect($start->toDateString())->toBe('2025-04-01')
        ->and($end->toDateString())->toBe('2026-03-31');
});

it('returns a known pan number from any donor profile with the same phone', function () {
    Donor::factory()->create([
        'email' => 'known@example.com',
        'phone' => '9000000001',
        'pan_number' => 'ABCDE1234F',
    ]);

    expect(app(\App\Support\PanRequirementService::class)->knownPanNumber('9000000001'))
        ->toBe('ABCDE1234F');
});
