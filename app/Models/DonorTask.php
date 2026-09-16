<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorTask extends Model
{
    /** @use HasFactory<\Database\Factories\DonorTaskFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'donor_id',
        'assigned_to',
        'created_by',
        'title',
        'body',
        'status',
        'due_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function markDone(): void
    {
        $this->forceFill([
            'status' => self::STATUS_DONE,
            'completed_at' => now(),
        ])->save();
    }

    public function markCancelled(): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => null,
        ])->save();
    }

    public function reopen(?\DateTimeInterface $dueAt = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_OPEN,
            'completed_at' => null,
            'due_at' => $dueAt ?? $this->due_at,
        ])->save();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
