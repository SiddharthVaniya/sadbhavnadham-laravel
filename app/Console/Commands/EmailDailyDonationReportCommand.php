<?php

namespace App\Console\Commands;

use App\Mail\DailyDonationReportMail;
use App\Models\DonationOrder;
use App\Support\DailyDonationReportExcel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailDailyDonationReportCommand extends Command
{
    protected $signature = 'donations:email-daily-report
                            {--date= : Report date (Y-m-d). Defaults to yesterday in app timezone.}';

    protected $description = 'Email an Excel report of paid donations created on a given day (default: yesterday)';

    public function handle(DailyDonationReportExcel $excel): int
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            $this->warn('No valid DONATION_DAILY_REPORT_EMAILS configured; skipping send.');
            Log::warning('Daily donation report skipped: no recipients');

            return self::SUCCESS;
        }

        // At 01:00 this sends the previous calendar day's paid donations (e.g. 08-Sep 1am → 07-Sep data).
        $reportDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'), config('app.timezone'))->startOfDay()
            : now()->subDay()->startOfDay();

        $orders = DonationOrder::query()
            ->with(['items.causeModel', 'items.package'])
            ->where('status', DonationOrder::STATUS_PAID)
            ->whereBetween('created_at', [
                $reportDate->copy()->startOfDay(),
                $reportDate->copy()->endOfDay(),
            ])
            ->orderByDesc('id')
            ->get();

        try {
            $xlsxBinary = $excel->build($orders, $reportDate);
            $attachmentName = 'donations_'.$reportDate->format('Y-m-d').'.xlsx';
            $primary = array_shift($recipients);
            $cc = $recipients;

            $mailable = new DailyDonationReportMail(
                $reportDate,
                $orders->count(),
                $xlsxBinary,
                $attachmentName,
            );

            $pending = Mail::to($primary);

            if ($cc !== []) {
                $pending->cc($cc);
            }

            $pending->send($mailable);

            $totalRecipients = 1 + count($cc);

            $this->info(sprintf(
                'Daily donation report sent for %s (%d orders) to %d recipient(s).',
                $reportDate->toDateString(),
                $orders->count(),
                $totalRecipients,
            ));

            Log::info('Daily donation report sent', [
                'date' => $reportDate->toDateString(),
                'order_count' => $orders->count(),
                'recipient_count' => $totalRecipients,
            ]);
        } catch (Throwable $exception) {
            Log::error('Daily donation report failed', [
                'date' => $reportDate->toDateString(),
                'error' => $exception->getMessage(),
            ]);

            $this->error('Failed to send daily donation report: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $configured = config('donation.daily_report_emails', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            $configured,
            fn (mixed $email): bool => is_string($email)
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
