<?php

use App\Http\Requests\StoreDonationRequest;

it('includes extended donor profile fields in donation request rules', function () {
    $rules = (new StoreDonationRequest)->rules();

    expect($rules)
        ->toHaveKey('date_of_birth')
        ->and($rules)->toHaveKey('pincode')
        ->and($rules)->toHaveKey('city')
        ->and($rules)->toHaveKey('state')
        ->and($rules)->toHaveKey('country')
        ->and($rules)->toHaveKey('donor_country')
        ->and($rules)->toHaveKey('consent_indian_citizen')
        ->and($rules)->toHaveKey('honoree_names')
        ->and($rules)->toHaveKey('honoree_names.*');
});

it('accepts exactly ten digits for donor phone', function () {
    $rules = (new StoreDonationRequest)->rules();
    $phoneRegexRule = collect($rules['donor_phone'])
        ->first(fn (string $rule) => str_starts_with($rule, 'regex:'));

    expect($phoneRegexRule)->not->toBeNull();
    $pattern = substr($phoneRegexRule, strlen('regex:'));

    expect(preg_match($pattern, '9876543210'))->toBe(1);
});

it('accepts donor names in multiple languages', function (string $donorName) {
    $rules = (new StoreDonationRequest)->rules();
    $nameRegexRule = collect($rules['donor_name'])
        ->first(fn (string $rule) => str_starts_with($rule, 'regex:'));

    expect($nameRegexRule)->not->toBeNull();
    $pattern = substr($nameRegexRule, strlen('regex:'));

    expect(preg_match($pattern, $donorName))->toBe(1);
})->with([
    'english' => 'Asha Patel',
    'hindi' => 'आशा पटेल',
    'gujarati' => 'આશા પટેલ',
    'marathi' => 'आशा पाटील',
    'gujarati with village' => 'મુકેશભાઈ તુલશીભાઈ સાચપરા (બુધેલ)',
    'hyphenated' => 'Mary-Jane O\'Brien',
    'initials' => 'A. K. Shah',
]);

it('rejects donor names with numbers or symbols', function (string $donorName) {
    $rules = (new StoreDonationRequest)->rules();
    $nameRegexRule = collect($rules['donor_name'])
        ->first(fn (string $rule) => str_starts_with($rule, 'regex:'));

    expect($nameRegexRule)->not->toBeNull();
    $pattern = substr($nameRegexRule, strlen('regex:'));

    expect(preg_match($pattern, $donorName))->toBe(0);
})->with([
    'number' => 'Asha 123',
    'symbol' => 'Asha @ Patel',
    'only punctuation' => '()-.',
]);

it('rejects invalid donor phone values', function (string $phone) {
    $rules = (new StoreDonationRequest)->rules();
    $phoneRegexRule = collect($rules['donor_phone'])
        ->first(fn (string $rule) => str_starts_with($rule, 'regex:'));

    expect($phoneRegexRule)->not->toBeNull();
    $pattern = substr($phoneRegexRule, strlen('regex:'));

    expect(preg_match($pattern, $phone))->toBe(0);
})->with([
    'less than 10 digits' => '987654321',
    'more than 10 digits' => '98765432101',
    'contains letters' => '98765A3210',
    'contains symbol' => '98765-3210',
]);
