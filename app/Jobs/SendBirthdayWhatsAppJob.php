<?php

namespace App\Jobs;

use App\Models\BirthdayMessageStep;
use App\Models\Donor;
use App\Services\AiSensyService;
use App\Services\BirthdayMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendBirthdayWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Donor $donor,
        private ?string $onDate = null,
        private bool $forceSend = false,
        private ?int $stepId = null,
    ) {}

    public function handle(AiSensyService $aiSensyService, BirthdayMessageService $birthdayMessages): void
    {
        $date = $this->onDate === null
            ? now()->startOfDay()
            : Carbon::parse($this->onDate)->startOfDay();

        $step = $this->stepId !== null
            ? BirthdayMessageStep::query()->find($this->stepId)
            : null;

        if ($step === null) {
            $step = $birthdayMessages->resolveStepForDonorOnDate($this->donor->fresh(), $date, $this->forceSend);
        }

        if ($step === null) {
            return;
        }

        if (! $birthdayMessages->shouldSendStep($this->donor->fresh(), $step, $date, $this->forceSend)
            && ! $this->forceSend) {
            return;
        }

        try {
            $donor = $this->donor->fresh();
            $sent = $step->isWarmWish()
                ? $aiSensyService->sendBirthdayWhatsApp($donor, $step)
                : $aiSensyService->sendBirthdayMarketingWhatsApp($donor, $step);

            if (! $sent) {
                throw new \RuntimeException('AiSensy birthday WhatsApp was not sent.');
            }

            $birthdayMessages->recordSend($donor, $step, $date);
        } catch (\Throwable $e) {
            Log::error('Birthday WhatsApp job failed', [
                'donor_id' => $this->donor->id,
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
