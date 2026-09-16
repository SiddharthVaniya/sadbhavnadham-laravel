<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationItem extends Model
{
    use HasFactory;

    protected $table = 'donation_items';

    protected $fillable = [
        'donation_order_id',
        'cause_id',
        'cause_package_id',
        'donation_campaign_id',
        'cause',
        'title',
        'quantity',
        'unit_amount',
        'amount',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ==========================
     |  Relationships
     ========================== */

    public function order(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class, 'donation_order_id');
    }

    public function causeModel(): BelongsTo
    {
        return $this->belongsTo(Cause::class, 'cause_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CausePackage::class, 'cause_package_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'donation_campaign_id');
    }
}
