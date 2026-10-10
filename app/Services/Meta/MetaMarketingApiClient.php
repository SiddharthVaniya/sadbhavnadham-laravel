<?php

namespace App\Services\Meta;

use App\Models\MetaAdAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaMarketingApiClient
{
    private const GRAPH_VERSION = 'v21.0';

    /**
     * Fetch daily ad-level Insights rows for [since, until] inclusive.
     *
     * @return list<array{
     *     spend_date: string,
     *     campaign_id: ?string,
     *     campaign_name: ?string,
     *     adset_id: ?string,
     *     adset_name: ?string,
     *     ad_id: string,
     *     ad_name: ?string,
     *     spend_amount: float,
     *     impressions: int,
     *     clicks: int,
     *     reach: int,
     *     inline_link_clicks: int,
     *     currency: ?string
     * }>
     */
    public function fetchAdInsights(MetaAdAccount $account, string $since, string $until): array
    {
        $token = (string) $account->access_token;

        if ($token === '') {
            throw new RuntimeException('Meta access token is missing.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/insights',
            self::GRAPH_VERSION,
            $account->graphActId(),
        );

        $params = [
            'level' => 'ad',
            'time_increment' => 1,
            'time_range' => json_encode([
                'since' => $since,
                'until' => $until,
            ], JSON_THROW_ON_ERROR),
            'fields' => implode(',', [
                'ad_id',
                'ad_name',
                'adset_id',
                'adset_name',
                'campaign_id',
                'campaign_name',
                'spend',
                'impressions',
                'clicks',
                'reach',
                'inline_link_clicks',
                'account_currency',
                'date_start',
            ]),
            'limit' => 500,
            'access_token' => $token,
        ];

        $rows = [];
        $nextUrl = $url.'?'.http_build_query($params);

        while ($nextUrl) {
            try {
                $response = Http::timeout(60)->acceptJson()->get($nextUrl);
                $response->throw();
            } catch (RequestException $e) {
                $body = $e->response?->json();
                $message = is_array($body)
                    ? (string) data_get($body, 'error.message', $e->getMessage())
                    : $e->getMessage();

                Log::warning('meta.insights.http_error', [
                    'account_id' => $account->id,
                    'ad_account_id' => $account->normalizedAdAccountId(),
                    'message' => $message,
                    'status' => $e->response?->status(),
                ]);

                throw new RuntimeException('Meta Insights API error: '.$message, 0, $e);
            }

            $payload = $response->json();
            $data = is_array($payload) ? ($payload['data'] ?? []) : [];

            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $adId = trim((string) ($item['ad_id'] ?? ''));

                if ($adId === '') {
                    continue;
                }

                $rows[] = [
                    'spend_date' => (string) ($item['date_start'] ?? $since),
                    'campaign_id' => $this->nullableString($item['campaign_id'] ?? null),
                    'campaign_name' => $this->nullableString($item['campaign_name'] ?? null),
                    'adset_id' => $this->nullableString($item['adset_id'] ?? null),
                    'adset_name' => $this->nullableString($item['adset_name'] ?? null),
                    'ad_id' => $adId,
                    'ad_name' => $this->nullableString($item['ad_name'] ?? null),
                    'spend_amount' => (float) ($item['spend'] ?? 0),
                    'impressions' => (int) ($item['impressions'] ?? 0),
                    'clicks' => (int) ($item['clicks'] ?? 0),
                    'reach' => (int) ($item['reach'] ?? 0),
                    'inline_link_clicks' => (int) ($item['inline_link_clicks'] ?? 0),
                    'currency' => $this->nullableString($item['account_currency'] ?? null),
                ];
            }

            $nextUrl = is_array($payload)
                ? (isset($payload['paging']['next']) ? (string) $payload['paging']['next'] : null)
                : null;
        }

        return $rows;
    }

    /**
     * Current Meta effective_status per ad id (matches Ads Manager delivery filter).
     *
     * @param  list<string>  $adIds
     * @return array<string, string>
     */
    public function fetchAdEffectiveStatuses(MetaAdAccount $account, array $adIds): array
    {
        $token = (string) $account->access_token;

        if ($token === '') {
            throw new RuntimeException('Meta access token is missing.');
        }

        $adIds = array_values(array_unique(array_filter(array_map(
            fn (mixed $id) => trim((string) $id),
            $adIds,
        ), fn (string $id) => $id !== '')));

        if ($adIds === []) {
            return [];
        }

        $baseUrl = sprintf(
            'https://graph.facebook.com/%s/%s/ads',
            self::GRAPH_VERSION,
            $account->graphActId(),
        );

        $statuses = [];

        foreach (array_chunk($adIds, 50) as $chunk) {
            $filtering = json_encode([
                [
                    'field' => 'id',
                    'operator' => 'IN',
                    'value' => $chunk,
                ],
            ], JSON_THROW_ON_ERROR);

            $params = [
                'fields' => 'id,effective_status',
                'filtering' => $filtering,
                'limit' => 500,
                'access_token' => $token,
            ];

            $nextUrl = $baseUrl.'?'.http_build_query($params);

            while ($nextUrl) {
                try {
                    $response = Http::timeout(60)->acceptJson()->get($nextUrl);
                    $response->throw();
                } catch (RequestException $e) {
                    $body = $e->response?->json();
                    $message = is_array($body)
                        ? (string) data_get($body, 'error.message', $e->getMessage())
                        : $e->getMessage();

                    throw new RuntimeException('Meta Ads API error: '.$message, 0, $e);
                }

                $payload = $response->json();
                $data = is_array($payload) ? ($payload['data'] ?? []) : [];

                foreach ($data as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $adId = trim((string) ($item['id'] ?? ''));
                    $status = $this->nullableString($item['effective_status'] ?? null);

                    if ($adId !== '' && $status !== null) {
                        $statuses[$adId] = strtoupper($status);
                    }
                }

                $nextUrl = is_array($payload)
                    ? (isset($payload['paging']['next']) ? (string) $payload['paging']['next'] : null)
                    : null;
            }
        }

        return $statuses;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
