<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaPixel extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'pixel_id',
        'access_token',
        'is_active',
        'send_purchase',
        'send_initiate_checkout',
        'test_event_code',
        'last_event_at',
        'last_event_status',
        'last_event_error',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_active' => 'boolean',
            'send_purchase' => 'boolean',
            'send_initiate_checkout' => 'boolean',
            'last_event_at' => 'datetime',
        ];
    }

    public function eventLogs(): HasMany
    {
        return $this->hasMany(MetaCapiEventLog::class);
    }

    public function hasAccessToken(): bool
    {
        return filled($this->access_token);
    }

    public function normalizedPixelId(): string
    {
        return trim((string) $this->pixel_id);
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
