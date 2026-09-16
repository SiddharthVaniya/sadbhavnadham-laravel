<?php

namespace App\Services\AiSensy;

use App\Models\AisensyAccount;
use App\Models\AisensyWaTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AiSensyProjectClient
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWaTemplates(AisensyAccount $account): array
    {
        $this->lastError = null;

        if (! $account->hasProjectApiPassword()) {
            $this->lastError = 'Project API password is missing on this AiSensy account.';

            return [];
        }

        $projectId = trim((string) $account->project_id);

        if ($projectId === '') {
            $this->lastError = 'Project ID is missing on this AiSensy account.';

            return [];
        }

        $direct = $this->listWaTemplatesDirect($account, $projectId);

        if ($direct !== []) {
            return $direct;
        }

        // Live Project API password auth does not expose List WA Template Message
        // (HTML 404 on /wa_template_messages). Fall back to templates embedded in
        // existing API / broadcast campaigns — those responses work with the same auth.
        $fromCampaigns = $this->listWaTemplatesFromCampaigns($account, $projectId);

        if ($fromCampaigns !== []) {
            $this->lastError = null;

            return $fromCampaigns;
        }

        if ($this->lastError === null) {
            $this->lastError = 'No APPROVED templates found on AiSensy campaigns. Add a template name manually, or create an API campaign in AiSensy once so sync can discover its template.';
        }

        Log::warning('AiSensy Project API template list failed', [
            'account_id' => $account->id,
            'error' => $this->lastError,
        ]);

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listWaTemplatesDirect(AisensyAccount $account, string $projectId): array
    {
        $paths = array_values(array_filter([
            config('services.aisensy.project_templates_path'),
            "project/{$projectId}/wa_template_messages",
            "project/{$projectId}/wa-template-messages",
            "project/{$projectId}/wa_template_message",
            "project/{$projectId}/templates",
        ]));

        foreach (array_unique($paths) as $path) {
            try {
                $response = $this->client($account)->get(ltrim((string) $path, '/'));

                if ($response->successful()) {
                    $normalized = $this->normalizeTemplateList($response->json());

                    if ($normalized !== []) {
                        return $normalized;
                    }
                }

                $this->lastError = $this->humanizeHttpError($path, $response);
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return [];
    }

    /**
     * Discover approved WA templates from campaigns already present in AiSensy.
     *
     * @return list<array<string, mixed>>
     */
    private function listWaTemplatesFromCampaigns(AisensyAccount $account, string $projectId): array
    {
        $rows = [];

        try {
            $response = $this->client($account)->get("project/{$projectId}/campaign/api");

            if ($response->successful()) {
                $payload = $response->json();
                $campaigns = is_array($payload)
                    ? ($payload['campaign'] ?? $payload['campaigns'] ?? $payload['data'] ?? $payload)
                    : [];

                if (isset($campaigns['name']) || isset($campaigns['message_payload'])) {
                    $campaigns = [$campaigns];
                }

                if (is_array($campaigns)) {
                    $rows = array_merge($rows, array_values(array_filter($campaigns, 'is_array')));
                }
            } else {
                $this->lastError = $this->humanizeHttpError("project/{$projectId}/campaign/api", $response);
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        foreach (['API', 'BROADCAST', 'GENERAL'] as $campaignType) {
            try {
                $response = $this->client($account)->post("project/{$projectId}/campaigns", [
                    'skip' => 0,
                    'limit' => 200,
                    'campaignType' => $campaignType,
                ]);

                if (! $response->successful()) {
                    continue;
                }

                $payload = $response->json();
                $campaigns = is_array($payload)
                    ? ($payload['campaigns'] ?? $payload['data'] ?? $payload)
                    : [];

                if (is_array($campaigns)) {
                    $rows = array_merge($rows, array_values(array_filter($campaigns, 'is_array')));
                }
            } catch (\Throwable) {
                // Keep going — other campaign sources may still work.
            }
        }

        return $this->normalizeTemplatesFromCampaigns($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<array<string, mixed>>
     */
    private function normalizeTemplatesFromCampaigns(array $campaigns): array
    {
        $templates = [];

        foreach ($campaigns as $campaign) {
            $template = $campaign['message_payload']['template'] ?? null;

            if (! is_array($template)) {
                continue;
            }

            $templates[] = [
                'id' => $template['id'] ?? $template['template_id'] ?? null,
                'name' => $template['name'] ?? $template['label'] ?? null,
                'language' => $template['language'] ?? $template['languageCode'] ?? null,
                'status' => $template['status'] ?? 'APPROVED',
                'category' => $template['category'] ?? null,
                'type' => $template['type'] ?? $template['header_type'] ?? null,
                'body' => $template['text'] ?? $template['body'] ?? $template['sample_text'] ?? null,
                'param_count' => $template['total_parameters'] ?? $template['param_count'] ?? 0,
                'components' => $template['components'] ?? null,
            ];
        }

        $normalized = $this->normalizeTemplateList($templates);
        $unique = [];

        foreach ($normalized as $row) {
            $key = ($row['external_id'] !== '' ? $row['external_id'] : 'name:'.Str::lower($row['name']));

            if ($row['name'] === '' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    private function humanizeHttpError(string $path, Response $response): string
    {
        $body = trim(strip_tags((string) $response->body()));
        $body = preg_replace('/\s+/', ' ', $body) ?: '';

        if (str_contains($body, 'Cannot GET') || str_contains($body, 'Cannot POST')) {
            return 'HTTP '.$response->status().' on '.$path.' (endpoint not available for Project API password auth)';
        }

        return 'HTTP '.$response->status().' on '.$path.': '.Str::limit($body, 180);
    }

    public function syncApprovedTemplates(AisensyAccount $account): int
    {
        $templates = $this->listWaTemplates($account);
        $synced = 0;

        foreach ($templates as $template) {
            $status = strtoupper((string) ($template['status'] ?? ''));

            if ($status !== '' && $status !== 'APPROVED') {
                continue;
            }

            $externalId = (string) ($template['external_id'] ?? '');
            $name = trim((string) ($template['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $record = AisensyWaTemplate::query()->firstOrNew([
                'aisensy_account_id' => $account->id,
                'external_id' => $externalId !== '' ? $externalId : 'name:'.Str::slug($name),
            ]);

            $record->fill([
                'name' => $name,
                'language' => $template['language'] ?? null,
                'status' => $status !== '' ? $status : 'APPROVED',
                'category' => $template['category'] ?? null,
                'header_type' => $template['header_type'] ?? null,
                'body_preview' => $template['body_preview'] ?? null,
                'param_count' => (int) ($template['param_count'] ?? 0),
                'components_json' => $template['components'] ?? null,
                'is_manual' => false,
                'is_active' => true,
                'synced_at' => now(),
            ]);

            if (blank($record->live_campaign_name)) {
                $record->live_campaign_name = $name;
            }

            $record->save();

            $synced++;
        }

        return $synced;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCampaigns(AisensyAccount $account, string $campaignType = 'API', int $skip = 0, int $limit = 100): array
    {
        $projectId = $this->requireProjectId($account);

        $response = $this->client($account)->post("project/{$projectId}/campaigns", [
            'skip' => $skip,
            'limit' => $limit,
            'campaignType' => $campaignType,
        ]);

        if (! $response->successful()) {
            $this->fail($account, 'list campaigns', $response);
        }

        $payload = $response->json();
        $rows = is_array($payload) ? ($payload['campaigns'] ?? $payload['data'] ?? $payload) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function campaignNameExists(AisensyAccount $account, string $campaignName, string $campaignType = 'API'): bool
    {
        $needle = mb_strtolower(trim($campaignName));

        if ($needle === '') {
            return false;
        }

        foreach ($this->listCampaigns($account, $campaignType, 0, 200) as $campaign) {
            $name = mb_strtolower(trim((string) ($campaign['name'] ?? '')));

            if ($name !== '' && $name === $needle) {
                return true;
            }
        }

        // Also check dedicated API campaign list endpoint.
        $projectId = $this->requireProjectId($account);
        $response = $this->client($account)->get("project/{$projectId}/campaign/api");

        if ($response->successful()) {
            $payload = $response->json();
            $rows = is_array($payload)
                ? ($payload['campaign'] ?? $payload['campaigns'] ?? $payload['data'] ?? $payload)
                : [];

            if (isset($rows['name'])) {
                $rows = [$rows];
            }

            if (is_array($rows)) {
                foreach ($rows as $campaign) {
                    if (! is_array($campaign)) {
                        continue;
                    }

                    $name = mb_strtolower(trim((string) ($campaign['name'] ?? '')));

                    if ($name !== '' && $name === $needle) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Locate an API campaign by exact name (case-insensitive).
     *
     * @return array<string, mixed>|null
     */
    public function findApiCampaignByName(AisensyAccount $account, string $campaignName): ?array
    {
        $needle = mb_strtolower(trim($campaignName));

        if ($needle === '') {
            return null;
        }

        foreach ($this->listCampaigns($account, 'API', 0, 200) as $campaign) {
            $name = mb_strtolower(trim((string) ($campaign['name'] ?? '')));

            if ($name !== '' && $name === $needle) {
                return $this->enrichCampaignWithAnalytics($account, $campaign);
            }
        }

        $projectId = $this->requireProjectId($account);
        $response = $this->client($account)->get("project/{$projectId}/campaign/api");

        if (! $response->successful()) {
            $this->lastError = $this->humanizeHttpError("project/{$projectId}/campaign/api", $response);

            return null;
        }

        $payload = $response->json();
        $rows = is_array($payload)
            ? ($payload['campaign'] ?? $payload['campaigns'] ?? $payload['data'] ?? $payload)
            : [];

        if (isset($rows['name'])) {
            $rows = [$rows];
        }

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $campaign) {
            if (! is_array($campaign)) {
                continue;
            }

            $name = mb_strtolower(trim((string) ($campaign['name'] ?? '')));

            if ($name !== '' && $name === $needle) {
                return $this->enrichCampaignWithAnalytics($account, $campaign);
            }
        }

        $this->lastError = 'Campaign not found in AiSensy: '.$campaignName;

        return null;
    }

    /**
     * Normalize delivery analytics from an AiSensy campaign payload.
     *
     * @param  array<string, mixed>  $campaign
     * @return array{sent: int, delivered: int, read: int, failed: int, raw: array<string, mixed>}
     */
    public function extractCampaignDeliveryStats(array $campaign): array
    {
        $bags = [
            $campaign,
            is_array($campaign['analytics'] ?? null) ? $campaign['analytics'] : [],
            is_array($campaign['stats'] ?? null) ? $campaign['stats'] : [],
            is_array($campaign['statistics'] ?? null) ? $campaign['statistics'] : [],
            is_array($campaign['report'] ?? null) ? $campaign['report'] : [],
            is_array($campaign['metrics'] ?? null) ? $campaign['metrics'] : [],
            is_array($campaign['campaign_analytics'] ?? null) ? $campaign['campaign_analytics'] : [],
            is_array($campaign['message_stats'] ?? null) ? $campaign['message_stats'] : [],
        ];

        $sent = $this->firstIntFromBags($bags, [
            'sent', 'sent_count', 'sentCount', 'total_sent', 'messages_sent', 'Submitted', 'submitted',
        ]);
        $delivered = $this->firstIntFromBags($bags, [
            'delivered', 'delivered_count', 'deliveredCount', 'total_delivered', 'messages_delivered',
        ]);
        $read = $this->firstIntFromBags($bags, [
            'read', 'read_count', 'readCount', 'total_read', 'messages_read', 'seen',
        ]);
        $failed = $this->firstIntFromBags($bags, [
            'failed', 'failed_count', 'failedCount', 'total_failed', 'messages_failed', 'Failed',
        ]);

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => $failed,
            'raw' => [
                'name' => $campaign['name'] ?? null,
                'id' => $campaign['id'] ?? $campaign['_id'] ?? null,
                'sent' => $sent,
                'delivered' => $delivered,
                'read' => $read,
                'failed' => $failed,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    private function enrichCampaignWithAnalytics(AisensyAccount $account, array $campaign): array
    {
        $projectId = trim((string) $account->project_id);
        $campaignId = (string) ($campaign['id'] ?? $campaign['_id'] ?? '');
        $campaignName = trim((string) ($campaign['name'] ?? ''));

        $paths = array_values(array_filter([
            $campaignId !== '' ? "project/{$projectId}/campaign/{$campaignId}" : null,
            $campaignId !== '' ? "project/{$projectId}/campaigns/{$campaignId}" : null,
            $campaignId !== '' ? "project/{$projectId}/campaign/{$campaignId}/analytics" : null,
            $campaignName !== '' ? 'project/'.$projectId.'/campaign/api/'.rawurlencode($campaignName) : null,
        ]));

        foreach ($paths as $path) {
            try {
                $response = $this->client($account)->get($path);

                if (! $response->successful()) {
                    continue;
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    continue;
                }

                $detail = is_array($payload['campaign'] ?? null) ? $payload['campaign'] : $payload;

                return array_replace($campaign, $detail);
            } catch (\Throwable) {
                continue;
            }
        }

        return $campaign;
    }

    /**
     * @param  list<array<string, mixed>>  $bags
     * @param  list<string>  $keys
     */
    private function firstIntFromBags(array $bags, array $keys): int
    {
        foreach ($bags as $bag) {
            if ($bag === []) {
                continue;
            }

            foreach ($keys as $key) {
                if (! array_key_exists($key, $bag)) {
                    continue;
                }

                $value = $bag[$key];

                if (is_numeric($value)) {
                    return max(0, (int) $value);
                }

                if (is_array($value) && is_numeric($value['count'] ?? null)) {
                    return max(0, (int) $value['count']);
                }
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function createApiCampaign(AisensyAccount $account, string $templateName, string $campaignName): array
    {
        $projectId = $this->requireProjectId($account);

        $response = $this->client($account)->post("project/{$projectId}/campaign/api", [
            'template_name' => $templateName,
            'campaign_name' => $campaignName,
        ]);

        if (! $response->successful()) {
            $this->fail($account, 'create api campaign', $response);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Send one WhatsApp template message via Project API Campaign send.
     *
     * @param  list<string>  $templateParams
     * @param  array{url?: string, filename?: string}|null  $media
     * @param  array{latitude?: string, longitude?: string, name?: string, address?: string}|null  $location
     */
    public function sendApiCampaign(
        AisensyAccount $account,
        string $campaignName,
        string $phoneNumber,
        string $contactName,
        array $templateParams = [],
        ?array $media = null,
        ?array $location = null,
    ): bool {
        $projectId = $this->requireProjectId($account);

        $payload = [
            'campaign_name' => $campaignName,
            'name' => $contactName !== '' ? $contactName : 'Donor',
            'phone_number' => $phoneNumber,
        ];

        if ($templateParams !== []) {
            $payload['template_params'] = array_values(array_map(
                static fn ($value): string => (string) $value,
                $templateParams,
            ));
        }

        if (is_array($media) && filled($media['url'] ?? null)) {
            $payload['media'] = [
                'url' => (string) $media['url'],
                'filename' => (string) ($media['filename'] ?? 'media.jpg'),
            ];
        }

        if (is_array($location)
            && filled($location['latitude'] ?? null)
            && filled($location['longitude'] ?? null)
        ) {
            $payload['location'] = [
                'latitude' => (string) $location['latitude'],
                'longitude' => (string) $location['longitude'],
                'name' => (string) ($location['name'] ?? ''),
                'address' => (string) ($location['address'] ?? ''),
            ];
        }

        try {
            $response = $this->client($account)->post("project/{$projectId}/campaign/api/send", $payload);
        } catch (\Throwable $e) {
            Log::error('AiSensy Project API send failed', [
                'account_id' => $account->id,
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            $this->lastError = $e->getMessage();

            return false;
        }

        if (! $response->successful()) {
            Log::error('AiSensy Project API send failed', [
                'account_id' => $account->id,
                'campaign_name' => $campaignName,
                'status' => $response->status(),
                'response' => Str::limit((string) $response->body(), 500),
            ]);

            $this->lastError = 'HTTP '.$response->status().': '.Str::limit((string) $response->body(), 300);

            return false;
        }

        return true;
    }

    private function requireProjectId(AisensyAccount $account): string
    {
        if (! $account->hasProjectApiPassword()) {
            throw new RuntimeException('Project API password is missing on this AiSensy account.');
        }

        $projectId = trim((string) $account->project_id);

        if ($projectId === '') {
            throw new RuntimeException('Project ID is missing on this AiSensy account.');
        }

        return $projectId;
    }

    private function client(AisensyAccount $account): PendingRequest
    {
        $base = rtrim((string) config('services.aisensy.project_api_base', 'https://apis.aisensy.com/project-apis/v1'), '/');

        return Http::baseUrl($base)
            ->timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-AiSensy-Project-API-Pwd' => (string) $account->project_api_password,
            ]);
    }

    private function fail(AisensyAccount $account, string $action, Response $response): never
    {
        $this->lastError = 'HTTP '.$response->status().': '.Str::limit((string) $response->body(), 300);

        Log::warning('AiSensy Project API '.$action.' failed', [
            'account_id' => $account->id,
            'error' => $this->lastError,
        ]);

        throw new RuntimeException('AiSensy Project API '.$action.' failed: '.$this->lastError);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeTemplateList(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $rows = $payload['data']
            ?? $payload['templates']
            ?? $payload['waTemplateMessages']
            ?? $payload['wa_template_messages']
            ?? $payload['result']
            ?? $payload;

        if (! is_array($rows)) {
            return [];
        }

        if (isset($rows['name']) || isset($rows['id']) || isset($rows['_id'])) {
            $rows = [$rows];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $components = $row['components'] ?? $row['component'] ?? null;
            $body = null;
            $paramCount = 0;

            if (is_array($components)) {
                foreach ($components as $component) {
                    if (! is_array($component)) {
                        continue;
                    }

                    $type = strtoupper((string) ($component['type'] ?? ''));
                    $text = (string) ($component['text'] ?? '');

                    if ($type === 'BODY' || ($body === null && $text !== '')) {
                        $body = $text;
                    }

                    if ($text !== '') {
                        preg_match_all('/\{\{\d+\}\}/', $text, $matches);
                        $paramCount = max($paramCount, count($matches[0] ?? []));
                    }
                }
            }

            if (isset($row['param_count']) || isset($row['total_parameters'])) {
                $paramCount = (int) ($row['param_count'] ?? $row['total_parameters']);
            }

            $headerType = strtoupper(trim((string) (
                $row['type']
                ?? $row['header_type']
                ?? $row['headerType']
                ?? $row['media_type']
                ?? ''
            )));

            if ($headerType === '' && is_array($components)) {
                foreach ($components as $component) {
                    if (! is_array($component)) {
                        continue;
                    }

                    if (strtoupper((string) ($component['type'] ?? '')) !== 'HEADER') {
                        continue;
                    }

                    $format = strtoupper(trim((string) ($component['format'] ?? $component['header_type'] ?? '')));

                    if (in_array($format, [
                        'IMAGE',
                        'VIDEO',
                        'DOCUMENT',
                        'TEXT',
                        'LOCATION',
                        'CAROUSEL',
                        'LIMITED TIME OFFER',
                        'LIMITED_TIME_OFFER',
                        'LTO',
                        'FILE',
                    ], true)) {
                        $headerType = match ($format) {
                            'FILE' => 'DOCUMENT',
                            'LIMITED_TIME_OFFER', 'LTO' => 'LIMITED TIME OFFER',
                            default => $format,
                        };
                        break;
                    }
                }
            }

            if ($headerType === '') {
                $headerType = 'TEXT';
            }

            $headerType = match ($headerType) {
                'FILE' => 'DOCUMENT',
                'LTO', 'LIMITED_TIME_OFFER', 'LIMITEDTIMEOFFER' => 'LIMITED TIME OFFER',
                default => str_replace('_', ' ', $headerType),
            };

            $normalized[] = [
                'external_id' => (string) ($row['id'] ?? $row['_id'] ?? $row['templateId'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['elementName'] ?? $row['template_name'] ?? $row['label'] ?? ''),
                'language' => $row['language'] ?? $row['languageCode'] ?? null,
                'status' => $row['status'] ?? $row['approvalStatus'] ?? 'APPROVED',
                'category' => $row['category'] ?? null,
                'header_type' => $headerType,
                'body_preview' => $body ?? ($row['body'] ?? $row['text'] ?? $row['body_preview'] ?? null),
                'param_count' => $paramCount,
                'components' => $components,
            ];
        }

        return $normalized;
    }
}
