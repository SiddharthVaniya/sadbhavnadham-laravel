<?php

namespace App\Services;

use App\Models\WhatsappCampaignRun;
use App\Services\AiSensy\AiSensyProjectClient;
use RuntimeException;

class WhatsappCampaignDeliverySync
{
    public function __construct(private AiSensyProjectClient $projectClient) {}

    /**
     * Pull AiSensy campaign analytics (sent/delivered/read/failed) onto our run.
     *
     * Our local failed_count only tracks API/queue rejects. Meta delivery failures
     * appear later in AiSensy analytics — this sync bridges that gap.
     *
     * @return array{
     *     sent: int,
     *     delivered: int,
     *     read: int,
     *     failed: int
     * }
     */
    public function sync(WhatsappCampaignRun $run): array
    {
        $run->loadMissing('account');

        $account = $run->account;
        $campaignName = trim((string) $run->live_campaign_name);

        if ($account === null) {
            throw new RuntimeException('Campaign has no AiSensy account.');
        }

        if ($campaignName === '') {
            throw new RuntimeException('Campaign has no AiSensy live campaign name.');
        }

        $campaign = $this->projectClient->findApiCampaignByName($account, $campaignName);

        if ($campaign === null) {
            throw new RuntimeException('AiSensy campaign “'.$campaignName.'” was not found. Open it in AiSensy or wait a minute and sync again.');
        }

        $stats = $this->projectClient->extractCampaignDeliveryStats($campaign);

        $run->forceFill([
            'delivery_sent_count' => $stats['sent'],
            'delivery_delivered_count' => $stats['delivered'],
            'delivery_read_count' => $stats['read'],
            'delivery_failed_count' => $stats['failed'],
            'delivery_synced_at' => now(),
            'delivery_stats_json' => $stats['raw'],
        ])->save();

        return [
            'sent' => $stats['sent'],
            'delivered' => $stats['delivered'],
            'read' => $stats['read'],
            'failed' => $stats['failed'],
        ];
    }
}
