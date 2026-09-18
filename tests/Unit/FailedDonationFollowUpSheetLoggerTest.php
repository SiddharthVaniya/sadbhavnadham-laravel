<?php

namespace Tests\Unit;

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Services\FailedDonationFollowUpSheetLogger;
use App\Services\GoogleSheetsClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedDonationFollowUpSheetLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_curated_follow_up_row_with_empty_manual_columns(): void
    {
        $cause = Cause::query()->create([
            'slug' => 'tree-plantation-'.Str::random(5),
            'title' => 'Tree Plantation',
            'excerpt' => 'Trees',
            'description' => 'Plant trees',
            'images' => [],
            'details' => [],
            'allow_custom_amount' => true,
            'allow_recurring' => false,
            'pan_required' => true,
            'default_amount' => 500,
            'default_title' => 'General',
            'cta_text' => 'Donate',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $order = DonationOrder::create([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'provider_order_id' => 'order_row_shape',
            'donor_name' => 'Row Shape Donor',
            'donor_email' => 'row@example.com',
            'donor_phone' => '9898237948',
            'currency' => 'INR',
            'total_amount' => 1500.4,
            'status' => DonationOrder::STATUS_FAILED,
            'failed_at' => now()->setTimezone('Asia/Kolkata')->setTime(14, 30),
            'payment_link_url' => 'https://rzp.io/i/row',
        ]);

        DonationItem::create([
            'donation_order_id' => $order->id,
            'cause_id' => $cause->id,
            'cause' => $cause->title,
            'title' => 'Package A',
            'amount' => 1500,
            'unit_amount' => 1500,
            'quantity' => 1,
        ]);

        $order->load('items.causeModel');

        $logger = new FailedDonationFollowUpSheetLogger(app(GoogleSheetsClientFactory::class));
        $row = $logger->buildRowData($order);

        $this->assertCount(14, $row);
        $this->assertSame($order->order_uuid, $row[0]);
        $this->assertSame('Row Shape Donor', $row[1]);
        $this->assertSame('9898237948', $row[2]);
        $this->assertSame('row@example.com', $row[3]);
        $this->assertSame('1500', $row[4]);
        $this->assertSame('Tree Plantation', $row[5]);
        $this->assertSame('https://rzp.io/i/row', $row[7]);
        $this->assertSame('order_row_shape', $row[8]);
        $this->assertSame('', $row[9]);
        $this->assertSame('', $row[10]);
        $this->assertSame('', $row[11]);
        $this->assertSame('', $row[12]);
        $this->assertSame('', $row[13]);
    }

    public function test_reports_configured_when_failed_sheet_id_is_set(): void
    {
        config(['services.google.failed_sheet_id' => 'abc123']);

        $logger = new FailedDonationFollowUpSheetLogger(app(GoogleSheetsClientFactory::class));

        $this->assertTrue($logger->isConfigured());
    }

    public function test_reports_not_configured_when_failed_sheet_id_is_blank(): void
    {
        config(['services.google.failed_sheet_id' => '']);

        $logger = new FailedDonationFollowUpSheetLogger(app(GoogleSheetsClientFactory::class));

        $this->assertFalse($logger->isConfigured());
    }
}
