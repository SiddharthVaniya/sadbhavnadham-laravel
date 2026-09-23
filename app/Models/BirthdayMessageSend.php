<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayMessageSend extends Model
{
    protected $fillable = [
        'donor_id',
        'year',
        'birthday_message_step_id',
        'days_before',
        'kind',
        'campaign_name',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(BirthdayMessageStep::class, 'birthday_message_step_id');
    }
}
