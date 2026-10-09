<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdSpendDaily extends Model
{
    public const MATCHED_VIA_AD_NAME_PREFIX = 'ad_name_prefix';

    public const MATCHED_VIA_NAME_IN_TEXT = 'name_in_text';

    public const MATCHED_VIA_REFERRAL_CODE = 'referral_code';

    public const MATCHED_VIA_UNMATCHED = 'unmatched';

    protected $table = 'meta_ad_spend_daily';

    protected $fillable = [
        'meta_ad_account_id',
        'spend_date',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
        'spend_amount',
        'impressions',
        'clicks',
        'reach',
        'inline_link_clicks',
        'currency',
        'user_id',
        'matched_via',
    ];

    protected function casts(): array
    {
        return [
            'spend_date' => 'date',
            'spend_amount' => 'float',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'reach' => 'integer',
            'inline_link_clicks' => 'integer',
        ];
    }

    /**
     * @return array{ctr: ?float, cpc: ?float, cpm: ?float}
     */
    public static function computedRates(float $spend, int $impressions, int $clicks): array
    {
        return [
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'cpc' => $clicks > 0 ? round($spend / $clicks, 2) : null,
            'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : null,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
