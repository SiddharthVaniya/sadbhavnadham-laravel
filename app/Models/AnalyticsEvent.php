<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_VISIT_HOME = 'visit_home';

    public const TYPE_VISIT_CAUSE = 'visit_cause';

    public const TYPE_VISIT_CAMPAIGN = 'visit_campaign';

    public const TYPE_CHECKOUT_STARTED = 'checkout_started';

    public const TYPE_SUBSCRIPTION_CHECKOUT_STARTED = 'subscription_checkout_started';

    public const TYPE_DONATION_PAID = 'donation_paid';

    public const TYPE_DONATION_FAILED = 'donation_failed';

    protected $fillable = [
        'event_type',
        'session_id',
        'cause_id',
        'donation_order_id',
        'path',
        'referrer',
        'device_type',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'attr_source',
        'attr_medium',
        'attr_platform',
        'attr_placement',
        'partner_user_id',
        'partner_code',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'ip_address',
        'country_code',
        'country_name',
        'region_name',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'asn',
        'isp',
        'amount',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'asn' => 'integer',
    ];

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }

    public function donationOrder(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class);
    }
}
