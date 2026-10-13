<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationTelecallerNote extends Model
{
    public const SPEAKER_TELECALLER = 'telecaller';

    public const SPEAKER_DONOR = 'donor';

    public const OUTCOMES = [
        'not_receive' => 'Not receive',
        'busy' => 'Busy',
        'send_qr' => 'Send QR',
        'send_details' => 'Send details',
        'paid' => 'Paid',
        'switch_off' => 'Unavailable / cut call / switch off',
        'will_call_later' => 'I will call later',
        'process_to_pay' => 'Process to pay',
        'not_interested' => 'Not interested',
    ];

    /** @var array<string, string> */
    private const LEGACY_OUTCOME_LABELS = [
        'promised_to_pay' => 'Promised to pay',
        'call_back_later' => 'Call back later',
        'not_reachable' => 'Not reachable',
        'refused' => 'Refused',
    ];

    public static function outcomeLabel(?string $outcome): ?string
    {
        if ($outcome === null || $outcome === '') {
            return null;
        }

        return self::OUTCOMES[$outcome]
            ?? self::LEGACY_OUTCOME_LABELS[$outcome]
            ?? null;
    }

    public static function outcomeDefaultMessage(string $outcome): ?string
    {
        return self::outcomeLabel($outcome);
    }

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
