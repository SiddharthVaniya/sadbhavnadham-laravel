<?php

namespace App\Services\Danamojo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DanamojoClient
{
    /**
     * Fetch all donation rows between two dates (inclusive), handling pagination.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchDonations(Carbon $fromDate, Carbon $toDate): array
    {
        $apiKey = (string) config('danamojo.api_key_secret');

        if ($apiKey === '') {
            throw new RuntimeException('Danamojo API key is not configured (DANAMOJO_API_KEY_SECRET).');
        }

        $pageSize = max(1, min(100, (int) config('danamojo.page_size', 100)));
        $startRow = 0;
        $all = [];

        do {
            $payload = $this->requestDonationsPage($apiKey, $fromDate, $toDate, $pageSize, $startRow);
            $rows = $payload['data'] ?? [];

            if (! is_array($rows)) {
                throw new RuntimeException('Danamojo API returned an unexpected data payload.');
            }

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $count = count($rows);
            $startRow += $count;
        } while ($count >= $pageSize);

        return $all;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestDonationsPage(
        string $apiKey,
        Carbon $fromDate,
        Carbon $toDate,
        int $rows,
        int $startRow,
    ): array {
        $url = config('danamojo.base_url').'/donation/v1.0/details';

        try {
            $response = Http::withHeaders([
                'danamojo_api_key_secret' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout((int) config('danamojo.timeout', 30))
                ->retry(3, 1000)
                ->get($url, [
                    'fromDate' => $fromDate->toDateString(),
                    'toDate' => $toDate->toDateString(),
                    'rows' => $rows,
                    'startRow' => $startRow,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Danamojo API connection failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('Danamojo API rejected the API key (unauthorized).');
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Danamojo API request failed with HTTP '.$response->status().': '.$response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Danamojo API returned non-JSON content.');
        }

        if ((int) ($json['status'] ?? 0) !== 1) {
            throw new RuntimeException(
                'Danamojo API status was not successful: '.json_encode($json)
            );
        }

        return $json;
    }
}
