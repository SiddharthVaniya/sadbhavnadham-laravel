<?php

use App\Helpers\DonationRequestLogContext;

it('builds sanitized donation request log context without pii fields', function () {
    $context = DonationRequestLogContext::fromInput([
        'cause' => 'feed-cows',
        'package_id' => 12,
        'amount' => 500,
        'quantity' => 2,
        'donor_name' => 'Sensitive Name',
        'donor_email' => 'sensitive@example.com',
        'donor_phone' => '9876543210',
        'date_of_birth' => '1990-01-01',
        'address' => 'Sensitive address',
        'pincode' => '123456',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'country' => 'india',
        'donor_country' => 'in',
        'consent_indian_citizen' => '1',
        'pan_number' => 'ABCDE1234F',
    ]);

    expect($context)
        ->toMatchArray([
            'cause' => 'feed-cows',
            'package_id' => 12,
            'amount' => 500,
            'quantity' => 2,
            'country' => 'INDIA',
            'donor_country' => 'IN',
            'consent_indian_citizen' => true,
            'has_pan_number' => true,
        ])
        ->and($context)->not->toHaveKeys([
            'donor_name',
            'donor_email',
            'donor_phone',
            'date_of_birth',
            'address',
            'pincode',
            'city',
            'state',
            'pan_number',
        ]);
});
