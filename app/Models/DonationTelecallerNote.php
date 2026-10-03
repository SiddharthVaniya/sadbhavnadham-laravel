<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationTelecallerNote extends Model
{
    public const SPEAKER_TELECALLER = 'telecaller';

    public const SPEAKER_DONOR = 'donor';

    public const OUTCOMES = [
        'promised_to_pay' => 'Promised to pay',
        'call_back_later' => 'Call back later',
        'not_reachable' => 'Not reachable',
        'refused' => 'Refused',
        'paid' => 'Paid',
    ];

    protected $fillable = [
        'donation_order_id',
        'donor_id',
        'user_id',
        'telecaller_name',
        'speaker',
        'message',
        'outcome',
        'follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class, 'donation_order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
