<?php

namespace App\Console\Commands;

use App\Jobs\SendBirthdayWhatsAppJob;
use App\Models\BirthdayMessageStep;
use App\Models\Donor;
use App\Services\BirthdayMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBirthdayWhatsAppCommand extends Command
{
    protected $signature = 'donors:send-birthday-whatsapp
                            {--date= : Run date (Y-m-d). Defaults to today.}
                            {--phone= : Only send to this phone number (digits; for testing)}
                            {--name= : Display name when creating a test donor for --phone}
                            {--dry-run : List eligible donors without dispatching jobs}
                            {--force : Ignore prior send date, DOB match, and global toggle}';

    protected $description = 'Queue birthday marketing / warm-wish WhatsApp messages from birthday_message_steps';

    public function handle(BirthdayMessageService $birthdayMessages): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $phoneFilter = preg_replace('/\D+/', '', (string) $this->option('phone')) ?? '';

        if (! $force && ! $birthdayMessages->settings()->enabled) {
            $this->warn('Birthday WhatsApp is disabled in birthday_message_settings. Use --force to override.');

            return self::SUCCESS;
        }

        if ($force && strlen($phoneFilter) === 10) {
            $this->ensureTestDonorForPhone($phoneFilter, $date);
        }

        $dispatched = 0;
        $skipped = 0;

        if ($phoneFilter !== '') {
            $donors = Donor::query()
                ->where(function ($builder) use ($phoneFilter): void {
                    $builder->where('phone', $phoneFilter)
                        ->orWhere('phone', '91'.$phoneFilter)
                        ->orWhere('phone', '+91'.$phoneFilter)
                        ->orWhere('phone', 'like', '%'.$phoneFilter);
                })
                ->orderBy('id')
                ->get();

            foreach ($donors as $donor) {
                $step = $birthdayMessages->resolveStepForDonorOnDate($donor, $date, $force);

                if ($step === null) {
                    $skipped++;

                    continue;
                }

                if ($this->dispatchOrDryRun($donor, $step, $date, $force, $dryRun)) {
                    $dispatched++;
                } else {
                    $skipped++;
                }
            }
        } else {
            $steps = $birthdayMessages->enabledSteps();

            foreach ($steps as $step) {
                $target = $date->copy()->addDays((int) $step->days_before);

                $query = Donor::query()
                    ->whereNotNull('date_of_birth')
                    ->whereMonth('date_of_birth', $target->month)
                    ->whereDay('date_of_birth', $target->day)
                    ->orderBy('id');

                $query->chunkById(100, function ($donors) use ($birthdayMessages, $step, $date, $force, $dryRun, &$dispatched, &$skipped): void {
                    foreach ($donors as $donor) {
                        if (! $birthdayMessages->shouldSendStep($donor, $step, $date, $force)) {
                            $skipped++;

                            continue;
                        }

                        if ($this->dispatchOrDryRun($donor, $step, $date, $force, $dryRun)) {
                            $dispatched++;
                        } else {
                            $skipped++;
                        }
                    }
                });
            }
        }

        if ($phoneFilter !== '' && $dispatched === 0) {
            $this->warn("No eligible donor found for phone {$phoneFilter}.");
        }

        $this->info(sprintf(
            '%s %d birthday WhatsApp job(s) for %s (%d skipped).',
            $dryRun ? 'Would dispatch' : 'Dispatched',
            $dispatched,
            $date->toDateString(),
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function dispatchOrDryRun(
        Donor $donor,
        BirthdayMessageStep $step,
        Carbon $date,
        bool $force,
        bool $dryRun,
    ): bool {
        if ($dryRun) {
            $this->line(sprintf(
                '[dry-run] donor #%d %s (%s) step #%d %s days_before=%d',
                $donor->id,
                $donor->name,
                $donor->phone,
                $step->id,
                $step->kind,
                $step->days_before,
            ));

            return true;
        }

        dispatch(new SendBirthdayWhatsAppJob($donor, $date->toDateString(), $force, $step->id));

        return true;
    }

    private function ensureTestDonorForPhone(string $phone, Carbon $date): void
    {
        $donor = Donor::query()
            ->where('phone', $phone)
            ->orWhere('phone', '91'.$phone)
            ->orWhere('phone', '+91'.$phone)
            ->first();

        if ($donor !== null) {
            $donor->forceFill([
                'phone' => $phone,
                'date_of_birth' => $date->copy()->subYears(30)->toDateString(),
                'birthday_whatsapp_sent_on' => null,
            ])->save();

            $this->line("Using donor #{$donor->id} ({$donor->name}) for {$phone}");

            return;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $name = 'Birthday Test';
        }

        $donor = Donor::query()->create([
            'name' => $name,
            'email' => 'birthday-test-'.$phone.'@example.test',
            'phone' => $phone,
            'date_of_birth' => $date->copy()->subYears(30)->toDateString(),
            'country' => 'INDIA',
            'country_code' => 'IN',
            'consent_indian_citizen' => true,
            'birthday_whatsapp_sent_on' => null,
        ]);

        $this->warn("Created test donor #{$donor->id} for phone {$phone}");
    }
}
