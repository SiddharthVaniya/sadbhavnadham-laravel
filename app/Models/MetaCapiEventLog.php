<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaCapiEventLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'meta_pixel_id',
        'donation_order_id',
        'event_name',
        'event_id',
        'status',
        'http_status',
        'error_message',
        'response_json',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function pixel(): BelongsTo
    {
        return $this->belongsTo(MetaPixel::class, 'meta_pixel_id');
    }

    public function donationOrder(): BelongsTo
    {
        return $this->belongsTo(DonationOrder::class);
    }
}
