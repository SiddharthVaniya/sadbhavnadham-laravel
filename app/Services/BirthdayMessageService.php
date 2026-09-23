<?php

namespace App\Services;

use App\Models\BirthdayMessageSend;
use App\Models\BirthdayMessageSetting;
use App\Models\BirthdayMessageStep;
use App\Models\DonationOrder;
use App\Models\Donor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BirthdayMessageService
{
    public function __construct(
        private DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ) {}

    public function settings(): BirthdayMessageSetting
    {
        return BirthdayMessageSetting::current();
    }

    /**
     * @return Collection<int, BirthdayMessageStep>
     */
    public function enabledSteps(): Collection
    {
        return BirthdayMessageStep::query()
            ->where('enabled', true)
            ->orderByDesc('days_before')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function resolveStepForDonorOnDate(Donor $donor, Carbon $runDate, bool $force = false): ?BirthdayMessageStep
    {
        if (! $force && ! $this->settings()->enabled) {
            return null;
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($donor->phone)) {
            return null;
        }

        if ($donor->date_of_birth === null && ! $force) {
            return null;
        }

        $year = (int) $runDate->year;

        foreach ($this->enabledSteps() as $step) {
            if (! $force && ! $this->donorMatchesOffset($donor, $runDate, (int) $step->days_before)) {
                continue;
            }

            if ($force && (int) $step->days_before !== 0) {
                // Force/phone tests target birthday-day path.
                continue;
            }

            $kind = $this->resolveKindForStep($donor, $step, $year);

            if ($kind === null) {
                continue;
            }

            $resolved = $kind === $step->kind
                ? $step
                : BirthdayMessageStep::query()
                    ->where('days_before', 0)
                    ->where('kind', $kind)
                    ->where('enabled', true)
                    ->first();

            if ($resolved === null) {
                continue;
            }

            if (! $force && $this->alreadySent($donor, $year, (int) $resolved->days_before, $resolved->kind)) {
                continue;
            }

            return $resolved;
        }

        return null;
    }

    /**
     * When scanning a specific step (cron per step).
     */
    public function shouldSendStep(Donor $donor, BirthdayMessageStep $step, Carbon $runDate, bool $force = false): bool
    {
        if (! $force && ! $this->settings()->enabled) {
            return false;
        }

        if (! $this->donationWhatsAppPolicy->hasSendablePhoneNumber($donor->phone)) {
            return false;
        }

        if (! $force && ! $this->donorMatchesOffset($donor, $runDate, (int) $step->days_before)) {
            return false;
        }

        $year = (int) $runDate->year;
        $resolvedKind = $this->resolveKindForStep($donor, $step, $year);

        if ($resolvedKind === null || $resolvedKind !== $step->kind) {
            return false;
        }

        if (! $force && $this->alreadySent($donor, $year, (int) $step->days_before, $step->kind)) {
            return false;
        }

        if ($step->isMarketing() && ! filled(trim((string) $step->campaign_name))) {
            return false;
        }

        if ($step->isWarmWish() && ! filled(trim((string) $step->campaign_name))) {
            return false;
        }

        return true;
    }

    /**
     * Birthday day: marketing vs warm_wish.
     * Pre-day reminders: marketing only, and stop once the donor has paid
     * after the first marketing/reminder send this year.
     */
    public function resolveKindForStep(Donor $donor, BirthdayMessageStep $step, int $year): ?string
    {
        if ((int) $step->days_before > 0) {
            if (! $step->isMarketing()) {
                return null;
            }

            // Already donated after the first reminder → skip remaining day-left marketing.
            if ($this->donorDonatedAfterFirstMarketing($donor, $year)) {
                return null;
            }

            return BirthdayMessageStep::KIND_MARKETING;
        }

        // days_before = 0
        if ($this->donorDonatedAfterFirstMarketing($donor, $year)) {
            return BirthdayMessageStep::KIND_WARM_WISH;
        }

        return BirthdayMessageStep::KIND_MARKETING;
    }

    public function donorMatchesOffset(Donor $donor, Carbon $runDate, int $daysBefore): bool
    {
        if ($donor->date_of_birth === null) {
            return false;
        }

        $target = $runDate->copy()->startOfDay()->addDays($daysBefore);

        return (int) $donor->date_of_birth->month === (int) $target->month
            && (int) $donor->date_of_birth->day === (int) $target->day;
    }

    public function alreadySent(Donor $donor, int $year, int $daysBefore, string $kind): bool
    {
        return BirthdayMessageSend::query()
            ->where('donor_id', $donor->id)
            ->where('year', $year)
            ->where('days_before', $daysBefore)
            ->where('kind', $kind)
            ->exists();
    }

    public function firstMarketingSentAt(Donor $donor, int $year): ?Carbon
    {
        $sentAt = BirthdayMessageSend::query()
            ->where('donor_id', $donor->id)
            ->where('year', $year)
            ->where('kind', BirthdayMessageStep::KIND_MARKETING)
            ->orderBy('sent_at')
            ->value('sent_at');

        return $sentAt !== null ? Carbon::parse($sentAt) : null;
    }

    public function donorDonatedAfterFirstMarketing(Donor $donor, int $year): bool
    {
        $firstMarketingAt = $this->firstMarketingSentAt($donor, $year);

        if ($firstMarketingAt === null) {
            return false;
        }

        return DonationOrder::query()
            ->where('status', DonationOrder::STATUS_PAID)
            ->where('paid_at', '>=', $firstMarketingAt)
            ->where(function ($query) use ($donor): void {
                $query->where('donor_id', $donor->id);

                $phone = preg_replace('/\D+/', '', (string) $donor->phone) ?? '';
                if (strlen($phone) >= 10) {
                    $query->orWhere(function ($phoneQuery) use ($phone): void {
                        $phoneQuery->where('donor_phone', $phone)
                            ->orWhere('donor_phone', '91'.$phone)
                            ->orWhere('donor_phone', '+91'.$phone)
                            ->orWhere('donor_phone', 'like', '%'.$phone);
                    });
                }
            })
            ->exists();
    }

    public function recordSend(Donor $donor, BirthdayMessageStep $step, Carbon $runDate): void
    {
        BirthdayMessageSend::query()->updateOrCreate(
            [
                'donor_id' => $donor->id,
                'year' => (int) $runDate->year,
                'days_before' => (int) $step->days_before,
                'kind' => $step->kind,
            ],
            [
                'birthday_message_step_id' => $step->id,
                'campaign_name' => $step->campaign_name,
                'sent_at' => now(),
            ],
        );

        if ($step->isWarmWish() || ((int) $step->days_before === 0 && $step->isMarketing())) {
            $donor->forceFill([
                'birthday_whatsapp_sent_on' => $runDate->toDateString(),
            ])->save();
        }
    }
}
