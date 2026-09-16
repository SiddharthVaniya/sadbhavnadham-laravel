<?php

namespace Tests\Unit;

use App\Models\DonationOrder;
use App\Services\GoogleSheetsLogger;
use ReflectionClass;
use Tests\TestCase;

class GoogleSheetsLoggerTest extends TestCase
{
    public function test_highlight_last_row_counts_the_full_row_range_when_column_a_is_blank(): void
    {
        $batchStub = new class
        {
            public $requestBody;

            public function batchUpdate($spreadsheetId, $requestBody)
            {
                $this->requestBody = $requestBody;
            }
        };

        $spreadsheetsValuesStub = new class
        {
            public $lastRange;

            public function get($spreadsheetId, $range)
            {
                $this->lastRange = $range;

                return new class
                {
                    public function getValues()
                    {
                        return [
                            ['Payment ID', 'Receipt No'],
                            ['', 'MSCT-RZP-'],
                        ];
                    }
                };
            }
        };

        $service = new class($spreadsheetsValuesStub, $batchStub)
        {
            public $spreadsheets_values;

            public $spreadsheets;

            public function __construct($spreadsheetsValues, $spreadsheets)
            {
                $this->spreadsheets_values = $spreadsheetsValues;
                $this->spreadsheets = $spreadsheets;
            }
        };

        $logger = new GoogleSheetsLogger;
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('highlightLastRow');
        $method->setAccessible(true);

        $method->invoke($logger, $service, 'spreadsheet-id', 1, 'May 2026', 'failed', 'old-age-home');

        $this->assertSame('May 2026!A1:R', $spreadsheetsValuesStub->lastRange);
        $this->assertNotNull($batchStub->requestBody);
        $this->assertSame(1, $batchStub->requestBody->getRequests()[0]['repeatCell']['range']['startRowIndex']);
    }

    public function test_find_donation_row_number_matches_receipt_then_payment_id(): void
    {
        $spreadsheetsValuesStub = new class
        {
            public function get($spreadsheetId, $range)
            {
                return new class
                {
                    public function getValues()
                    {
                        return [
                            ['Payment ID', 'Receipt No'],
                            ['pay_old', 'MSCT-RZP-100'],
                            ['pay_target', 'MSCT-RZP-701'],
                        ];
                    }
                };
            }
        };

        $service = new class($spreadsheetsValuesStub)
        {
            public $spreadsheets_values;

            public function __construct($spreadsheetsValues)
            {
                $this->spreadsheets_values = $spreadsheetsValues;
            }
        };

        $order = new DonationOrder([
            'provider_payment_id' => 'pay_target',
            'receipt_number' => 701,
        ]);

        $logger = new GoogleSheetsLogger;
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('findDonationRowNumber');
        $method->setAccessible(true);

        $row = $method->invoke($logger, $service, 'spreadsheet-id', 'July 2026', $order);

        $this->assertSame(3, $row);
    }
}
