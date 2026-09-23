<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BirthdayMessageStep extends Model
{
    public const KIND_MARKETING = 'marketing';

    public const KIND_WARM_WISH = 'warm_wish';

    protected $fillable = [
        'days_before',
        'kind',
        'enabled',
        'campaign_name',
        'image_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sends(): HasMany
    {
        return $this->hasMany(BirthdayMessageSend::class);
    }

    public function isMarketing(): bool
    {
        return $this->kind === self::KIND_MARKETING;
    }

    public function isWarmWish(): bool
    {
        return $this->kind === self::KIND_WARM_WISH;
    }

    public function publicImageUrl(): ?string
    {
        $path = trim((string) $this->image_path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : ltrim($path, '/');

        return Storage::disk('public')->url($relative);
    }
}
