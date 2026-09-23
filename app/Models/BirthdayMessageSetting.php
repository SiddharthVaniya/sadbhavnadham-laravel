<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayMessageSetting extends Model
{
    protected $fillable = [
        'enabled',
        'aisensy_account_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();

        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'enabled' => false,
            'aisensy_account_id' => null,
        ]);
    }
}
