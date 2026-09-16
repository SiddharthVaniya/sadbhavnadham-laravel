<?php

namespace App\Console\Commands;

use App\Models\CausePackage;
use App\Services\RazorpaySubscriptionService;
use App\Support\SubscriptionFrequency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncRazorpayPlans extends Command
{
    protected $signature = 'razorpay:sync-plans
                            {--frequency= : Sync only one frequency (monthly, quarterly, yearly)}
                            {--cause= : Sync plans only for a cause slug}';

    protected $description = 'Create or verify Razorpay subscription plans for active recurring packages';

    public function handle(RazorpaySubscriptionService $subscriptionService): int
    {
        if (! $subscriptionService->isEnabled()) {
            $this->warn('Recurring donations are disabled. Set RAZORPAY_SUBSCRIPTIONS_ENABLED=true to sync plans.');

            return self::SUCCESS;
        }

        $frequencies = $this->resolveFrequencies();
        $causeSlug = $this->option('cause');

        $packages = CausePackage::query()
            ->where('is_active', true)
            ->where('allow_recurring', true)
            ->whereHas('cause', function ($query) use ($causeSlug): void {
                $query->where('is_active', true)
                    ->where('allow_recurring', true);

                if (is_string($causeSlug) && $causeSlug !== '') {
                    $query->where('slug', $causeSlug);
                }
            })
            ->with('cause')
            ->orderBy('cause_id')
            ->orderBy('sort_order')
            ->get();

        if ($packages->isEmpty()) {
            $this->warn('No active packages found on recurring-enabled causes.');

            return self::SUCCESS;
        }

        $created = 0;
        $existing = 0;
        $failed = 0;

        foreach ($packages as $package) {
            foreach ($frequencies as $frequency) {
                try {
                    $plan = $subscriptionService->syncPlanForPackage($package, $frequency);

                    if ($plan->wasRecentlyCreated) {
                        $created++;
                        $this->line("Created {$plan->razorpay_plan_id} for {$package->cause->slug} / {$package->title} ({$frequency})");
                    } else {
                        $existing++;
                        $this->line("Exists {$plan->razorpay_plan_id} for {$package->cause->slug} / {$package->title} ({$frequency})");
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Failed syncing Razorpay plan', [
                        'cause_package_id' => $package->id,
                        'frequency' => $frequency,
                        'error' => $exception->getMessage(),
                    ]);
                    $this->error("Failed {$package->cause->slug} / {$package->title} ({$frequency}): {$exception->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Sync complete. Created: {$created}, existing: {$existing}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveFrequencies(): array
    {
        $requested = $this->option('frequency');

        if (is_string($requested) && $requested !== '') {
            SubscriptionFrequency::assertSupported($requested);

            return [$requested];
        }

        return config('payments.razorpay.subscription_frequencies', [SubscriptionFrequency::MONTHLY]);
    }
}
