<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DanamojoDonation extends Model
{
    public const STATE_NOTIFIED = 'notified';

    public const STATE_PENDING = 'pending';

    public const STATE_FAILED = 'failed';

    public const STATE_IMPORTED = 'imported';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'donation_info_id',
        'donation_order_id',
        'payment_status',
        'dm_status',
        'sync_state',
        'donor_name',
        'donor_email',
        'donor_phone',
        'nationality',
        'country',
        'currency',
        'amount_local',
        'amount_inr',
        'payment_option',
        'product_name',
        'receipt_number',
        'receipt_link',
        'referer_url',
        'landing_url',
        'sid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'aid',
        'partner_code',
        'partner_user_id',
        'device',
        'recurring',
        'fcra',
        'international',
        'donated_at',
        'notified_at',
        'last_synced_at',
        'imported_at',
        'next_retry_at',
        'retry_count',
        'last_error',
        'raw_payload',
        'notify_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'donation_info_id' => 'integer',
            'amount_local' => 'decimal:2',
            'amount_inr' => 'decimal:2',
            'recurring' => 'boolean',
            'fcra' => 'boolean',
            'international' => 'boolean',
            'donated_at' => 'datetime',
            'notified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'imported_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'retry_count' => 'integer',
            'raw_payload' => 'array',
            'notify_payload' => 'array',
        ];
    }

    public function donationOrder(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }
}
