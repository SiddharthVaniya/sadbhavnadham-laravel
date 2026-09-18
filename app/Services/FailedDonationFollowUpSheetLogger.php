<?php

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\DonationOrder;
use Carbon\Carbon;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FailedDonationFollowUpSheetLogger
{
    public const SHEET_TITLE = 'Failed Follow-ups';

    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Order UUID',
        'Donor Name',
        'Phone',
        'Email',
        'Amount',
        'Cause',
        'Failed At',
        'Payment Link',
        'Provider Order',
        'Assigned To',
        'Contacted',
        'Follow-up Notes',
        'Outcome',
        'Next Follow-up',
    ];

    public function __construct(
        private GoogleSheetsClientFactory $clientFactory,
    ) {}

    public function isConfigured(): bool
    {
        return $this->spreadsheetId() !== '';
    }

    public function log(DonationOrder $order): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google failed follow-up sheet id is not configured.');
        }

        $order->loadMissing('items.causeModel');

        $service = $this->clientFactory->make();
        $spreadsheetId = $this->spreadsheetId();
        $sheetId = $this->ensureSheet($service, $spreadsheetId);

        if ($this->findRowNumberByUuid($service, $spreadsheetId, (string) $order->order_uuid) !== null) {
            Log::info('Failed follow-up sheet row already exists; skipping append', [
                'order_id' => $order->id,
                'order_uuid' => $order->order_uuid,
            ]);

            return;
        }

        $service->spreadsheets_values->append(
            $spreadsheetId,
            self::SHEET_TITLE.'!A2',
            new Google_Service_Sheets_ValueRange([
                'values' => [$this->buildRowData($order)],
            ]),
            ['valueInputOption' => 'RAW']
        );

        Log::info('Failed follow-up sheet row appended', [
            'order_id' => $order->id,
            'order_uuid' => $order->order_uuid,
            'sheet_id' => $sheetId,
        ]);
    }

    public function refreshPaymentLink(DonationOrder $order): void
    {
        if (! $this->isConfigured() || ! filled($order->payment_link_url) || ! filled($order->order_uuid)) {
            return;
        }

        $service = $this->clientFactory->make();
        $spreadsheetId = $this->spreadsheetId();
        $this->ensureSheet($service, $spreadsheetId);

        $rowNumber = $this->findRowNumberByUuid($service, $spreadsheetId, (string) $order->order_uuid);
        if ($rowNumber === null) {
            return;
        }

        $service->spreadsheets_values->update(
            $spreadsheetId,
            self::SHEET_TITLE.'!H'.$rowNumber,
            new Google_Service_Sheets_ValueRange([
                'values' => [[(string) $order->payment_link_url]],
            ]),
            ['valueInputOption' => 'RAW']
        );

        Log::info('Failed follow-up sheet payment link refreshed', [
            'order_id' => $order->id,
            'order_uuid' => $order->order_uuid,
            'row' => $rowNumber,
        ]);
    }

    /**
     * @return list<string|int|float>
     */
    public function buildRowData(DonationOrder $order): array
    {
        $failedAt = $order->failed_at
            ? Carbon::parse($order->failed_at)->setTimezone(config('app.timezone', 'Asia/Kolkata'))
            : now()->setTimezone(config('app.timezone', 'Asia/Kolkata'));

        return [
            (string) $order->order_uuid,
            (string) ($order->donor_name ?? ''),
            (string) ($order->donor_phone ?? ''),
            (string) ($order->donor_email ?? ''),
            NumberHelper::formatWholeAmount($order->total_amount ?? 0),
            $this->resolveCause($order),
            $failedAt->format('d-m-Y h:i A'),
            (string) ($order->payment_link_url ?? ''),
            (string) ($order->provider_order_id ?? ''),
            '',
            '',
            '',
            '',
            '',
        ];
    }

    private function spreadsheetId(): string
    {
        return trim((string) config('services.google.failed_sheet_id'));
    }

    private function ensureSheet(Google_Service_Sheets $service, string $spreadsheetId): int
    {
        $sheetId = $this->getSheetIdByName($service, $spreadsheetId, self::SHEET_TITLE);

        if ($sheetId === null) {
            $sheetId = $this->createSheet($service, $spreadsheetId, self::SHEET_TITLE);
        }

        $this->ensureHeaderRow($service, $spreadsheetId, $sheetId);

        return $sheetId;
    }

    private function createSheet(Google_Service_Sheets $service, string $spreadsheetId, string $sheetName): int
    {
        $batchRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'addSheet' => [
                    'properties' => ['title' => $sheetName],
                ],
            ]],
        ]);

        $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);

        return (int) $response->getReplies()[0]->getAddSheet()->getProperties()->getSheetId();
    }

    private function getSheetIdByName(Google_Service_Sheets $service, string $spreadsheetId, string $sheetName): ?int
    {
        $sheets = $service->spreadsheets->get($spreadsheetId)->getSheets();

        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->title === $sheetName) {
                return (int) $sheet->getProperties()->sheetId;
            }
        }

        return null;
    }

    private function ensureHeaderRow(Google_Service_Sheets $service, string $spreadsheetId, int $sheetId): void
    {
        $rangeResponse = $service->spreadsheets_values->get($spreadsheetId, self::SHEET_TITLE.'!A1:N1');
        $values = $rangeResponse->getValues();
        $firstCell = is_array($values) && isset($values[0][0]) ? trim((string) $values[0][0]) : '';

        if ($firstCell !== 'Order UUID') {
            if ($firstCell !== '') {
                $service->spreadsheets->batchUpdate(
                    $spreadsheetId,
                    new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => [[
                            'insertDimension' => [
                                'range' => [
                                    'sheetId' => $sheetId,
                                    'dimension' => 'ROWS',
                                    'startIndex' => 0,
                                    'endIndex' => 1,
                                ],
                                'inheritFromBefore' => false,
                            ],
                        ]],
                    ])
                );
            }

            $service->spreadsheets_values->update(
                $spreadsheetId,
                self::SHEET_TITLE.'!A1',
                new Google_Service_Sheets_ValueRange([
                    'values' => [self::HEADERS],
                ]),
                ['valueInputOption' => 'RAW']
            );
        }
    }

    private function findRowNumberByUuid(Google_Service_Sheets $service, string $spreadsheetId, string $uuid): ?int
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        $rangeResponse = $service->spreadsheets_values->get($spreadsheetId, self::SHEET_TITLE.'!A:A');
        $values = $rangeResponse->getValues();

        if (! is_array($values) || $values === []) {
            return null;
        }

        foreach ($values as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if (trim((string) ($row[0] ?? '')) === $uuid) {
                return $index + 1;
            }
        }

        return null;
    }

    private function resolveCause(DonationOrder $order): string
    {
        $item = $order->items->first();
        if (! $item) {
            return '';
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        if (! empty($meta['cause_title'])) {
            return trim((string) $meta['cause_title']);
        }

        if ($item->relationLoaded('causeModel') && $item->causeModel) {
            return trim((string) $item->causeModel->title);
        }

        return trim((string) ($item->cause ?? ''));
    }
}
