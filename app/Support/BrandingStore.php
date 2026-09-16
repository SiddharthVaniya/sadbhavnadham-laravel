<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BrandingStore
{
    public const CACHE_KEY = 'branding.overrides';

    public const SETTING_KEY = 'branding_overrides';

    /**
     * @return array<string, mixed>
     */
    public static function envDefaults(): array
    {
        static $defaults = null;

        $defaults ??= require config_path('branding.php');

        return is_array($defaults) ? $defaults : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function overrides(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $raw = Setting::query()
                ->where('key', self::SETTING_KEY)
                ->value('value');

            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function merged(): array
    {
        return array_replace_recursive(self::envDefaults(), self::overrides());
    }

    public static function applyToConfig(): void
    {
        config(['branding' => self::merged()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function persist(array $overrides): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $clean = self::pruneEmpty($overrides);

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'label' => 'Branding configuration',
                'description' => 'Managed from Admin → Branding. Overrides .env defaults.',
                'group' => 'branding',
            ]
        );

        self::flush();
        DonatePublicCache::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function pruneEmpty(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = self::pruneEmpty($value);

                if ($nested !== []) {
                    $result[$key] = $nested;
                }

                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            if ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
