<?php

use App\Support\PeriodRange;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('resolves yesterday as the previous calendar day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:30:00'));

    $range = PeriodRange::forKey('yesterday');

    expect($range)->not->toBeNull()
        ->and($range['start']->toDateTimeString())->toBe('2026-09-15 00:00:00')
        ->and($range['end']->toDateTimeString())->toBe('2026-09-15 23:59:59');
});

it('resolves this week from monday through now end of day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:30:00')); // Wednesday

    $range = PeriodRange::forKey('this_week');

    expect($range)->not->toBeNull()
        ->and($range['start']->toDateTimeString())->toBe('2026-09-14 00:00:00')
        ->and($range['end']->toDateTimeString())->toBe('2026-09-16 23:59:59');
});

it('resolves last week as the previous monday through sunday', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:30:00')); // Wednesday

    $range = PeriodRange::forKey('last_week');

    expect($range)->not->toBeNull()
        ->and($range['start']->toDateTimeString())->toBe('2026-09-07 00:00:00')
        ->and($range['end']->toDateTimeString())->toBe('2026-09-13 23:59:59');
});

it('resolves this month from month start through now end of day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:30:00'));

    $range = PeriodRange::forKey('this_month');

    expect($range)->not->toBeNull()
        ->and($range['start']->toDateTimeString())->toBe('2026-09-01 00:00:00')
        ->and($range['end']->toDateTimeString())->toBe('2026-09-16 23:59:59');
});

it('resolves last month as the full previous calendar month', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 15:30:00'));

    $range = PeriodRange::forKey('last_month');

    expect($range)->not->toBeNull()
        ->and($range['start']->toDateTimeString())->toBe('2026-08-01 00:00:00')
        ->and($range['end']->toDateTimeString())->toBe('2026-08-31 23:59:59');
});

it('returns null for unknown duration keys', function () {
    expect(PeriodRange::forKey('7d'))->toBeNull();
});
