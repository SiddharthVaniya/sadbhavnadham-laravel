<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsappCampaignRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'aisensy_account_id',
        'aisensy_wa_template_id',
        'name',
        'live_campaign_name',
        'status',
        'filters_json',
        'param_map_json',
        'media_path',
        'media_filename',
        'location_json',
        'audience_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'delivery_sent_count',
        'delivery_delivered_count',
        'delivery_read_count',
        'delivery_failed_count',
        'delivery_synced_at',
        'delivery_stats_json',
        'created_by',
        'started_at',
        'finished_at',
        'last_error',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'param_map_json' => 'array',
        'location_json' => 'array',
        'delivery_stats_json' => 'array',
        'audience_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'delivery_sent_count' => 'integer',
        'delivery_delivered_count' => 'integer',
        'delivery_read_count' => 'integer',
        'delivery_failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'delivery_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (empty($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AisensyAccount::class, 'aisensy_account_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AisensyWaTemplate::class, 'aisensy_wa_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappCampaignRecipient::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
