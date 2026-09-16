<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailyDimension extends Model
{
    public const TYPE_REFERRER = 'referrer';

    public const TYPE_DEVICE = 'device';

    public const TYPE_UTM = 'utm';

    public const TYPE_COUNTRY = 'country';

    public const TYPE_REGION = 'region';

    public const TYPE_CITY = 'city';

    public const TYPE_HOUR = 'hour';

    protected $fillable = [
        'stat_date',
        'scope',
        'dimension_type',
        'dimension_key',
        'hits',
        'visitors',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'hits' => 'integer',
            'visitors' => 'integer',
        ];
    }
}
