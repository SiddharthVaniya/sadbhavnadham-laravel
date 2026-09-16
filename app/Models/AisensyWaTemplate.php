<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AisensyWaTemplate extends Model
{
    public const HEADER_TEXT = 'TEXT';

    public const HEADER_IMAGE = 'IMAGE';

    public const HEADER_VIDEO = 'VIDEO';

    public const HEADER_DOCUMENT = 'DOCUMENT';

    public const HEADER_LOCATION = 'LOCATION';

    public const HEADER_CAROUSEL = 'CAROUSEL';

    public const HEADER_LIMITED_TIME_OFFER = 'LIMITED TIME OFFER';

    /**
     * AiSensy dashboard template types (Manage → Template Message).
     *
     * @return list<string>
     */
    public static function headerTypes(): array
    {
        return [
            self::HEADER_TEXT,
            self::HEADER_IMAGE,
            self::HEADER_VIDEO,
            self::HEADER_DOCUMENT,
            self::HEADER_LOCATION,
            self::HEADER_CAROUSEL,
            self::HEADER_LIMITED_TIME_OFFER,
        ];
    }

    protected $fillable = [
        'aisensy_account_id',
        'external_id',
        'name',
        'language',
        'status',
        'category',
        'header_type',
        'body_preview',
        'param_count',
        'components_json',
        'live_campaign_name',
        'is_manual',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'components_json' => 'array',
        'param_count' => 'integer',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AisensyAccount::class, 'aisensy_account_id');
    }

    public function campaignRuns(): HasMany
    {
        return $this->hasMany(WhatsappCampaignRun::class);
    }

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === 'APPROVED';
    }

    public function requiresMediaHeader(): bool
    {
        return in_array($this->normalizedHeaderType(), [
            self::HEADER_IMAGE,
            self::HEADER_VIDEO,
            self::HEADER_DOCUMENT,
            self::HEADER_LIMITED_TIME_OFFER,
        ], true);
    }

    public function requiresLocationHeader(): bool
    {
        return $this->normalizedHeaderType() === self::HEADER_LOCATION;
    }

    public function normalizedHeaderType(): string
    {
        $type = strtoupper(trim((string) $this->header_type));
        $type = preg_replace('/\s+/', ' ', $type) ?: '';
        $type = str_replace('_', ' ', $type);

        return match ($type) {
            'FILE' => self::HEADER_DOCUMENT,
            'LTO', 'LIMITEDTIMEOFFER' => self::HEADER_LIMITED_TIME_OFFER,
            '' => self::HEADER_TEXT,
            default => in_array($type, self::headerTypes(), true) ? $type : $type,
        };
    }

    public function resolvedCampaignName(): ?string
    {
        $name = trim((string) ($this->live_campaign_name ?: $this->name));

        return $name !== '' ? $name : null;
    }
}
