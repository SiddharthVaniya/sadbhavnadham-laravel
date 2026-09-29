<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MarketerDailyBudget extends Model
{
    protected $fillable = [
        'user_id',
        'spend_date',
        'limit_amount',
        'spend_amount',
    ];

    protected function casts(): array
    {
        return [
            'spend_date' => 'date',
            'limit_amount' => 'float',
            'spend_amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function todayDate(?Carbon $reference = null): string
    {
        return ($reference ?? now())->toDateString();
    }
}
