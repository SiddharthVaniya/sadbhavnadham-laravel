<?php

namespace App\Services;

use App\Models\AnalyticsDailyCause;
use App\Models\AnalyticsDailyDimension;
use App\Models\AnalyticsDailyStat;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSessionDay;
use App\Support\AdminAnalyticsData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRollupService
{
    /**
     * @return array{days: int, from: string, to: string}
     */
    public function rollupRange(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->startOfDay();
        $days = 0;

        for ($cursor = $fromDay->copy(); $cursor->lte($toDay); $cursor->addDay()) {
            $this->rollupDay($cursor);
            $days++;
        }

        return [
            'days' => $days,
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
        ];
    }

    public function rollupDay(Carbon $day): void
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $statDate = $start->toDateString();

        DB::transaction(function () use ($statDate, $start, $end): void {
            AnalyticsDailyStat::query()->whereDate('stat_date', $statDate)->delete();
            AnalyticsDailyCause::query()->whereDate('stat_date', $statDate)->delete();
            AnalyticsDailyDimension::query()->whereDate('stat_date', $statDate)->delete();
            AnalyticsSessionDay::query()->whereDate('stat_date', $statDate)->delete();

            $this->rollupScope($statDate, $start, $end, AnalyticsDailyStat::SCOPE_ALL);
            $this->rollupScope($statDate, $start, $end, AnalyticsDailyStat::SCOPE_IN);
        });
    }

    public function earliestEventDate(): ?Carbon
    {
        $min = AnalyticsEvent::query()->min('created_at');

        return $min ? Carbon::parse($min)->startOfDay() : null;
    }

    private function rollupScope(string $statDate, Carbon $start, Carbon $end, string $scope): void
    {
        $events = $this->eventsForDay($start, $end, $scope);

        if ($events->isEmpty()) {
            AnalyticsDailyStat::query()->create([
                'stat_date' => $statDate,
                'scope' => $scope,
            ]);

            return;
        }

        $visitEvents = $events->whereIn('event_type', [
            AnalyticsEvent::TYPE_VISIT_HOME,
            AnalyticsEvent::TYPE_VISIT_CAUSE,
        ]);

        $causeVisits = $events->where('event_type', AnalyticsEvent::TYPE_VISIT_CAUSE);
        $homeVisits = $events->where('event_type', AnalyticsEvent::TYPE_VISIT_HOME);
        $checkouts = $events->where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED);
        $paid = $events->where('event_type', AnalyticsEvent::TYPE_DONATION_PAID);
        $failed = $events->where('event_type', AnalyticsEvent::TYPE_DONATION_FAILED);

        $sessionMap = [];

        foreach ($visitEvents as $event) {
            $sessionId = $event->session_id;

            if (! is_string($sessionId) || $sessionId === '') {
                continue;
            }

            if (! isset($sessionMap[$sessionId])) {
                $sessionMap[$sessionId] = false;
            }

            if ($event->event_type === AnalyticsEvent::TYPE_VISIT_CAUSE) {
                $sessionMap[$sessionId] = true;
            }
        }

        $this->insertSessionDays($statDate, $scope, $sessionMap);

        $uniqueVisitors = count($sessionMap);
        $uniqueCauseVisitors = collect($sessionMap)->filter()->count();

        $countriesReached = $visitEvents
            ->pluck('country_code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->count();

        AnalyticsDailyStat::query()->create([
            'stat_date' => $statDate,
            'scope' => $scope,
            'home_visits' => $homeVisits->count(),
            'cause_views' => $causeVisits->count(),
            'unique_visitors' => $uniqueVisitors,
            'unique_cause_visitors' => $uniqueCauseVisitors,
            'checkouts_started' => $checkouts->count(),
            'donations_paid' => $paid->count(),
            'donations_failed' => $failed->count(),
            'tracked_revenue' => (float) $paid->sum(fn ($event) => (float) $event->amount),
            'countries_reached' => $countriesReached,
        ]);

        $this->rollupCauses($statDate, $scope, $events);
        $this->rollupReferrers($statDate, $scope, $visitEvents);
        $this->rollupDevices($statDate, $scope, $visitEvents);
        $this->rollupUtm($statDate, $scope, $events);
        $this->rollupGeo($statDate, $scope, $visitEvents);
        $this->rollupHours($statDate, $scope, $events);
    }

    /**
     * @return Collection<int, AnalyticsEvent>
     */
    private function eventsForDay(Carbon $start, Carbon $end, string $scope): Collection
    {
        $query = AnalyticsEvent::query()->whereBetween('created_at', [$start, $end]);

        if ($scope === AnalyticsDailyStat::SCOPE_IN) {
            $query->where(function ($inner): void {
                $inner->where('country_code', 'IN')
                    ->orWhereNull('country_code');
            });
        }

        return $query->get([
            'event_type',
            'session_id',
            'cause_id',
            'referrer',
            'device_type',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'country_code',
            'country_name',
            'region_name',
            'city',
            'amount',
            'created_at',
        ]);
    }

    /**
     * @param  array<string, bool>  $sessionMap
     */
    private function insertSessionDays(string $statDate, string $scope, array $sessionMap): void
    {
        if ($sessionMap === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($sessionMap as $sessionId => $visitedCause) {
            $rows[] = [
                'stat_date' => $statDate,
                'scope' => $scope,
                'session_id' => $sessionId,
                'visited_cause' => $visitedCause,
                'created_at' => $now,
            ];

            if (count($rows) >= 500) {
                AnalyticsSessionDay::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            AnalyticsSessionDay::query()->insert($rows);
        }
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     */
    private function rollupCauses(string $statDate, string $scope, Collection $events): void
    {
        $byCause = [];

        foreach ($events as $event) {
            if (! $event->cause_id) {
                continue;
            }

            $causeId = (int) $event->cause_id;

            if (! isset($byCause[$causeId])) {
                $byCause[$causeId] = [
                    'views' => 0,
                    'sessions' => [],
                    'checkouts' => 0,
                    'paid' => 0,
                    'revenue' => 0.0,
                ];
            }

            if ($event->event_type === AnalyticsEvent::TYPE_VISIT_CAUSE) {
                $byCause[$causeId]['views']++;

                if (is_string($event->session_id) && $event->session_id !== '') {
                    $byCause[$causeId]['sessions'][$event->session_id] = true;
                }
            }

            if ($event->event_type === AnalyticsEvent::TYPE_CHECKOUT_STARTED) {
                $byCause[$causeId]['checkouts']++;
            }

            if ($event->event_type === AnalyticsEvent::TYPE_DONATION_PAID) {
                $byCause[$causeId]['paid']++;
                $byCause[$causeId]['revenue'] += (float) $event->amount;
            }
        }

        $rows = [];
        $now = now();

        foreach ($byCause as $causeId => $data) {
            $rows[] = [
                'stat_date' => $statDate,
                'scope' => $scope,
                'cause_id' => $causeId,
                'views' => $data['views'],
                'unique_visitors' => count($data['sessions']),
                'checkouts' => $data['checkouts'],
                'paid' => $data['paid'],
                'revenue' => $data['revenue'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            AnalyticsDailyCause::query()->insert($rows);
        }
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $visitEvents
     */
    private function rollupReferrers(string $statDate, string $scope, Collection $visitEvents): void
    {
        $grouped = [];

        foreach ($visitEvents as $event) {
            $host = AdminAnalyticsData::normalizeReferrerHost($event->referrer);

            if ($host === null) {
                continue;
            }

            $grouped[$host] = ($grouped[$host] ?? 0) + 1;
        }

        $this->insertDimensions($statDate, $scope, AnalyticsDailyDimension::TYPE_REFERRER, $grouped);
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $visitEvents
     */
    private function rollupDevices(string $statDate, string $scope, Collection $visitEvents): void
    {
        $grouped = [];

        foreach ($visitEvents as $event) {
            if (! is_string($event->session_id) || $event->session_id === '') {
                continue;
            }

            $device = strtolower(trim((string) ($event->device_type ?: 'unknown')));
            $grouped[$device][$event->session_id] = true;
        }

        $rows = [];

        foreach ($grouped as $device => $sessions) {
            $rows[$device] = [
                'hits' => count($sessions),
                'visitors' => count($sessions),
            ];
        }

        $this->insertDimensionRows($statDate, $scope, AnalyticsDailyDimension::TYPE_DEVICE, $rows);
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     */
    private function rollupUtm(string $statDate, string $scope, Collection $events): void
    {
        $grouped = [];

        foreach ($events as $event) {
            $source = trim((string) $event->utm_source);

            if ($source === '') {
                continue;
            }

            $key = implode('||', [
                $source,
                trim((string) ($event->utm_medium ?: '')),
                trim((string) ($event->utm_campaign ?: '')),
            ]);

            $grouped[$key] = ($grouped[$key] ?? 0) + 1;
        }

        $this->insertDimensions($statDate, $scope, AnalyticsDailyDimension::TYPE_UTM, $grouped);
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $visitEvents
     */
    private function rollupGeo(string $statDate, string $scope, Collection $visitEvents): void
    {
        $countries = [];
        $regions = [];
        $cities = [];

        foreach ($visitEvents as $event) {
            if (! is_string($event->session_id) || $event->session_id === '') {
                continue;
            }

            $countryName = trim((string) ($event->country_name ?: ''));
            $countryCode = trim((string) ($event->country_code ?: ''));
            $regionName = trim((string) ($event->region_name ?: ''));
            $city = trim((string) ($event->city ?: ''));

            if ($countryName !== '') {
                $key = $this->canonicalDimensionKey($countryCode.'||'.$countryName);
                $countries[$key]['hits'] = ($countries[$key]['hits'] ?? 0) + 1;
                $countries[$key]['sessions'][$event->session_id] = true;
            }

            if ($regionName !== '') {
                $key = $this->canonicalDimensionKey($regionName.'||'.$countryName);
                $regions[$key]['hits'] = ($regions[$key]['hits'] ?? 0) + 1;
                $regions[$key]['sessions'][$event->session_id] = true;
            }

            if ($city !== '') {
                $key = $this->canonicalDimensionKey($city.'||'.$regionName.'||'.$countryName);
                $cities[$key]['hits'] = ($cities[$key]['hits'] ?? 0) + 1;
                $cities[$key]['sessions'][$event->session_id] = true;
            }
        }

        $this->insertDimensionRows(
            $statDate,
            $scope,
            AnalyticsDailyDimension::TYPE_COUNTRY,
            collect($countries)->mapWithKeys(fn ($data, $key) => [$key => [
                'hits' => $data['hits'],
                'visitors' => count($data['sessions']),
            ]])->all()
        );

        $this->insertDimensionRows(
            $statDate,
            $scope,
            AnalyticsDailyDimension::TYPE_REGION,
            collect($regions)->mapWithKeys(fn ($data, $key) => [$key => [
                'hits' => $data['hits'],
                'visitors' => count($data['sessions']),
            ]])->all()
        );

        $this->insertDimensionRows(
            $statDate,
            $scope,
            AnalyticsDailyDimension::TYPE_CITY,
            collect($cities)->mapWithKeys(fn ($data, $key) => [$key => [
                'hits' => $data['hits'],
                'visitors' => count($data['sessions']),
            ]])->all()
        );
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     */
    private function rollupHours(string $statDate, string $scope, Collection $events): void
    {
        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[(string) $hour] = ['hits' => 0, 'visitors' => 0];
        }

        foreach ($events as $event) {
            $hour = (string) (int) Carbon::parse($event->created_at)->format('G');

            if ($event->event_type === AnalyticsEvent::TYPE_VISIT_HOME
                || $event->event_type === AnalyticsEvent::TYPE_VISIT_CAUSE) {
                $hours[$hour]['hits']++;
            }

            if ($event->event_type === AnalyticsEvent::TYPE_DONATION_PAID) {
                $hours[$hour]['visitors']++;
            }
        }

        $this->insertDimensionRows($statDate, $scope, AnalyticsDailyDimension::TYPE_HOUR, $hours);
    }

    /**
     * @param  array<string, int>  $grouped
     */
    private function insertDimensions(string $statDate, string $scope, string $type, array $grouped): void
    {
        $rows = [];

        foreach ($grouped as $key => $hits) {
            $canonical = $this->canonicalDimensionKey((string) $key);
            $rows[$canonical] = [
                'hits' => ($rows[$canonical]['hits'] ?? 0) + $hits,
                'visitors' => 0,
            ];
        }

        $this->insertDimensionRows($statDate, $scope, $type, $rows);
    }

    /**
     * @param  array<string, array{hits: int, visitors: int}>  $rows
     */
    private function insertDimensionRows(string $statDate, string $scope, string $type, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // Merge keys that PHP treats as distinct but MySQL unicode_ci unique indexes as equal
        // (e.g. Baladīyat ad Dawḩah vs ASCII lookalikes).
        $merged = [];

        foreach ($rows as $key => $data) {
            $canonical = $this->canonicalDimensionKey((string) $key);

            if (! isset($merged[$canonical])) {
                $merged[$canonical] = [
                    'hits' => 0,
                    'visitors' => 0,
                ];
            }

            $merged[$canonical]['hits'] += (int) $data['hits'];
            $merged[$canonical]['visitors'] += (int) $data['visitors'];
        }

        $now = now();
        $insert = [];

        foreach ($merged as $key => $data) {
            $insert[] = [
                'stat_date' => $statDate,
                'scope' => $scope,
                'dimension_type' => $type,
                'dimension_key' => $key,
                'hits' => $data['hits'],
                'visitors' => $data['visitors'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($insert) >= 500) {
                AnalyticsDailyDimension::query()->upsert(
                    $insert,
                    ['stat_date', 'scope', 'dimension_type', 'dimension_key'],
                    ['hits', 'visitors', 'updated_at']
                );
                $insert = [];
            }
        }

        if ($insert !== []) {
            AnalyticsDailyDimension::query()->upsert(
                $insert,
                ['stat_date', 'scope', 'dimension_type', 'dimension_key'],
                ['hits', 'visitors', 'updated_at']
            );
        }
    }

    /**
     * Collapse Unicode variants so MySQL utf8mb4_*_ci unique keys do not collide.
     */
    private function canonicalDimensionKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return $key;
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($key, \Normalizer::FORM_KD);

            if (is_string($normalized) && $normalized !== '') {
                $key = $normalized;
            }

            $stripped = preg_replace('/\p{Mn}+/u', '', $key);

            if (is_string($stripped)) {
                $key = $stripped;
            }
        }

        $key = mb_strtolower($key, 'UTF-8');
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return trim($key);
    }
}
