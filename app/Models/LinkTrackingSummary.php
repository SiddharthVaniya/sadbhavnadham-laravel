<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkTrackingSummary extends Model
{
    protected $table = 'link_tracking_summary';

    protected $fillable = [
        'sid',
        'total_clicks',
        'unique_visitors',
        'total_donations',
        'total_amount',
        'average_amount',
        'first_click_at',
        'last_click_at',
        'last_donation_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'total_clicks' => 'integer',
            'unique_visitors' => 'integer',
            'total_donations' => 'integer',
            'total_amount' => 'decimal:2',
            'average_amount' => 'decimal:2',
            'first_click_at' => 'datetime',
            'last_click_at' => 'datetime',
            'last_donation_at' => 'datetime',
        ];
    }
}
