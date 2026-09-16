<?php

use App\Models\Cause;
use App\Models\CausePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function treeDedicationCheckoutPayload(Cause $cause, CausePackage $package, array $overrides = []): array
{
    return array_merge([
        'cause' => $cause->slug,
        'package_id' => $package->id,
        'quantity' => 1,
        'donor_name' => 'Asha Patel',
        'donor_email' => 'asha@example.com',
        'donor_phone' => '9876543210',
        'address' => '12 Green Avenue',
        'pincode' => '360001',
        'city' => 'Rajkot',
        'state' => 'Gujarat',
        'country' => 'India',
        'consent_indian_citizen' => '1',
    ], $overrides);
}

it('rejects more tree dedication names than the selected quantity', function () {
    $cause = Cause::factory()->create([
        'slug' => 'tree-plantation',
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 3000,
        'is_active' => true,
    ]);

    $this->from(route('donate.show', $cause->slug))
        ->post(route('donate.razorpay'), treeDedicationCheckoutPayload($cause, $package, [
            'quantity' => 1,
            'honoree_names' => ['Asha Patel', 'Ramesh Patel'],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('honoree_names');
});

it('rejects invalid characters in a tree dedication name', function () {
    $cause = Cause::factory()->create([
        'slug' => 'tree-plantation',
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);
    $package = CausePackage::factory()->for($cause)->create([
        'amount' => 3000,
        'is_active' => true,
    ]);

    $this->from(route('donate.show', $cause->slug))
        ->post(route('donate.razorpay'), treeDedicationCheckoutPayload($cause, $package, [
            'honoree_names' => ['Asha 123'],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('honoree_names.0');
});
