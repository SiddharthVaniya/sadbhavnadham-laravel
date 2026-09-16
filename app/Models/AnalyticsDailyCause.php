<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsDailyCause extends Model
{
    protected $fillable = [
        'stat_date',
        'scope',
        'cause_id',
        'views',
        'unique_visitors',
        'checkouts',
        'paid',
        'revenue',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'cause_id' => 'integer',
            'views' => 'integer',
            'unique_visitors' => 'integer',
            'checkouts' => 'integer',
            'paid' => 'integer',
            'revenue' => 'decimal:2',
        ];
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }
}
