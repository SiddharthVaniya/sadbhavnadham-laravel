<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailyStat extends Model
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_IN = 'in';

    protected $fillable = [
        'stat_date',
        'scope',
        'home_visits',
        'cause_views',
        'unique_visitors',
        'unique_cause_visitors',
        'checkouts_started',
        'donations_paid',
        'donations_failed',
        'tracked_revenue',
        'countries_reached',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'tracked_revenue' => 'decimal:2',
            'home_visits' => 'integer',
            'cause_views' => 'integer',
            'unique_visitors' => 'integer',
            'unique_cause_visitors' => 'integer',
            'checkouts_started' => 'integer',
            'donations_paid' => 'integer',
            'donations_failed' => 'integer',
            'countries_reached' => 'integer',
        ];
    }
}
