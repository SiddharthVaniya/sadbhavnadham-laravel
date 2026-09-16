<?php

namespace App\Jobs;

use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCampaignRun;
use App\Services\AiSensy\AiSensyProjectClient;
use App\Support\DonationNotificationRetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendWhatsappCampaignRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $recipientId) {}

    public function tries(): int
    {
        return DonationNotificationRetry::jobTries();
    }

    public function backoff(): int
    {
        return DonationNotificationRetry::jobBackoffSeconds();
    }

    public function handle(AiSensyProjectClient $projectClient): void
    {
        $recipient = WhatsappCampaignRecipient::query()
            ->with(['run.account', 'donor'])
            ->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        $run = $recipient->run;

        if ($run === null || $run->status === WhatsappCampaignRun::STATUS_CANCELLED) {
            return;
        }

        if ($recipient->status !== WhatsappCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $donor = $recipient->donor;
        $account = $run->account;

        if ($donor === null || $account === null) {
            $this->markFailed($recipient, $run, 'Missing donor or AiSensy account.');

            return;
        }

        $paramMap = is_array($run->param_map_json) ? $run->param_map_json : [];
        $templateParams = [];

        foreach ($paramMap as $source) {
            $templateParams[] = $this->resolveParam((string) $source, $donor);
        }

        $phone = $this->formatPhone((string) $recipient->phone, (string) ($account->country_code ?: '91'));

        $media = null;

        if (filled($run->media_path)) {
            $media = [
                'url' => asset('storage/'.ltrim((string) $run->media_path, '/')),
                'filename' => (string) ($run->media_filename ?: basename((string) $run->media_path)),
            ];
        }

        $location = is_array($run->location_json) ? $run->location_json : null;

        $sent = $projectClient->sendApiCampaign(
            $account,
            (string) $run->live_campaign_name,
            $phone,
            (string) ($donor->name ?: 'Donor'),
            $templateParams,
            $media,
            $location,
        );

        if (! $sent) {
            $this->markFailed(
                $recipient,
                $run,
                $projectClient->lastError() ?: 'AiSensy Project API send failed.',
            );

            return;
        }

        DB::transaction(function () use ($recipient, $run): void {
            $recipient->forceFill([
                'status' => WhatsappCampaignRecipient::STATUS_SENT,
                'sent_at' => now(),
                'error' => null,
            ])->save();

            WhatsappCampaignRun::query()->whereKey($run->id)->increment('sent_count');
            $this->maybeCompleteRun($run->id);
        });
    }

    public function failed(?\Throwable $exception): void
    {
        $recipient = WhatsappCampaignRecipient::query()->with('run')->find($this->recipientId);

        if ($recipient === null || $recipient->run === null) {
            return;
        }

        if ($recipient->status === WhatsappCampaignRecipient::STATUS_PENDING) {
            $this->markFailed($recipient, $recipient->run, $exception?->getMessage() ?? 'Job failed');
        }
    }

    private function markFailed(WhatsappCampaignRecipient $recipient, WhatsappCampaignRun $run, string $error): void
    {
        DB::transaction(function () use ($recipient, $run, $error): void {
            $locked = WhatsappCampaignRecipient::query()
                ->whereKey($recipient->id)
                ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
                ->first();

            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'status' => WhatsappCampaignRecipient::STATUS_FAILED,
                'error' => $error,
            ])->save();

            WhatsappCampaignRun::query()->whereKey($run->id)->increment('failed_count');
            $this->maybeCompleteRun($run->id);
        });

        Log::warning('WhatsApp campaign recipient failed', [
            'run_id' => $run->id,
            'recipient_id' => $recipient->id,
            'error' => $error,
        ]);
    }

    private function maybeCompleteRun(int $runId): void
    {
        $run = WhatsappCampaignRun::query()->find($runId);

        if ($run === null || $run->isTerminal()) {
            return;
        }

        $pending = WhatsappCampaignRecipient::query()
            ->where('whatsapp_campaign_run_id', $runId)
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->exists();

        if ($pending) {
            return;
        }

        $run->forceFill([
            'status' => WhatsappCampaignRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
    }

    private function resolveParam(string $source, \App\Models\Donor $donor): string
    {
        return match ($source) {
            'donor.name' => (string) ($donor->name ?: ''),
            'donor.city' => (string) ($donor->city ?: ''),
            'donor.state' => (string) ($donor->state ?: ''),
            'donor.phone' => (string) ($donor->phone ?: ''),
            'donor.email' => (string) ($donor->email ?: ''),
            'last_paid_amount' => $this->resolveLastPaidAmount($donor),
            default => str_starts_with($source, 'custom:')
                ? substr($source, 7)
                : $source,
        };
    }

    private function resolveLastPaidAmount(\App\Models\Donor $donor): string
    {
        $amount = $donor->paidDonationOrders()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->value('total_amount');

        if ($amount === null) {
            return '';
        }

        return (string) \App\Helpers\NumberHelper::formatWholeAmount($amount);
    }

    private function formatPhone(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $countryCode = preg_replace('/\D+/', '', $countryCode) ?: '91';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        return $countryCode.ltrim($digits, '0');
    }
}
