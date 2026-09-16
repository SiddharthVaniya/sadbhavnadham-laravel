<?php

namespace App\Console\Commands;

use App\Jobs\SendBirthdayWhatsAppJob;
use App\Models\Donor;
use App\Models\Setting;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBirthdayWhatsAppCommand extends Command
{
    protected $signature = 'donors:send-birthday-whatsapp
                            {--date= : Birthday date to process (Y-m-d). Defaults to today.}
                            {--phone= : Only send to this phone number (digits; for testing)}
                            {--name= : Display name when creating a test donor for --phone}
                            {--dry-run : List eligible donors without dispatching jobs}
                            {--force : Ignore prior send date, DOB match, and global toggle}';

    protected $description = 'Queue Happy Birthday WhatsApp images for donors whose birthday is today';

    public function handle(DonationWhatsAppPolicy $donationWhatsAppPolicy): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $phoneFilter = preg_replace('/\D+/', '', (string) $this->option('phone')) ?? '';

        if (! $force && ! Setting::isEnabled(Setting::SEND_BIRTHDAY_WHATSAPP)) {
            $this->warn('Birthday WhatsApp is disabled in settings. Use --force to override.');

            return self::SUCCESS;
        }

        if ($force && strlen($phoneFilter) === 10) {
            $this->ensureTestDonorForPhone($phoneFilter, $date);
        }

        $query = Donor::query()->orderBy('id');

        if ($phoneFilter !== '') {
            $query->where(function ($builder) use ($phoneFilter): void {
                $builder->where('phone', $phoneFilter)
                    ->orWhere('phone', '91'.$phoneFilter)
                    ->orWhere('phone', '+91'.$phoneFilter)
                    ->orWhere('phone', 'like', '%'.$phoneFilter);
            });
        } else {
            $query->whereNotNull('date_of_birth')
                ->whereMonth('date_of_birth', $date->month)
                ->whereDay('date_of_birth', $date->day);

            if (! $force) {
                $query->where(function ($builder) use ($date): void {
                    $builder->whereNull('birthday_whatsapp_sent_on')
                        ->orWhereDate('birthday_whatsapp_sent_on', '!=', $date->toDateString());
                });
            }
        }

        $dispatched = 0;
        $skipped = 0;

        $query->chunkById(100, function ($donors) use ($donationWhatsAppPolicy, $date, $force, $dryRun, &$dispatched, &$skipped): void {
            foreach ($donors as $donor) {
                if (! $donationWhatsAppPolicy->shouldSendBirthday($donor, $date, $force)) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] donor #%d %s (%s)',
                        $donor->id,
                        $donor->name,
                        $donor->phone,
                    ));
                    $dispatched++;

                    continue;
                }

                dispatch(new SendBirthdayWhatsAppJob($donor, $date->toDateString(), $force));
                $dispatched++;
            }
        });

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
