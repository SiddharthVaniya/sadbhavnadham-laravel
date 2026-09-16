<?php

namespace App\Support;

use App\Models\DonationOrder;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DailyDonationReportExcel
{
    public const CHECK_UNCHECKED = '☐';

    public const CHECK_CHECKED = '☑';

    private const CHECK_COLUMN = 'M';

    /**
     * @param  iterable<int, DonationOrder>  $orders
     */
    public function build(iterable $orders, Carbon $reportDate): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Donations');

        $headers = [
            'Order UUID',
            'Payment ID',
            'Donor Name',
            'Donor Email',
            'Donor Phone',
            'Cause',
            'Cause title',
            'Amount (INR)',
            'Payment Provider',
            'Receipt Number',
            'Created At',
            'Paid At',
            'Check',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        $sheet->freezePane('A2');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        $rowNumber = 2;

        foreach ($orders as $order) {
            foreach (AdminInertiaData::donationTableRows($order) as $row) {
                $values = [
                    $row['uuid'] ?? '',
                    $row['payment_id'] ?? '',
                    $row['donor_name'] ?? '',
                    $row['donor_email'] ?? '',
                    $row['donor_phone'] ?? '',
                    ($row['cause'] ?? '') === '—' ? '' : ($row['cause'] ?? ''),
                    ($row['cause_title'] ?? '') === '—' ? '' : ($row['cause_title'] ?? ''),
                    $row['total_amount'] ?? '',
                    $order->payment_provider,
                    $order->hasReceipt() ? $order->receiptNumberFormatted() : '',
                    $order->created_at?->format('Y-m-d H:i:s') ?? '',
                    $order->paid_at?->format('Y-m-d H:i:s') ?? '',
                    self::CHECK_UNCHECKED,
                ];

                foreach ($values as $index => $value) {
                    $sheet->setCellValue([$index + 1, $rowNumber], $value);
                }

                $rowNumber++;
            }
        }

        $lastDataRow = max(2, $rowNumber - 1);
        $this->applyCheckValidation($sheet, $lastDataRow);

        foreach (range(1, 13) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $temp = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($temp);
        $spreadsheet->disconnectWorksheets();
        rewind($temp);
        $contents = stream_get_contents($temp) ?: '';
        fclose($temp);

        return $contents;
    }

    private function applyCheckValidation(Worksheet $sheet, int $lastDataRow): void
    {
        $validation = $sheet->getCell(self::CHECK_COLUMN.'2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid check');
        $validation->setError('Choose ☐ or ☑.');
        $validation->setFormula1('"'.self::CHECK_UNCHECKED.','.self::CHECK_CHECKED.'"');
        $validation->setSqref(self::CHECK_COLUMN.'2:'.self::CHECK_COLUMN.$lastDataRow);
    }
}
