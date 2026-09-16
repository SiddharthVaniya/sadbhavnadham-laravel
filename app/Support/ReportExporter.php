<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * @param  array<string, mixed>  $report
     */
    public static function download(array $report, string $format): Response|StreamedResponse
    {
        $format = strtolower($format);
        $basename = self::basename($report, $format);

        return match ($format) {
            'xlsx' => self::excelResponse($report, $basename),
            'pdf' => self::pdfResponse($report, $basename),
            default => self::csvResponse($report, $basename),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>|list<string|int|float>>  $rows
     */
    public static function downloadTable(
        string $title,
        string $periodLabel,
        array $headers,
        array $rows,
        string $format,
    ): Response|StreamedResponse {
        return self::download([
            'type' => 'table',
            'title' => $title,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d M Y, h:i A'),
            'headers' => $headers,
            'rows' => $rows,
        ], $format);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function basename(array $report, string $format): string
    {
        $type = (string) ($report['type'] ?? 'report');
        $extension = $format === 'xlsx' ? 'xls' : $format;

        return sprintf('%s_%s.%s', $type, now()->format('Y-m-d_His'), $extension);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function csvResponse(array $report, string $filename): StreamedResponse
    {
        $rows = self::tabularRows($report);

        return response()->stream(function () use ($report, $rows): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [(string) ($report['title'] ?? 'Report'), (string) ($report['periodLabel'] ?? '')]);
            fputcsv($file, ['Generated at', (string) ($report['generatedAt'] ?? now()->format('d M Y, h:i A'))]);
            fputcsv($file, []);

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function excelResponse(array $report, string $filename): Response
    {
        $rows = self::tabularRows($report);
        $xml = self::buildExcelXml($report, $rows);

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function pdfResponse(array $report, string $filename): Response
    {
        $pdf = Pdf::loadView('admin.reports.export', [
            'report' => $report,
            'rows' => self::tabularRows($report),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(DompdfGujaratiFont::options());

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    public static function tabularRows(array $report): array
    {
        $type = (string) ($report['type'] ?? '');

        return match ($type) {
            'cause_summary' => self::causeSummaryRows($report),
            'package_summary' => self::packageSummaryRows($report),
            'monthly_summary' => self::monthlySummaryRows($report),
            'daily_summary' => self::dailySummaryRows($report),
            'donation_detail' => self::donationDetailRows($report),
            'table' => self::genericTableRows($report),
            default => self::monthlyByCauseRows($report),
        };
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function genericTableRows(array $report): array
    {
        $headers = array_values($report['headers'] ?? []);
        $rows = [$headers];

        foreach ($report['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_is_list($row)) {
                $rows[] = array_map(
                    fn ($cell) => is_scalar($cell) || $cell === null ? $cell ?? '' : (string) $cell,
                    $row,
                );

                continue;
            }

            $line = [];

            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $line[] = is_scalar($value) || $value === null ? $value ?? '' : (string) $value;
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function monthlyByCauseRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $line = [(string) ($row['label'] ?? '')];

            foreach ($row['values'] ?? [] as $value) {
                $line[] = round((float) $value, 2);
            }

            $line[] = round((float) ($row['total'] ?? 0), 2);
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function causeSummaryRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $rows[] = [
                (string) ($row['cause'] ?? ''),
                (int) ($row['donation_count'] ?? 0),
                round((float) ($row['total_amount'] ?? 0), 2),
                round((float) ($row['average_amount'] ?? 0), 2),
            ];
        }

        $rows[] = ['Total', '', round((float) ($report['grandTotal'] ?? 0), 2), ''];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function packageSummaryRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $rows[] = [
                (string) ($row['package'] ?? ''),
                (string) ($row['cause'] ?? ''),
                (int) ($row['donation_count'] ?? 0),
                round((float) ($row['total_amount'] ?? 0), 2),
                round((float) ($row['average_amount'] ?? 0), 2),
            ];
        }

        $rows[] = ['Total', '', '', round((float) ($report['grandTotal'] ?? 0), 2), ''];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function monthlySummaryRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $rows[] = [
                (string) ($row['month'] ?? ''),
                (int) ($row['donation_count'] ?? 0),
                round((float) ($row['total_amount'] ?? 0), 2),
                round((float) ($row['average_amount'] ?? 0), 2),
            ];
        }

        $rows[] = ['Total', '', round((float) ($report['grandTotal'] ?? 0), 2), ''];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function dailySummaryRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $rows[] = [
                (string) ($row['day'] ?? ''),
                (int) ($row['donation_count'] ?? 0),
                round((float) ($row['total_amount'] ?? 0), 2),
                round((float) ($row['average_amount'] ?? 0), 2),
            ];
        }

        $rows[] = ['Total', '', round((float) ($report['grandTotal'] ?? 0), 2), ''];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private static function donationDetailRows(array $report): array
    {
        $rows = [array_values($report['headers'] ?? [])];

        foreach ($report['rows'] ?? [] as $row) {
            $rows[] = [
                (string) ($row['paid_at'] ?? ''),
                (string) ($row['order_uuid'] ?? ''),
                (string) ($row['donor_name'] ?? ''),
                (string) ($row['donor_email'] ?? ''),
                (string) ($row['donor_phone'] ?? ''),
                (string) ($row['state'] ?? ''),
                (string) ($row['partner_name'] ?? ''),
                (string) ($row['sid_code'] ?? ''),
                (string) ($row['cause'] ?? ''),
                (string) ($row['item_title'] ?? ''),
                round((float) ($row['amount'] ?? 0), 2),
                (string) ($row['payment_provider'] ?? ''),
                (string) ($row['receipt_number'] ?? ''),
            ];
        }

        $rows[] = ['', '', '', '', '', '', '', '', '', 'Total', round((float) ($report['grandTotal'] ?? 0), 2), '', ''];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<list<string|int|float>>  $rows
     */
    private static function buildExcelXml(array $report, array $rows): string
    {
        $sheetName = htmlspecialchars((string) ($report['title'] ?? 'Report'), ENT_XML1);
        $metaRows = [
            ['Report', (string) ($report['title'] ?? 'Report')],
            ['Period', (string) ($report['periodLabel'] ?? '')],
            ['Generated at', (string) ($report['generatedAt'] ?? now()->format('d M Y, h:i A'))],
            [],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="'.$sheetName.'"><Table>';

        foreach (array_merge($metaRows, $rows) as $row) {
            $xml .= '<Row>';

            if ($row === []) {
                $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
            } else {
                foreach ($row as $cell) {
                    $type = is_numeric($cell) && $cell !== '' ? 'Number' : 'String';
                    $value = htmlspecialchars((string) $cell, ENT_XML1);
                    $xml .= '<Cell><Data ss:Type="'.$type.'">'.$value.'</Data></Cell>';
                }
            }

            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }
}
