<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PeriodRange
{
    /**
     * Calendar period keys shared across admin / marketer duration dropdowns.
     *
     * @var list<string>
     */
    public const CALENDAR_KEYS = [
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
    ];

    /**
     * @return array{start: Carbon, end: Carbon, label: string}|null
     */
    public static function forKey(string $duration, ?Carbon $reference = null): ?array
    {
        if (! in_array($duration, self::CALENDAR_KEYS, true)) {
            return null;
        }

        $now = ($reference ?? now())->copy();

        return match ($duration) {
            'yesterday' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
                'label' => 'Yesterday · '.$now->copy()->subDay()->format('d M Y'),
            ],
            'this_week' => [
                'start' => $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'label' => 'This week · '.$now->copy()->startOfWeek(Carbon::MONDAY)->format('d M')
                    .' – '.$now->format('d M Y'),
            ],
            'last_week' => (static function () use ($now): array {
                $start = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();
                $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => 'Previous week · '.$start->format('d M')
                        .' – '.$end->format('d M Y'),
                ];
            })(),
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfDay(),
                'label' => 'This month · '.$now->format('F Y'),
            ],
            'last_month' => (static function () use ($now): array {
                $month = $now->copy()->subMonthNoOverflow();

                return [
                    'start' => $month->copy()->startOfMonth(),
                    'end' => $month->copy()->endOfMonth(),
                    'label' => 'Previous month · '.$month->format('F Y'),
                ];
            })(),
            default => null,
        };
    }
}
