<?php

use App\Mail\DailyDonationReportMail;
use App\Models\DonationOrder;
use App\Support\DailyDonationReportExcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

it('emails todays donations excel to configured recipients with check column', function () {
    Mail::fake();

    config([
        'donation.daily_report_emails' => [
            'siddharthvaniya123@gmail.com',
            'ops@example.com',
        ],
    ]);

    $reportDay = Carbon::parse('2026-09-07', 'Asia/Kolkata')->startOfDay();

    $makeOrder = function (array $attributes) use ($reportDay): DonationOrder {
        $createdAt = $attributes['created_at'] ?? $reportDay->copy()->setTime(10, 0);
        unset($attributes['created_at'], $attributes['updated_at']);

        $order = DonationOrder::create($attributes);
        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $order->refresh();
    };

    $makeOrder([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'daily-report-paid',
        'provider_payment_id' => 'pay_daily_report_paid',
        'donor_name' => 'Today Paid',
        'donor_email' => 'today-paid@example.com',
        'donor_phone' => '9000000001',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $reportDay->copy()->setTime(10, 0),
        'created_at' => $reportDay->copy()->setTime(10, 0),
    ]);

    $makeOrder([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'daily-report-pending',
        'donor_name' => 'Today Pending',
        'donor_email' => 'today-pending@example.com',
        'donor_phone' => '9000000002',
        'currency' => 'INR',
        'total_amount' => 300,
        'status' => DonationOrder::STATUS_PENDING,
        'created_at' => $reportDay->copy()->setTime(11, 0),
    ]);

    $makeOrder([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'daily-report-failed',
        'donor_name' => 'Today Failed',
        'donor_email' => 'today-failed@example.com',
        'donor_phone' => '9000000003',
        'currency' => 'INR',
        'total_amount' => 200,
        'status' => DonationOrder::STATUS_FAILED,
        'failed_at' => $reportDay->copy()->setTime(12, 0),
        'created_at' => $reportDay->copy()->setTime(12, 0),
    ]);

    $makeOrder([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'daily-report-yesterday',
        'donor_name' => 'Yesterday Donor',
        'donor_email' => 'yesterday@example.com',
        'donor_phone' => '9000000004',
        'currency' => 'INR',
        'total_amount' => 900,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $reportDay->copy()->subDay(),
        'created_at' => $reportDay->copy()->subDay()->setTime(15, 0),
    ]);

    $this->artisan('donations:email-daily-report', [
        '--date' => $reportDay->toDateString(),
    ])->assertSuccessful();

    Mail::assertSent(DailyDonationReportMail::class, function (DailyDonationReportMail $mail): bool {
        expect($mail->hasTo('siddharthvaniya123@gmail.com'))->toBeTrue()
            ->and($mail->hasCc('ops@example.com'))->toBeTrue()
            ->and($mail->orderCount)->toBe(1)
            ->and($mail->attachmentName)->toEndWith('.xlsx')
            ->and(strlen($mail->xlsxBinary))->toBeGreaterThan(100);

        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($temp, $mail->xlsxBinary);
        $sheet = IOFactory::load($temp)->getActiveSheet();
        @unlink($temp);

        expect($sheet->getCell('M1')->getValue())->toBe('Check')
            ->and($sheet->getCell('M2')->getValue())->toBe(DailyDonationReportExcel::CHECK_UNCHECKED)
            ->and($sheet->getCell('A1')->getValue())->toBe('Order UUID')
            ->and($sheet->getCell('F1')->getValue())->toBe('Cause')
            ->and($sheet->getCell('I1')->getValue())->toBe('Payment Provider');

        $headers = [];
        for ($col = 1; $col <= 14; $col++) {
            $headers[] = (string) $sheet->getCell([$col, 1])->getValue();
        }

        expect($headers)->not->toContain('Status');

        $donorNames = [];
        for ($row = 2; $row <= 10; $row++) {
            $name = (string) $sheet->getCell([3, $row])->getValue();
            if ($name !== '') {
                $donorNames[] = $name;
            }
        }

        expect($donorNames)->toContain('Today Paid')
            ->and($donorNames)->not->toContain('Today Pending')
            ->and($donorNames)->not->toContain('Today Failed')
            ->and($donorNames)->not->toContain('Yesterday Donor');

        return true;
    });
});

it('defaults to yesterday when --date is omitted', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-09-08 01:00:00', 'Asia/Kolkata'));

    config([
        'donation.daily_report_emails' => ['siddharthvaniya123@gmail.com'],
    ]);

    $yesterday = Carbon::parse('2026-09-07 14:00:00', 'Asia/Kolkata');

    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'daily-report-default-yesterday',
        'donor_name' => 'Yesterday Default',
        'donor_email' => 'yesterday-default@example.com',
        'donor_phone' => '9000000005',
        'currency' => 'INR',
        'total_amount' => 150,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $yesterday,
    ]);
    $order->forceFill([
        'created_at' => $yesterday,
        'updated_at' => $yesterday,
    ])->saveQuietly();

    $this->artisan('donations:email-daily-report')->assertSuccessful();

    Mail::assertSent(DailyDonationReportMail::class, function (DailyDonationReportMail $mail): bool {
        return $mail->orderCount === 1
            && $mail->reportDate->toDateString() === '2026-09-07'
            && $mail->attachmentName === 'donations_2026-09-07.xlsx'
            && $mail->formalBritishDate() === '7th September 2026'
            && $mail->envelope()->subject === 'Daily donations — 7th September 2026';
    });

    Carbon::setTestNow();
});

it('skips sending when no report emails are configured', function () {
    Mail::fake();

    config(['donation.daily_report_emails' => []]);

    $this->artisan('donations:email-daily-report')->assertSuccessful();

    Mail::assertNothingSent();
});
