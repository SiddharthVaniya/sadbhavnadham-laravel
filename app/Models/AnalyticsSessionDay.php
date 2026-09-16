<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsSessionDay extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'stat_date',
        'scope',
        'session_id',
        'visited_cause',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'visited_cause' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
