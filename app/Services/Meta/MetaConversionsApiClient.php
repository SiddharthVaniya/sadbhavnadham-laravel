<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaConversionsApiClient
{
    private const GRAPH_VERSION = 'v21.0';

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{http_status: int, body: array<string, mixed>|null}
     */
    public function sendEvents(
        string $pixelId,
        string $accessToken,
        array $events,
        ?string $testEventCode = null,
    ): array {
        $pixelId = trim($pixelId);
        $token = trim($accessToken);

        if ($pixelId === '' || $token === '') {
            throw new RuntimeException('Meta pixel id or access token is missing.');
        }

        if ($events === []) {
            throw new RuntimeException('No CAPI events to send.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            self::GRAPH_VERSION,
            $pixelId,
        );

        $payload = [
            'data' => $events,
            'access_token' => $token,
        ];

        if ($testEventCode !== null && trim($testEventCode) !== '') {
            $payload['test_event_code'] = trim($testEventCode);
        }

        try {
            $response = Http::timeout(30)->acceptJson()->post($url, $payload);
            $response->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? (string) data_get($body, 'error.message', $e->getMessage())
                : $e->getMessage();

            Log::warning('meta.capi.http_error', [
                'pixel_id' => $pixelId,
                'message' => $message,
                'status' => $e->response?->status(),
            ]);

            return [
                'http_status' => (int) ($e->response?->status() ?? 0),
                'body' => is_array($body) ? $body : ['error' => ['message' => $message]],
            ];
        }

        $json = $response->json();

        return [
            'http_status' => $response->status(),
            'body' => is_array($json) ? $json : null,
        ];
    }
}
