<?php

namespace App\Jobs;

use App\Services\Meta\MetaAdSpendSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncMetaAdSpendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public ?int $metaAdAccountId = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(MetaAdSpendSyncService $service): array
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $from = $this->fromDate
            ? Carbon::parse($this->fromDate, $tz)->startOfDay()
            : null;
        $to = $this->toDate
            ? Carbon::parse($this->toDate, $tz)->startOfDay()
            : null;

        if ($from === null && $to === null) {
            return $service->syncRecentDays($this->metaAdAccountId);
        }

        return $service->sync($this->metaAdAccountId, $from, $to ?? $from);
    }
}
