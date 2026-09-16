<?php

namespace App\Models;

use App\Support\DonatePublicCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CausePackage extends Model
{
    use HasFactory;

    protected $table = 'cause_packages';

    protected $fillable = [
        'cause_id',
        'title',
        'amount',
        'image',
        'meta',
        'sort_order',
        'is_active',
        'is_default',
        'allow_recurring',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'allow_recurring' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DonatePublicCache::flush());
        static::deleted(fn () => DonatePublicCache::flush());
    }

    public function allowsRecurringDonations(): bool
    {
        if (! $this->allow_recurring) {
            return false;
        }

        $this->loadMissing('cause');

        return (bool) ($this->cause?->allow_recurring ?? false);
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }

    public function razorpayPlans(): HasMany
    {
        return $this->hasMany(RazorpayPlan::class, 'cause_package_id');
    }
}
