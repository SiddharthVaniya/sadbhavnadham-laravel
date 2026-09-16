<?php

namespace App\Services;

use App\Jobs\ProcessWhatsappCampaignRunJob;
use App\Models\AisensyAccount;
use App\Models\AisensyWaTemplate;
use App\Models\Donor;
use App\Models\User;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCampaignRun;
use App\Services\AiSensy\AiSensyProjectClient;
use App\Support\DonorAudienceQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WhatsappCampaignLauncher
{
    public function __construct(private AiSensyProjectClient $projectClient) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $paramMap
     * @param  array{path?: string, filename?: string}|null  $media
     * @param  array{latitude?: string, longitude?: string, name?: string, address?: string}|null  $location
     */
    public function launch(
        User $user,
        AisensyAccount $account,
        string $name,
        AisensyWaTemplate $template,
        array $filters,
        array $paramMap = [],
        bool $dryRun = false,
        ?array $media = null,
        ?array $location = null,
    ): WhatsappCampaignRun {
        $name = trim($name);
        $templateName = trim((string) $template->name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Portal campaign name is required.',
            ]);
        }

        if ($templateName === '') {
            throw ValidationException::withMessages([
                'aisensy_wa_template_id' => 'Selected template is missing a name.',
            ]);
        }

        if (! $account->hasProjectApiPassword() || blank($account->project_id)) {
            throw ValidationException::withMessages([
                'aisensy_account_id' => 'Selected AiSensy account needs Project API password and Project ID.',
            ]);
        }

        if ($template->requiresMediaHeader() && blank($media['path'] ?? null)) {
            throw ValidationException::withMessages([
                'media' => 'This template needs an '.$template->normalizedHeaderType().' media file upload.',
            ]);
        }

        if ($template->requiresLocationHeader()
            && (blank($location['latitude'] ?? null) || blank($location['longitude'] ?? null))
        ) {
            throw ValidationException::withMessages([
                'location.latitude' => 'LOCATION templates need latitude and longitude.',
            ]);
        }

        $maxAudience = max(1, (int) config('services.aisensy.campaign_max_audience', 10000));

        $eligibleQuery = DonorAudienceQuery::campaignEligible($filters);
        $candidateIds = (clone $eligibleQuery)->pluck('id');

        $recipients = [];

        foreach ($candidateIds->chunk(200) as $chunk) {
            $donors = Donor::query()->whereIn('id', $chunk)->get();

            foreach ($donors as $donor) {
                if (! DonorAudienceQuery::isSendableDonor($donor)) {
                    continue;
                }

                $recipients[] = [
                    'donor_id' => $donor->id,
                    'phone' => (string) $donor->phone,
                ];

                if (count($recipients) > $maxAudience) {
                    throw ValidationException::withMessages([
                        'filters' => "Audience exceeds the maximum of {$maxAudience} donors. Narrow your filters.",
                    ]);
                }
            }
        }

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'filters' => 'No eligible donors matched these filters (valid phone + not opted out).',
            ]);
        }

        $aisensyCampaignName = $this->uniqueCampaignName($account, $name);

        if (! $dryRun) {
            try {
                $this->projectClient->createApiCampaign($account, $templateName, $aisensyCampaignName);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages([
                    'name' => 'Could not create campaign in AiSensy: '.$e->getMessage(),
                ]);
            }
        }

        return DB::transaction(function () use ($user, $account, $name, $aisensyCampaignName, $filters, $paramMap, $template, $recipients, $dryRun, $media, $location): WhatsappCampaignRun {
            $run = WhatsappCampaignRun::query()->create([
                'aisensy_account_id' => $account->id,
                'aisensy_wa_template_id' => $template->id,
                'name' => $name,
                'live_campaign_name' => $aisensyCampaignName,
                'status' => $dryRun ? WhatsappCampaignRun::STATUS_COMPLETED : WhatsappCampaignRun::STATUS_QUEUED,
                'filters_json' => $filters,
                'param_map_json' => array_values($paramMap),
                'media_path' => $media['path'] ?? null,
                'media_filename' => $media['filename'] ?? null,
                'location_json' => $location,
                'audience_count' => count($recipients),
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => $dryRun ? count($recipients) : 0,
                'created_by' => $user->id,
                'started_at' => $dryRun ? now() : null,
                'finished_at' => $dryRun ? now() : null,
            ]);

            $now = now();
            $rows = [];

            foreach ($recipients as $recipient) {
                $rows[] = [
                    'whatsapp_campaign_run_id' => $run->id,
                    'donor_id' => $recipient['donor_id'],
                    'phone' => $recipient['phone'],
                    'status' => $dryRun
                        ? WhatsappCampaignRecipient::STATUS_SKIPPED
                        : WhatsappCampaignRecipient::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                WhatsappCampaignRecipient::query()->insert($chunk);
            }

            if (! $dryRun) {
                ProcessWhatsappCampaignRunJob::dispatch($run->id);
            }

            return $run->fresh(['template', 'account', 'creator']) ?? $run;
        });
    }

    private function uniqueCampaignName(AisensyAccount $account, string $portalName): string
    {
        $base = Str::limit(Str::slug($portalName) ?: 'portal-campaign', 40, '');
        $candidate = $base.'-'.now()->format('YmdHis');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $name = $attempt === 0 ? $candidate : $candidate.'-'.Str::lower(Str::random(4));

            try {
                if (! $this->projectClient->campaignNameExists($account, $name, 'API')) {
                    return $name;
                }
            } catch (RuntimeException) {
                // If list fails, still use a unique local name and let create fail clearly.
                return $name;
            }
        }

        throw ValidationException::withMessages([
            'name' => 'Could not generate a unique AiSensy campaign name. Try a different portal campaign name.',
        ]);
    }
}
