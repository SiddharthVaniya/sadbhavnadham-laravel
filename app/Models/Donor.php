<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Donor extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'date_of_birth',
        'pan_number',
        'address',
        'pincode',
        'city',
        'state',
        'country',
        'country_code',
        'consent_indian_citizen',
        'whatsapp_opt_out',
        'whatsapp_opted_out_at',
        'last_donated_at',
        'birthday_whatsapp_sent_on',
        'owner_user_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'consent_indian_citizen' => 'boolean',
        'whatsapp_opt_out' => 'boolean',
        'whatsapp_opted_out_at' => 'datetime',
        'last_donated_at' => 'datetime',
        'birthday_whatsapp_sent_on' => 'date',
    ];

    public function donationOrders(): HasMany
    {
        return $this->hasMany(DonationOrder::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DonationSubscription::class);
    }

    public function paidDonationOrders(): HasMany
    {
        return $this->hasMany(DonationOrder::class)->paid();
    }

    public function latestDonationOrder(): HasOne
    {
        return $this->hasOne(DonationOrder::class)->latestOfMany('created_at');
    }

    public function latestPaidDonationOrder(): HasOne
    {
        return $this->hasOne(DonationOrder::class)
            ->ofMany(
                ['created_at' => 'max', 'id' => 'max'],
                fn ($query) => $query->where('status', DonationOrder::STATUS_PAID),
            );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DonorNote::class)->latest('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DonorTask::class)->latest('id');
    }

    public function openTasks(): HasMany
    {
        return $this->hasMany(DonorTask::class)->open()->orderBy('due_at');
    }

    public function linkedDonationOrdersQuery(): Builder
    {
        return DonationOrder::query()
            ->where(function (Builder $query): void {
                $query->where('donor_id', $this->id)
                    ->orWhere(function (Builder $legacyQuery): void {
                        $legacyQuery
                            ->whereNull('donor_id')
                            ->where('donor_email', $this->email)
                            ->where('donor_phone', $this->phone);
                    });
            });
    }

    public function hasLinkedDonationOrders(): bool
    {
        return $this->linkedDonationOrdersQuery()->exists();
    }

    public static function resolveFromDonationSnapshot(array $snapshot): self
    {
        $email = mb_strtolower(trim((string) ($snapshot['donor_email'] ?? '')));
        $phone = trim((string) ($snapshot['donor_phone'] ?? ''));

        return self::updateOrCreate(
            [
                'email' => $email,
                'phone' => $phone,
            ],
            [
                'name' => (string) ($snapshot['donor_name'] ?? ''),
                'date_of_birth' => $snapshot['date_of_birth'] ?? null,
                'pan_number' => $snapshot['pan_number'] ?? null,
                'address' => $snapshot['address'] ?? null,
                'pincode' => $snapshot['pincode'] ?? null,
                'city' => $snapshot['city'] ?? null,
                'state' => $snapshot['state'] ?? null,
                'country' => (string) ($snapshot['country'] ?? 'INDIA'),
                'country_code' => (string) ($snapshot['donor_country_code'] ?? 'IN'),
                'consent_indian_citizen' => (bool) ($snapshot['consent_indian_citizen'] ?? false),
                'last_donated_at' => now(),
            ]
        );
    }
}
