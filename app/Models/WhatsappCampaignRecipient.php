<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'whatsapp_campaign_run_id',
        'donor_id',
        'phone',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaignRun::class, 'whatsapp_campaign_run_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }
}
