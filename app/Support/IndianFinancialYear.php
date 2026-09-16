<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class IndianFinancialYear
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(?Carbon $date = null): array
    {
        $date ??= now();

        if ($date->month >= 4) {
            $start = Carbon::create($date->year, 4, 1)->startOfDay();
            $end = Carbon::create($date->year + 1, 3, 31)->endOfDay();
        } else {
            $start = Carbon::create($date->year - 1, 4, 1)->startOfDay();
            $end = Carbon::create($date->year, 3, 31)->endOfDay();
        }

        return [$start, $end];
    }
}
