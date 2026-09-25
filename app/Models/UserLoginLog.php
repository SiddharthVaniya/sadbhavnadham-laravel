<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_BLOCKED_FINGERPRINT = 'blocked_fingerprint';

    public const STATUS_FAILED_CREDENTIALS = 'failed_credentials';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'fingerprint',
        'fingerprint_matched',
        'status',
        'ip_address',
        'country',
        'region',
        'city',
        'location',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'asn',
        'isp',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fingerprint_matched' => 'boolean',
            'created_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'asn' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
