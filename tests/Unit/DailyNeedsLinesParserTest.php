<?php

use App\Support\AdminInertiaResources;

it('parses rupee daily needs titles into separate product lines', function () {
    $lines = AdminInertiaResources::parseDailyNeedsSource(
        "12kg Chickpeas = ₹ 1,236,\n6liter Cooking Oil Tin = ₹ 12,600,\n5piece Ghee Tin = ₹ 5,500\n— total ₹ 19,336"
    );

    expect($lines)->toHaveCount(3)
        ->and($lines[0]['qty_label'])->toBe('12kg')
        ->and($lines[0]['title'])->toBe('Chickpeas')
        ->and($lines[0]['amount'])->toBe(1236.0)
        ->and($lines[1]['qty_label'])->toBe('6liter')
        ->and($lines[1]['title'])->toBe('Cooking Oil Tin')
        ->and($lines[2]['qty_label'])->toBe('5piece')
        ->and($lines[2]['title'])->toBe('Ghee Tin');
});

it('parses legacy rs daily needs titles into separate product lines', function () {
    $lines = AdminInertiaResources::parseDailyNeedsSource(
        '1kg Moth Beans rs 152, 2kg Rice rs 90, 3 Diaper (1 piece) rs 51 -- total 293'
    );

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toMatchArray([
            'title' => 'Moth Beans',
            'qty' => 1,
            'unit' => 'kg',
            'qty_label' => '1kg',
            'amount' => 152.0,
        ])
        ->and($lines[1])->toMatchArray([
            'title' => 'Rice',
            'qty' => 2,
            'unit' => 'kg',
            'qty_label' => '2kg',
            'amount' => 90.0,
        ])
        ->and($lines[2]['title'])->toBe('Diaper (1 piece)')
        ->and($lines[2]['qty_label'])->toBe('3piece')
        ->and($lines[2]['unit'])->toBe('piece')
        ->and($lines[2]['amount'])->toBe(51.0);
});
