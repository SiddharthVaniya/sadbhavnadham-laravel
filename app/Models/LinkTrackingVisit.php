<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkTrackingVisit extends Model
{
    protected $fillable = [
        'visitor_id',
        'sid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_id',
        'utm_term',
        'fbclid',
        'amt',
        'ptype',
        'landing_url',
        'page_path',
        'extra_params',
        'referrer',
        'ip_address',
        'user_agent',
        'device_type',
        'ip_country_code',
        'ip_country_name',
        'ip_region_name',
        'ip_city',
        'ip_postal_code',
        'ip_lat',
        'ip_lng',
        'ip_timezone',
        'ip_asn',
        'ip_isp',
        'is_unique',
        'converted',
        'donation_order_id',
        'converted_amount',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_unique' => 'boolean',
            'converted' => 'boolean',
            'converted_amount' => 'decimal:2',
            'converted_at' => 'datetime',
            'extra_params' => 'array',
            'ip_lat' => 'float',
            'ip_lng' => 'float',
            'ip_asn' => 'integer',
        ];
    }

    public function donationOrder(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class);
    }
}
