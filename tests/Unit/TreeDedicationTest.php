<?php

use App\Support\TreeDedication;

uses(Tests\TestCase::class);

it('collects dedication names only for configured tree causes', function () {
    expect(TreeDedication::collectsForSlug('tree-plantation'))->toBeTrue()
        ->and(TreeDedication::collectsForSlug('old-age-home'))->toBeFalse()
        ->and(TreeDedication::collectsForSlug(null))->toBeFalse();
});

it('pads and trims honoree names to the selected quantity', function () {
    expect(TreeDedication::normalize(['  Asha Patel  ', '', 'ignored'], 2))
        ->toBe(['Asha Patel', ''])
        ->and(TreeDedication::forItemMeta(['Asha Patel'], 2))->toBe(['Asha Patel', ''])
        ->and(TreeDedication::forItemMeta(['', '  '], 2))->toBeNull();
});

it('labels filled tree names for receipts and admin', function () {
    expect(TreeDedication::displayLines(['Asha Patel', '', 'Ramesh Patel']))
        ->toBe([
            'Tree 1: Asha Patel',
            'Tree 3: Ramesh Patel',
        ])
        ->and(TreeDedication::displayLines(null))->toBe([]);
});
