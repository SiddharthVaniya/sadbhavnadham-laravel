<?php

namespace App\Console\Commands;

use App\Models\DonationOrder;
use App\Support\StaffReferral;
use Illuminate\Console\Command;

class FixCookieMixedPartnerAttributionCommand extends Command
{
    protected $signature = 'donations:fix-cookie-mixed-partners
                            {--dry-run : Report rows without updating}
                            {--chunk=200 : Orders per chunk}';

    protected $description = 'Repair orders where WhatsApp/staff first-touch sid kept credit after a Meta ad content graft';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $scanned = 0;
        $fixed = 0;
        $skipped = 0;

        DonationOrder::query()
            ->whereNotNull('partner_code')
            ->where('partner_code', '!=', '')
            ->whereNotNull('utm_content')
            ->where('utm_content', 'like', '%|%')
            ->where(function ($query): void {
                $query->whereIn('utm_medium', ['whatsapp', 'referral'])
                    ->orWhere('utm_source', 'staff')
                    ->orWhere('utm_campaign', 'tree');
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use ($dryRun, &$scanned, &$fixed, &$skipped): void {
                foreach ($orders as $order) {
                    $scanned++;

                    $fromAd = StaffReferral::partnerFromMetaAdNamePrefix($order->utm_content);

                    if ($fromAd === null) {
                        $skipped++;

                        continue;
                    }

                    $expectedCode = StaffReferral::normalize($fromAd->referral_code);
                    $currentCode = StaffReferral::normalize($order->partner_code);

                    if ($expectedCode === null || $expectedCode === $currentCode) {
                        $skipped++;

                        continue;
                    }

                    // Prefer a canonical Meta campaign/medium from siblings with the same ad name.
                    $sibling = DonationOrder::query()
                        ->where('utm_content', $order->utm_content)
                        ->where('partner_code', $expectedCode)
                        ->where(function ($q): void {
                            $q->where('utm_medium', 'like', 'Facebook_%')
                                ->orWhere('utm_medium', 'like', 'Instagram_%');
                        })
                        ->latest('id')
                        ->first(['utm_medium', 'utm_campaign', 'utm_source', 'landing_path']);

                    $payload = [
                        'partner_user_id' => $fromAd->id,
                        'partner_code' => $expectedCode,
                    ];

                    if ($sibling) {
                        if (filled($sibling->utm_medium)) {
                            $payload['utm_medium'] = $sibling->utm_medium;
                        }
                        if (filled($sibling->utm_campaign)) {
                            $payload['utm_campaign'] = $sibling->utm_campaign;
                        }
                        if (filled($sibling->utm_source)) {
                            $payload['utm_source'] = $sibling->utm_source;
                        }
                        if (filled($sibling->landing_path) && in_array((string) $order->landing_path, ['/donate', '/donate/tree-plantation', '/'], true)) {
                            $payload['landing_path'] = $sibling->landing_path;
                        }
                    }

                    $this->line(sprintf(
                        '%s #%d %s → %s (%s)',
                        $dryRun ? 'Would fix' : 'Fixed',
                        $order->id,
                        $currentCode,
                        $expectedCode,
                        $fromAd->name,
                    ));

                    if (! $dryRun) {
                        $order->forceFill($payload)->save();
                    }

                    $fixed++;
                }
            });

        $mode = $dryRun ? 'Dry-run: ' : '';
        $this->info("{$mode}Scanned {$scanned}; fixed {$fixed}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
