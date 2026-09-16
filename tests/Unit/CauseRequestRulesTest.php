<?php

use App\Http\Requests\Admin\StoreCauseRequest;
use App\Http\Requests\Admin\UpdateCauseRequest;

it('includes active icon fields in store cause rules', function () {
    $rules = (new StoreCauseRequest)->rules();

    expect($rules)->toHaveKey('icon_uri_active_file')
        ->and($rules)->toHaveKey('icon_uri_active_existing')
        ->and(implode(',', (array) $rules['icon_uri_file']))->not->toContain('svg')
        ->and(implode(',', (array) $rules['icon_uri_active_file']))->not->toContain('svg');
});

it('includes active icon fields in update cause rules', function () {
    $request = new UpdateCauseRequest;

    // UpdateCauseRequest depends on route model in slug unique rule;
    // this test verifies only the active icon keys are present in the rules array shape.
    $request->setRouteResolver(function () {
        return new class
        {
            public function parameter(string $key): mixed
            {
                if ($key === 'cause') {
                    $cause = new \App\Models\Cause;
                    $cause->id = 1;

                    return $cause;
                }

                return null;
            }
        };
    });

    $rules = $request->rules();

    expect($rules)->toHaveKey('icon_uri_active_file')
        ->and($rules)->toHaveKey('icon_uri_active_existing')
        ->and(implode(',', (array) $rules['icon_uri_file']))->not->toContain('svg')
        ->and(implode(',', (array) $rules['icon_uri_active_file']))->not->toContain('svg');
});
