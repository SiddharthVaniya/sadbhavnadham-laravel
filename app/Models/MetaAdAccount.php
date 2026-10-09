<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'app_id',
        'app_secret',
        'access_token',
        'ad_account_id',
        'is_active',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'app_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }

    public function spendDaily(): HasMany
    {
        return $this->hasMany(MetaAdSpendDaily::class);
    }

    public function hasAppSecret(): bool
    {
        return filled($this->app_secret);
    }

    public function hasAccessToken(): bool
    {
        return filled($this->access_token);
    }

    /**
     * Normalize stored id to digits only (no act_ prefix).
     */
    public function normalizedAdAccountId(): string
    {
        $id = trim((string) $this->ad_account_id);

        if (str_starts_with(strtolower($id), 'act_')) {
            return substr($id, 4);
        }

        return $id;
    }

    public function graphActId(): string
    {
        return 'act_'.$this->normalizedAdAccountId();
    }

    public function fromEncryptedString($value)
    {
        try {
            return parent::fromEncryptedString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
