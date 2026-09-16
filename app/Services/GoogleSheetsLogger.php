<?php

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\DonationOrder;
use App\Support\RazorpayDonationLabels;
use Carbon\Carbon;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GoogleSheetsLogger
{
    /**
     * Log donation using DonationOrder (SOURCE OF TRUTH)
     */
    public function logDonation(DonationOrder $order, string $status): void
    {
        try {
            $service = $this->getGoogleSheetsService();
            $spreadsheetId = $this->spreadsheetId();

            $order->loadMissing('items.causeModel');
            [$sheetName, $sheetId, $rowData, $donateFor] = $this->prepareSheetWrite($service, $spreadsheetId, $order, $status);

            $service->spreadsheets_values->append(
                $spreadsheetId,
                $sheetName.'!A2',
                new Google_Service_Sheets_ValueRange(['values' => [$rowData]]),
                ['valueInputOption' => 'RAW']
            );

            $this->highlightLastRow(
                $service,
                $spreadsheetId,
                $sheetId,
                $sheetName,
                $status,
                $donateFor
            );
        } catch (\Throwable $e) {
            Log::error('❌ Google Sheets logging error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing sheet row for a donation (matched by receipt no, then payment id).
     * Falls back to append when no matching row is found.
     */
    public function updateDonationRow(DonationOrder $order, string $status = 'captured'): void
    {
        try {
            $service = $this->getGoogleSheetsService();
            $spreadsheetId = $this->spreadsheetId();

            $order->loadMissing('items.causeModel');
            [$sheetName, $sheetId, $rowData, $donateFor] = $this->prepareSheetWrite($service, $spreadsheetId, $order, $status);

            $rowNumber = $this->findDonationRowNumber($service, $spreadsheetId, $sheetName, $order);

            if ($rowNumber === null) {
                Log::warning('Google Sheet row not found for donation update; appending instead.', [
                    'order_id' => $order->id,
                    'receipt' => $order->receiptNumberFormatted(),
                    'payment_id' => $order->provider_payment_id,
                    'sheet' => $sheetName,
                ]);

                $service->spreadsheets_values->append(
                    $spreadsheetId,
                    $sheetName.'!A2',
                    new Google_Service_Sheets_ValueRange(['values' => [$rowData]]),
                    ['valueInputOption' => 'RAW']
                );

                $this->highlightLastRow(
                    $service,
                    $spreadsheetId,
                    $sheetId,
                    $sheetName,
                    $status,
                    $donateFor
                );

                return;
            }

            $service->spreadsheets_values->update(
                $spreadsheetId,
                $sheetName.'!A'.$rowNumber.':R'.$rowNumber,
                new Google_Service_Sheets_ValueRange(['values' => [$rowData]]),
                ['valueInputOption' => 'RAW']
            );
        } catch (\Throwable $e) {
            Log::error('❌ Google Sheets update error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{0: string, 1: int, 2: array<int, mixed>, 3: string}
     */
    private function prepareSheetWrite(
        Google_Service_Sheets $service,
        string $spreadsheetId,
        DonationOrder $order,
        string $status,
    ): array {
        $paidAt = $order->paid_at ?? now();
        $timestamp = Carbon::parse($paidAt)
            ->setTimezone(config('app.timezone', 'Asia/Kolkata'));

        $items = $order->items;
        $donateFor = $this->resolveDonateFor($items);
        $rowData = $this->buildRowData($order, $status, $timestamp, $items, $donateFor);
        $sheetName = $timestamp->format('F Y');

        $sheetId = $this->getSheetIdByName($service, $spreadsheetId, $sheetName);
        if (! $sheetId) {
            $sheetId = $this->createNewSheet($service, $spreadsheetId, $sheetName);
        }

        $this->ensureHeaderRow($service, $spreadsheetId, $sheetId, $sheetName);

        return [$sheetName, $sheetId, $rowData, $donateFor];
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function buildRowData(
        DonationOrder $order,
        string $status,
        Carbon $timestamp,
        Collection $items,
        string $donateFor,
    ): array {
        return [
            $order->provider_payment_id ?? '',
            $order->receiptNumberFormatted(),
            $order->donor_name ?? '',
            $order->donor_email ?? '',
            $order->donor_phone ?? '',
            NumberHelper::formatWholeAmount($order->total_amount ?? 0),
            strtoupper($status),
            $donateFor,
            $this->resolvePackage($items),
            $this->resolveQuantity($items),
            $timestamp->format('d-m-Y'),
            $timestamp->format('h:i:s A'),
            $order->pan_number ?? '',
            $order->address ?? '',
            $order->pincode ?? '',
            $order->city ?? '',
            $order->state ?? '',
            $order->country ?? '',
        ];
    }

    private function findDonationRowNumber(
        $service,
        string $spreadsheetId,
        string $sheetName,
        DonationOrder $order,
    ): ?int {
        $rangeResponse = $service->spreadsheets_values->get($spreadsheetId, $sheetName.'!A:B');
        $values = $rangeResponse->getValues();

        if (! is_array($values) || $values === []) {
            return null;
        }

        $receipt = trim($order->receiptNumberFormatted());
        $paymentId = trim((string) ($order->provider_payment_id ?? ''));

        foreach ($values as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowPaymentId = trim((string) ($row[0] ?? ''));
            $rowReceipt = trim((string) ($row[1] ?? ''));

            if ($receipt !== '' && $rowReceipt === $receipt) {
                return $index + 1;
            }

            if ($paymentId !== '' && $rowPaymentId === $paymentId) {
                return $index + 1;
            }
        }

        return null;
    }

    private function spreadsheetId(): string
    {
        $spreadsheetId = (string) config('services.google.sheet_id');

        if ($spreadsheetId === '') {
            throw new \Exception('Google sheet id is not configured.');
        }

        return $spreadsheetId;
    }

    /**
     * Google Sheets client
     */
    private function getGoogleSheetsService(): Google_Service_Sheets
    {
        $client = new Google_Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);
        $client->setAccessType('offline');

        $tokenPath = storage_path('app/google-token.json');

        if (! file_exists($tokenPath)) {
            Log::critical('Google token file missing: '.$tokenPath);
            throw new \Exception('Google token file not found.');
        }

        $token = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if (! $refreshToken) {
                throw new \Exception('Google refresh token missing.');
            }

            $client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        return new Google_Service_Sheets($client);
    }

    /**
     * Create new monthly sheet
     */
    private function createNewSheet($service, $spreadsheetId, $sheetName): int
    {
        $batchRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'addSheet' => [
                    'properties' => ['title' => $sheetName],
                ],
            ]],
        ]);

        $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
        $sheetId = $response->getReplies()[0]->getAddSheet()->getProperties()->getSheetId();

        $service->spreadsheets_values->update(
            $spreadsheetId,
            $sheetName.'!A1',
            new Google_Service_Sheets_ValueRange([
                'values' => [[
                    'Payment ID',
                    'Receipt No',
                    'Donor Name',
                    'Email',
                    'Contact',
                    'Amount',
                    'Status',
                    'Donate For',
                    'Package',
                    'Quantity',
                    'Date',
                    'Time',
                    'PAN',
                    'Address',
                    'Pincode',
                    'City',
                    'State',
                    'Country',
                ]],
            ]),
            ['valueInputOption' => 'RAW']
        );

        return $sheetId;
    }

    /**
     * Highlight last appended row
     */
    private function highlightLastRow($service, $spreadsheetId, $sheetId, $sheetName, $status, $donateFor): void
    {
        $rangeResponse = $service->spreadsheets_values->get($spreadsheetId, $sheetName.'!A1:R');
        $values = $rangeResponse->getValues();
        $rowNumber = is_array($values) ? count($values) : 1;

        $colorMap = [
            'tree' => ['red' => 0.85, 'green' => 1.0,  'blue' => 0.85],
            'vruddhashram' => ['red' => 0.85, 'green' => 0.90, 'blue' => 1.0],
            'animal' => ['red' => 0.85, 'green' => 1.0,  'blue' => 1.0],
            'unknown' => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95],
        ];

        $key = $this->normalizeCause($donateFor);
        $statusKey = strtolower(trim($status));
        $color = $statusKey === 'failed'
            ? ['red' => 1.0, 'green' => 0.8, 'blue' => 0.8]
            : ($colorMap[$key] ?? $colorMap['unknown']);

        $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $rowNumber - 1,
                        'endRowIndex' => $rowNumber,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $color,
                        ],
                    ],
                    'fields' => 'userEnteredFormat.backgroundColor',
                ],
            ]],
        ]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $requestBody);
    }

    /**
     * Get sheet ID by name
     */
    private function getSheetIdByName($service, $spreadsheetId, $sheetName): ?int
    {
        $sheets = $service->spreadsheets->get($spreadsheetId)->getSheets();
        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->title === $sheetName) {
                return $sheet->getProperties()->sheetId;
            }
        }

        return null;
    }

    /**
     * Resolve the donation cause label from items.
     */
    private function resolveDonateFor(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'unknown';
        }

        $causes = $items->map(function ($item): string {
            $meta = is_array($item->meta) ? $item->meta : [];

            if (! empty($meta['cause_title'])) {
                return trim((string) $meta['cause_title']);
            }

            if ($item->relationLoaded('causeModel') && $item->causeModel) {
                return trim((string) $item->causeModel->title);
            }

            return trim((string) ($item->cause ?? ''));
        })
            ->filter()
            ->unique()
            ->values();

        if ($causes->isEmpty()) {
            return 'unknown';
        }

        if ($causes->count() === 1) {
            return $causes->first();
        }

        return $causes->implode(', ');
    }

    /**
     * Resolve total quantity from items.
     */
    private function resolveQuantity(Collection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->sum('quantity');
    }

    /**
     * Resolve package title(s) from items.
     */
    private function resolvePackage(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $titles = $items
            ->filter(static fn ($item) => $item->cause_package_id !== null)
            ->map(static fn ($item) => RazorpayDonationLabels::sheetPackageLabel(
                $item->cause_package_id,
                $item->title
            ))
            ->filter()
            ->unique()
            ->values();

        return $titles->implode(', ');
    }

    /**
     * Normalize cause labels to known keys for highlighting.
     */
    private function normalizeCause(string $donateFor): string
    {
        $value = strtolower(trim($donateFor));

        if ($value === '') {
            return 'unknown';
        }

        if (str_contains($value, 'tree')) {
            return 'tree';
        }

        if (str_contains($value, 'vruddha') || str_contains($value, 'vriddha') || str_contains($value, 'old age')) {
            return 'vruddhashram';
        }

        if (str_contains($value, 'animal')) {
            return 'animal';
        }

        return $value;
    }

    /**
     * Ensure header row exists (and insert if missing).
     */
    private function ensureHeaderRow($service, $spreadsheetId, int $sheetId, string $sheetName): void
    {
        $expected = [
            'Payment ID',
            'Receipt No',
            'Donor Name',
            'Email',
            'Contact',
            'Amount',
            'Status',
            'Donate For',
            'Package',
            'Quantity',
            'Date',
            'Time',
            'PAN',
            'Address',
            'Pincode',
            'City',
            'State',
            'Country',
        ];

        $rangeResponse = $service->spreadsheets_values->get($spreadsheetId, $sheetName.'!A1:R1');
        $values = $rangeResponse->getValues();
        $firstCell = is_array($values) && isset($values[0][0]) ? trim((string) $values[0][0]) : '';

        if ($firstCell !== 'Payment ID') {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
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
            ]);

            $service->spreadsheets->batchUpdate($spreadsheetId, $requestBody);
        }

        $service->spreadsheets_values->update(
            $spreadsheetId,
            $sheetName.'!A1',
            new Google_Service_Sheets_ValueRange([
                'values' => [$expected],
            ]),
            ['valueInputOption' => 'RAW']
        );
    }
}
