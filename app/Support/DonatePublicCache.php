<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class DonatePublicCache
{
    public const TTL_SECONDS = 120;

    private const VERSION_KEY = 'donate.api.cache_version';

    public static function remember(string $name, callable $callback): mixed
    {
        return Cache::remember(
            'donate.api.v'.self::version().'.'.$name,
            self::TTL_SECONDS,
            $callback
        );
    }

    public static function flush(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 2);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }

    private static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
