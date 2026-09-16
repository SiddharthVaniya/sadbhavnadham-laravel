<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AisensyAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_key',
        'project_api_password',
        'project_id',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'project_api_password' => 'encrypted',
    ];

    public function causes(): HasMany
    {
        return $this->hasMany(Cause::class);
    }

    public function waTemplates(): HasMany
    {
        return $this->hasMany(AisensyWaTemplate::class);
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function hasProjectApiPassword(): bool
    {
        return filled($this->project_api_password);
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
