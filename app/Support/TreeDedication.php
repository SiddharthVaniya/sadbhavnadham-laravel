<?php

namespace App\Support;

class TreeDedication
{
    public const NAME_REGEX = '/^(?=.*\p{L})[\p{L}\p{M}\s\'.\-()]+$/u';

    /**
     * @return list<string>
     */
    public static function causeSlugs(): array
    {
        $slugs = config('donation.tree_dedication_causes', ['tree-plantation']);

        if (! is_array($slugs)) {
            return ['tree-plantation'];
        }

        return array_values(array_filter(
            array_map(static fn ($slug) => is_string($slug) ? trim($slug) : '', $slugs),
            static fn (string $slug) => $slug !== ''
        ));
    }

    public static function collectsForSlug(?string $slug): bool
    {
        if (! is_string($slug) || $slug === '') {
            return false;
        }

        return in_array($slug, self::causeSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public static function normalize(mixed $names, int $quantity): array
    {
        $quantity = max(1, min(100, $quantity));
        $raw = is_array($names) ? array_values($names) : [];
        $normalized = [];

        for ($index = 0; $index < $quantity; $index++) {
            $value = $raw[$index] ?? '';
            $normalized[] = is_string($value) ? trim($value) : '';
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $names
     */
    public static function hasAny(array $names): bool
    {
        foreach ($names as $name) {
            if ($name !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>|null
     */
    public static function forItemMeta(mixed $names, int $quantity): ?array
    {
        $normalized = self::normalize($names, $quantity);

        return self::hasAny($normalized) ? $normalized : null;
    }

    /**
     * @return list<string>
     */
    public static function displayLines(mixed $names): array
    {
        if (! is_array($names)) {
            return [];
        }

        $lines = [];

        foreach (array_values($names) as $index => $name) {
            $trimmed = is_string($name) ? trim($name) : '';

            if ($trimmed === '') {
                continue;
            }

            $lines[] = 'Tree '.($index + 1).': '.$trimmed;
        }

        return $lines;
    }
}
