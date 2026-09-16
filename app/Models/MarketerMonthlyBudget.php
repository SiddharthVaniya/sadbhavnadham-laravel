<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerMonthlyBudget extends Model
{
    protected $fillable = [
        'user_id',
        'year_month',
        'target_amount',
        'spend_amount',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'spend_amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function currentYearMonth(?\Illuminate\Support\Carbon $reference = null): string
    {
        return ($reference ?? now())->format('Y-m');
    }
}
