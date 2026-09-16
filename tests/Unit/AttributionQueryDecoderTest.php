<?php

use App\Support\Attribution\AttributionParameters;

it('decodes leftover url-encoded campaign names from meta ads', function () {
    expect(AttributionParameters::decodeQueryValue('Ashwini+%7C+10%2F08+%7C+Sadbhavna+Ghau+Dan'))
        ->toBe('Ashwini | 10/08 | Sadbhavna Ghau Dan');
});

it('leaves already readable campaign names unchanged', function () {
    expect(AttributionParameters::decodeQueryValue('Ashwini | 19/08 | Sadbhavna Ghau Dan | Retargeting'))
        ->toBe('Ashwini | 19/08 | Sadbhavna Ghau Dan | Retargeting');
});

it('decodes double-encoded campaign names', function () {
    expect(AttributionParameters::decodeQueryValue('Ashwini%2B%257C%2B10%252F08'))
        ->toBe('Ashwini | 10/08');
});

it('decodes tracking payload utm fields and extra params', function () {
    $decoded = AttributionParameters::decodeTrackingPayload([
        'utm_campaign' => 'Ashwini+%7C+10%2F08',
        'utm_content' => 'Plant+a+tree',
        'landing_url' => 'https://sadbhavnadham.org/donate?utm_campaign=Ashwini+%7C+10%2F08',
        'extra_params' => [
            'placement' => 'Facebook_Desktop_Feed',
            'note' => 'Ghau+%7C+Dan',
        ],
    ]);

    expect($decoded['utm_campaign'])->toBe('Ashwini | 10/08')
        ->and($decoded['utm_content'])->toBe('Plant a tree')
        ->and($decoded['landing_url'])->toBe('https://sadbhavnadham.org/donate?utm_campaign=Ashwini+%7C+10%2F08')
        ->and($decoded['extra_params']['note'])->toBe('Ghau | Dan')
        ->and($decoded['extra_params']['placement'])->toBe('Facebook_Desktop_Feed');
});
