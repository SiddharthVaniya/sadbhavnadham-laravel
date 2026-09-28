<?php

namespace App\Jobs;

use App\Models\DanamojoDonation;
use App\Services\Danamojo\DanamojoDonationImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportDanamojoDonationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 12;

    /**
     * @param  array<string, string|null>  $attribution
     */
    public function __construct(
        public int $donationInfoId,
        public array $attribution = [],
        public ?string $dmStatus = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [45, 120, 300, 600, 900, 1800, 3600, 3600, 7200, 7200, 14400];
    }

    public function handle(DanamojoDonationImporter $importer): void
    {
        if ($this->attribution !== []) {
            $importer->recordNotify($this->donationInfoId, array_filter([
                'dmStatus' => $this->dmStatus,
                ...$this->attribution,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        $result = $importer->importByDonationInfoId($this->donationInfoId);

        Log::info('danamojo.notify.retry', [
            'donationInfoId' => $this->donationInfoId,
            'attempt' => $this->attempts(),
            'result' => $result,
        ]);

        if (in_array($result, ['imported', 'updated'], true)) {
            return;
        }

        $record = DanamojoDonation::query()
            ->where('donation_info_id', $this->donationInfoId)
            ->first();

        if ($record?->sync_state === DanamojoDonation::STATE_FAILED) {
            return;
        }

        if (in_array($result, ['not_found', 'skipped'], true) && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
        }
    }
}
