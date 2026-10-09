<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdSpendDaily extends Model
{
    public const MATCHED_VIA_AD_NAME_PREFIX = 'ad_name_prefix';

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
        'currency',
        'user_id',
        'matched_via',
    ];

    protected function casts(): array
    {
        return [
            'spend_date' => 'date',
            'spend_amount' => 'float',
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
