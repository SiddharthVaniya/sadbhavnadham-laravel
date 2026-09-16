<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class LiveVisitorTracker
{
    public const TTL_SECONDS = 90;

    private const INDEX_KEY = 'live_visitors:index';

    private const SESSION_PREFIX = 'live_visitors:session:';

    /**
     * Mark a public-site session as currently browsing.
     *
     * @param  array{
     *     path?: string|null,
     *     city?: string|null,
     *     region?: string|null,
     *     country_code?: string|null,
     *     country_name?: string|null,
     *     lat?: float|null,
     *     lng?: float|null,
     *     label?: string|null,
     *     precision?: string|null
     * }  $attributes
     */
    public function heartbeat(string $sessionId, array $attributes = []): void
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return;
        }

        $now = now()->getTimestamp();
        $existing = Cache::get(self::SESSION_PREFIX.$sessionId);
        $existing = is_array($existing) ? $existing : [];

        $payload = [
            'path' => isset($attributes['path']) && is_string($attributes['path'])
                ? mb_substr($attributes['path'], 0, 200)
                : ($existing['path'] ?? null),
            'city' => $attributes['city'] ?? ($existing['city'] ?? null),
            'region' => $attributes['region'] ?? ($existing['region'] ?? null),
            'country_code' => $attributes['country_code'] ?? ($existing['country_code'] ?? null),
            'country_name' => $attributes['country_name'] ?? ($existing['country_name'] ?? null),
            'lat' => $attributes['lat'] ?? ($existing['lat'] ?? null),
            'lng' => $attributes['lng'] ?? ($existing['lng'] ?? null),
            'label' => $attributes['label'] ?? ($existing['label'] ?? null),
            'precision' => $attributes['precision'] ?? ($existing['precision'] ?? null),
            'seen_at' => $now,
        ];

        Cache::put(self::SESSION_PREFIX.$sessionId, $payload, self::TTL_SECONDS);

        $index = Cache::get(self::INDEX_KEY, []);

        if (! is_array($index)) {
            $index = [];
        }

        $index[$sessionId] = $now;
        $index = $this->pruneIndex($index, $now);

        Cache::put(self::INDEX_KEY, $index, self::TTL_SECONDS + 30);
    }

    public function count(): int
    {
        return count($this->snapshot());
    }

    /**
     * @return list<array{
     *     session_id: string,
     *     path: string|null,
     *     city: string|null,
     *     region: string|null,
     *     country_code: string|null,
     *     country_name: string|null,
     *     lat: float|null,
     *     lng: float|null,
     *     label: string|null,
     *     precision: string|null,
     *     seen_at: int
     * }>
     */
    public function snapshot(): array
    {
        $now = now()->getTimestamp();
        $index = Cache::get(self::INDEX_KEY, []);

        if (! is_array($index)) {
            return [];
        }

        $index = $this->pruneIndex($index, $now);
        Cache::put(self::INDEX_KEY, $index, self::TTL_SECONDS + 30);

        $rows = [];

        foreach ($index as $sessionId => $seenAt) {
            $payload = Cache::get(self::SESSION_PREFIX.$sessionId);

            if (! is_array($payload)) {
                continue;
            }

            $rows[] = [
                'session_id' => (string) $sessionId,
                'path' => isset($payload['path']) ? (string) $payload['path'] : null,
                'city' => isset($payload['city']) ? (string) $payload['city'] : null,
                'region' => isset($payload['region']) ? (string) $payload['region'] : null,
                'country_code' => isset($payload['country_code']) ? (string) $payload['country_code'] : null,
                'country_name' => isset($payload['country_name']) ? (string) $payload['country_name'] : null,
                'lat' => isset($payload['lat']) ? (float) $payload['lat'] : null,
                'lng' => isset($payload['lng']) ? (float) $payload['lng'] : null,
                'label' => isset($payload['label']) ? (string) $payload['label'] : null,
                'precision' => isset($payload['precision']) ? (string) $payload['precision'] : null,
                'seen_at' => (int) ($payload['seen_at'] ?? $seenAt),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['seen_at'] <=> $a['seen_at']);

        return $rows;
    }

    /**
     * @param  array<string, int>  $index
     * @return array<string, int>
     */
    private function pruneIndex(array $index, int $now): array
    {
        $cutoff = $now - self::TTL_SECONDS;

        return array_filter(
            $index,
            fn ($seenAt): bool => is_int($seenAt) || is_numeric($seenAt) ? (int) $seenAt >= $cutoff : false,
        );
    }
}
