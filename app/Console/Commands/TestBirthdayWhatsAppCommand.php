<?php

namespace App\Console\Commands;

use App\Models\BirthdayMessageStep;
use App\Models\Donor;
use App\Services\AiSensyService;
use Illuminate\Console\Command;

class TestBirthdayWhatsAppCommand extends Command
{
    /**
     * Default QA handset — keep in sync with test.md.
     */
    public const DEFAULT_TEST_PHONE = '9426025598';

    public const DEFAULT_TEST_NAME = 'Tony';

    protected $signature = 'birthday:test-whatsapp
                            {--phone=9426025598 : Destination phone (10 digits or with country code)}
                            {--name=Tony : Display / template name}
                            {--only= : day-left|birthday-marketing|warm (default: all)}';

    protected $description = 'Send birthday WhatsApp campaign smoke tests (default: Tony +919426025598)';

    public function handle(AiSensyService $aiSensy): int
    {
        $phone = preg_replace('/\D+/', '', (string) $this->option('phone')) ?: self::DEFAULT_TEST_PHONE;
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }

        $name = trim((string) $this->option('name')) ?: self::DEFAULT_TEST_NAME;
        $only = strtolower(trim((string) $this->option('only')));

        $donor = Donor::query()
            ->where(function ($q) use ($phone): void {
                $q->where('phone', $phone)
                    ->orWhere('phone', '91'.$phone)
                    ->orWhere('phone', '+91'.$phone)
                    ->orWhere('phone', 'like', '%'.$phone);
            })
            ->orderBy('id')
            ->first();

        if ($donor === null) {
            $donor = new Donor([
                'name' => $name,
                'phone' => $phone,
                'date_of_birth' => now()->subYears(30)->toDateString(),
            ]);
        } else {
            $donor->name = $name !== '' ? $name : ($donor->name ?: self::DEFAULT_TEST_NAME);
            $donor->phone = $phone;
        }

        $this->info("Birthday WA test → +91{$phone} ({$donor->name})");

        $dayLeft = BirthdayMessageStep::query()
            ->where('days_before', '>', 0)
            ->where('kind', BirthdayMessageStep::KIND_MARKETING)
            ->where('enabled', true)
            ->orderByDesc('days_before')
            ->first();

        $birthdayMarketing = BirthdayMessageStep::query()
            ->where('days_before', 0)
            ->where('kind', BirthdayMessageStep::KIND_MARKETING)
            ->where('enabled', true)
            ->first();

        $warm = BirthdayMessageStep::query()
            ->where('days_before', 0)
            ->where('kind', BirthdayMessageStep::KIND_WARM_WISH)
            ->where('enabled', true)
            ->first();

        $runs = [];

        if ($only === '' || $only === 'all' || $only === 'day-left') {
            $runs[] = ['label' => 'day-left marketing', 'step' => $dayLeft, 'type' => 'marketing'];
        }
        if ($only === '' || $only === 'all' || $only === 'birthday-marketing') {
            $runs[] = ['label' => 'birthday marketing', 'step' => $birthdayMarketing, 'type' => 'marketing'];
        }
        if ($only === '' || $only === 'all' || $only === 'warm') {
            $runs[] = ['label' => 'warm wish (donated)', 'step' => $warm, 'type' => 'warm'];
        }

        if ($runs === []) {
            $this->error('Unknown --only value. Use: day-left, birthday-marketing, warm');

            return self::FAILURE;
        }

        $okCount = 0;
        $failCount = 0;

        foreach ($runs as $run) {
            /** @var BirthdayMessageStep|null $step */
            $step = $run['step'];

            if ($step === null) {
                $this->error("{$run['label']}: step missing / disabled in birthday_message_steps");
                $failCount++;

                continue;
            }

            $this->line('');
            $this->line("→ {$run['label']}");
            $this->line("  days_before={$step->days_before} kind={$step->kind}");
            $this->line('  campaign='.$step->campaign_name);
            $this->line('  image='.($step->publicImageUrl() ?: '(fallback)'));

            $ok = $run['type'] === 'warm'
                ? $aiSensy->sendBirthdayWarmWishWhatsApp($donor, $step)
                : $aiSensy->sendBirthdayMarketingWhatsApp($donor, $step);

            if ($ok) {
                $this->info('  RESULT: sent (HTTP 200 from AiSensy)');
                $okCount++;
            } else {
                $this->error('  RESULT: failed — check storage/logs/laravel.log');
                $failCount++;
            }

            usleep(400_000);
        }

        $this->line('');
        $this->info("Done. ok={$okCount} fail={$failCount}. Check WhatsApp on +91{$phone}.");
        $this->line('See test.md for expected campaigns and troubleshooting.');

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
