<?php

namespace App\Models;

use App\Support\DonatePublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cause extends Model
{
    use HasFactory;

    public const SLUG_DAILY_NEEDS = 'daily-needs';

    protected $table = 'causes';

    protected $fillable = [
        'slug',
        'icon_uri',
        'icon_uri_active',
        'title',
        'excerpt',
        'description',
        'images',
        'hero_image',
        'details',
        'allow_custom_amount',
        'allow_recurring',
        'allow_weekly_recurring',
        'pan_required',
        'default_amount',
        'default_title',
        'cta_text',
        'contact_heading',
        'contact_address',
        'contact_phone',
        'contact_email',
        'sort_order',
        'is_active',
        'aisensy_account_id',
        'aisensy_payment_link_campaign',
        'aisensy_thank_you_campaign',
        'aisensy_certificate_campaign',
        'aisensy_receipt_campaign',
        'certificate_template',
        'certificate_template_english',
        'aisensy_thank_you_image',
        'aisensy_thank_you_message_mode',
        'aisensy_thank_you_message_template',
        'aisensy_thank_you_include_name',
        'aisensy_thank_you_include_amount',
        'aisensy_thank_you_include_cause',
        'aisensy_thank_you_include_receipt',
        'aisensy_send_thank_you',
        'aisensy_send_certificate',
        'aisensy_send_receipt',
    ];

    protected $casts = [
        'images' => 'array',
        'details' => 'array',
        'allow_custom_amount' => 'boolean',
        'allow_recurring' => 'boolean',
        'allow_weekly_recurring' => 'boolean',
        'pan_required' => 'boolean',
        'is_active' => 'boolean',
        'default_amount' => 'decimal:2',
        'aisensy_thank_you_include_name' => 'boolean',
        'aisensy_thank_you_include_amount' => 'boolean',
        'aisensy_thank_you_include_cause' => 'boolean',
        'aisensy_thank_you_include_receipt' => 'boolean',
        'aisensy_send_thank_you' => 'boolean',
        'aisensy_send_certificate' => 'boolean',
        'aisensy_send_receipt' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DonatePublicCache::flush());
        static::deleted(fn () => DonatePublicCache::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function packages(): HasMany
    {
        return $this->hasMany(CausePackage::class);
    }

    /**
     * Daily Needs has a dedicated donate page, so it is omitted from cause grids/switchers.
     */
    public function scopeListedOnDonateIndex(Builder $query): Builder
    {
        return $query->where('slug', '!=', self::SLUG_DAILY_NEEDS);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DonationSubscription::class);
    }

    public function aisensyAccount()
    {
        return $this->belongsTo(AisensyAccount::class);
    }

    public function hasContactCard(): bool
    {
        return trim((string) ($this->contact_address ?? '')) !== ''
            || trim((string) ($this->contact_phone ?? '')) !== ''
            || trim((string) ($this->contact_email ?? '')) !== '';
    }

    public function allowsMonthlyRecurring(): bool
    {
        return (bool) $this->allow_recurring;
    }

    public function allowsWeeklyRecurring(): bool
    {
        return (bool) $this->allow_weekly_recurring;
    }

    /**
     * @return list<string>
     */
    public function allowedRecurringFrequencies(): array
    {
        $frequencies = [];

        if ($this->allowsMonthlyRecurring()) {
            $frequencies[] = \App\Support\SubscriptionFrequency::MONTHLY;
        }

        if ($this->allowsWeeklyRecurring()) {
            $frequencies[] = \App\Support\SubscriptionFrequency::WEEKLY;
        }

        return $frequencies;
    }

    public function allowsAnyRecurring(): bool
    {
        return $this->allowsMonthlyRecurring() || $this->allowsWeeklyRecurring();
    }

    public function hasAisensy(): bool
    {
        return ! is_null($this->aisensy_account_id)
            && $this->aisensyAccount?->is_active;
    }

    public function shouldSendThankYouWhatsApp(): bool
    {
        return $this->hasAisensy()
            && (bool) ($this->aisensy_send_thank_you ?? true);
    }

    public function shouldSendCertificateWhatsApp(): bool
    {
        return $this->hasAisensy()
            && (bool) ($this->aisensy_send_certificate ?? true);
    }

    public function shouldSendReceiptWhatsApp(): bool
    {
        return $this->hasAisensy()
            && filled($this->receiptCampaignName())
            && (bool) ($this->aisensy_send_receipt ?? true);
    }

    public function receiptCampaignName(): ?string
    {
        $campaign = trim((string) ($this->aisensy_receipt_campaign ?? ''));

        if ($campaign !== '') {
            return $campaign;
        }

        $default = trim((string) config('services.aisensy.receipt_campaign', ''));

        return $default !== '' ? $default : null;
    }
}
