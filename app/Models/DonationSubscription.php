<?php

namespace App\Models;

use App\Support\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DonationSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_uuid',
        'donor_id',
        'cause_id',
        'cause_package_id',
        'donation_campaign_id',
        'frequency',
        'quantity',
        'unit_amount',
        'total_amount',
        'currency',
        'item_title',
        'razorpay_plan_id',
        'razorpay_subscription_id',
        'status',
        'billing_cycle_count',
        'total_count',
        'started_at',
        'next_charge_at',
        'ended_at',
        'cancelled_at',
        'cancel_reason',
        'donor_name',
        'donor_email',
        'donor_phone',
        'date_of_birth',
        'pan_number',
        'address',
        'pincode',
        'city',
        'state',
        'country',
        'donor_country_code',
        'consent_indian_citizen',
        'consent_recurring',
        'meta',

        'source_channel',
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
        'referrer',
        'landing_path',
        'device_type',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'consent_indian_citizen' => 'boolean',
        'consent_recurring' => 'boolean',
        'unit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'next_charge_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public const STATUS_CREATED = 'created';

    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HALTED = 'halted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (empty($subscription->subscription_uuid)) {
                $subscription->subscription_uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'subscription_uuid';
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CausePackage::class, 'cause_package_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'donation_campaign_id');
    }

    public function donationOrders(): HasMany
    {
        return $this->hasMany(DonationOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED], true);
    }

    public function isPendingCancellation(): bool
    {
        return (bool) ($this->meta['cancel_at_cycle_end'] ?? false)
            && $this->status === self::STATUS_ACTIVE;
    }

    public function canBeCancelled(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_CREATED,
            self::STATUS_AUTHENTICATED,
            self::STATUS_ACTIVE,
            self::STATUS_PENDING,
            self::STATUS_HALTED,
        ], true);
    }

    public function frequencyLabel(): string
    {
        return \App\Support\SubscriptionFrequency::label($this->frequency);
    }

    public function statusLabel(): string
    {
        if ($this->isPendingCancellation()) {
            return 'Cancelling at cycle end';
        }

        return SubscriptionStatus::label($this->status);
    }

    public function isLive(): bool
    {
        if ($this->isCancelled() || $this->isPendingCancellation()) {
            return false;
        }

        return in_array($this->status, SubscriptionStatus::liveStatuses(), true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive($query)
    {
        return $query->whereIn('status', SubscriptionStatus::liveStatuses());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch($query, ?string $term)
    {
        if (! is_string($term) || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($inner) use ($term): void {
            $inner->where('donor_name', 'like', "%{$term}%")
                ->orWhere('donor_email', 'like', "%{$term}%")
                ->orWhere('donor_phone', 'like', "%{$term}%")
                ->orWhere('razorpay_subscription_id', 'like', "%{$term}%")
                ->orWhere('subscription_uuid', 'like', "%{$term}%")
                ->orWhere('item_title', 'like', "%{$term}%")
                ->orWhere('utm_source', 'like', "%{$term}%")
                ->orWhere('utm_campaign', 'like', "%{$term}%")
                ->orWhere('utm_content', 'like', "%{$term}%");
        });
    }
}
