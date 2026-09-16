<?php

use App\Http\Requests\Admin\StoreDonationOrderRequest;

it('includes address profile fields in admin donation order request rules', function () {
    $rules = (new StoreDonationOrderRequest)->rules();

    expect($rules)
        ->toHaveKey('address')
        ->and($rules)->toHaveKey('pincode')
        ->and($rules)->toHaveKey('city')
        ->and($rules)->toHaveKey('state')
        ->and($rules)->toHaveKey('country')
        ->and($rules)->toHaveKey('donor_country_code')
        ->and($rules)->toHaveKey('donation_date');
});
