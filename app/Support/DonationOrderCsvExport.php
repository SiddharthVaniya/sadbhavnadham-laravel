<?php

namespace App\Support;

use App\Models\DonationOrder;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationOrderCsvExport
{
    public static function stream(Builder $exportQuery, ?string $causeTitleFilter = null, ?string $filenamePrefix = 'donations'): StreamedResponse
    {
        $filename = $filenamePrefix.'_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $qualifiedKey = (new DonationOrder)->getQualifiedKeyName();
        $keyName = (new DonationOrder)->getKeyName();

        $callback = function () use ($exportQuery, $causeTitleFilter, $qualifiedKey, $keyName): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Order UUID',
                'Payment ID',
                'Donor Name',
                'Donor Email',
                'Donor Phone',
                'Cause',
                'Cause title',
                'Amount (INR)',
                'Status',
                'Telecaller response',
                'Telecaller name',
                'Telecaller noted at',
                'Payment Provider',
                'Receipt Number',
                'Created At',
                'Paid At',
            ]);

            $exportQuery->with(['items.causeModel', 'items.package'])->chunkById(500, function ($orders) use ($file, $causeTitleFilter): void {
                AdminInertiaData::prepareTelecallerNotesForOrders($orders);

                foreach ($orders as $order) {
                    $telecaller = AdminInertiaData::lastTelecallerNoteForOrder((int) $order->id);

                    foreach (AdminInertiaData::donationTableRows($order, $causeTitleFilter) as $row) {
                        fputcsv($file, [
                            $row['uuid'],
                            $row['payment_id'],
                            $row['donor_name'],
                            $row['donor_email'],
                            $row['donor_phone'],
                            $row['cause'] === '—' ? '' : $row['cause'],
                            $row['cause_title'] === '—' ? '' : $row['cause_title'],
                            $row['total_amount'],
                            $row['status'],
                            $telecaller['outcome_label'] ?? $telecaller['message'] ?? '',
                            $telecaller['name'] ?? '',
                            $telecaller['at'] ?? '',
                            $order->payment_provider,
                            $order->hasReceipt() ? $order->receiptNumberFormatted() : '',
                            $order->created_at?->format('Y-m-d H:i:s'),
                            $order->paid_at?->format('Y-m-d H:i:s'),
                        ]);
                    }
                }

                AdminInertiaData::clearTelecallerNoteCache();
            }, $qualifiedKey, $keyName);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
