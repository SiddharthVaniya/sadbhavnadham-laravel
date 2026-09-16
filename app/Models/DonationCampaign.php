<?php

namespace App\Models;

use App\Support\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DonationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'cause_id',
        'cause_package_id',
        'amount',
        'goal_amount',
        'title',
        'headline',
        'subheadline',
        'image',
        'recurring_only',
        'frequency',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'goal_amount' => 'decimal:2',
        'recurring_only' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CausePackage::class, 'cause_package_id');
    }

    public function donationItems(): HasMany
    {
        return $this->hasMany(DonationItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DonationSubscription::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at instanceof Carbon && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at instanceof Carbon && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isReadyForCheckout(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $this->loadMissing(['cause', 'package']);

        if (! $this->cause?->is_active) {
            return false;
        }

        $amount = $this->resolvedAmount();

        if ($amount <= 0 || $this->resolvedTitle() === '') {
            return false;
        }

        if (! $this->recurring_only) {
            return true;
        }

        if (! config('payments.razorpay.subscriptions_enabled', false)) {
            return false;
        }

        $frequency = $this->billingFrequency();

        if ($frequency === SubscriptionFrequency::WEEKLY) {
            if (! $this->cause->allowsWeeklyRecurring()) {
                return false;
            }
        } elseif (! $this->cause->allowsMonthlyRecurring()) {
            return false;
        }

        $package = $this->resolvedPackage();

        if ($package && ! $package->allowsRecurringDonations()) {
            return false;
        }

        $min = (float) config('payments.razorpay.subscription_min_amount', 100);
        $max = (float) config('payments.razorpay.subscription_max_amount', 15000);

        return $amount >= $min && $amount <= $max;
    }

    public function billingFrequency(): string
    {
        if (! $this->recurring_only) {
            return SubscriptionFrequency::MONTHLY;
        }

        $frequency = strtolower(trim((string) ($this->frequency ?: SubscriptionFrequency::MONTHLY)));

        return in_array($frequency, SubscriptionFrequency::campaignOptions(), true)
            ? $frequency
            : SubscriptionFrequency::MONTHLY;
    }

    public function frequencyLabel(): string
    {
        return SubscriptionFrequency::label($this->billingFrequency());
    }

    public function resolvedPackage(): ?CausePackage
    {
        $this->loadMissing('package');

        if ($this->package && $this->package->is_active && $this->package->cause_id === $this->cause_id) {
            return $this->package;
        }

        return null;
    }

    public function resolvedAmount(): float
    {
        $package = $this->resolvedPackage();

        if ($package) {
            return (float) $package->amount;
        }

        return (float) ($this->amount ?? 0);
    }

    public function resolvedTitle(): string
    {
        if (is_string($this->title) && trim($this->title) !== '') {
            return trim($this->title);
        }

        $package = $this->resolvedPackage();

        if ($package) {
            return $package->title;
        }

        if (is_string($this->name) && trim($this->name) !== '') {
            return trim($this->name);
        }

        return 'Monthly Donation';
    }

    public function publicUrl(): string
    {
        return route('donate.campaign', $this->slug, false);
    }

    public function resolvedHeroImage(): ?string
    {
        $this->loadMissing(['cause', 'package']);

        if (is_string($this->image) && trim($this->image) !== '') {
            return $this->image;
        }

        $package = $this->resolvedPackage();

        if (is_string($package?->image) && trim((string) $package->image) !== '') {
            return $package->image;
        }

        $causeImage = $this->cause?->hero_image;

        return is_string($causeImage) && trim($causeImage) !== '' ? $causeImage : null;
    }
}
