<?php

namespace Tests\Unit;

use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Services\GoogleSheetsLogger;
use App\Support\RazorpayDonationLabels;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class RazorpayDonationLabelsTest extends TestCase
{
    public function test_custom_donation_uses_custom_amount_description(): void
    {
        $cause = new \App\Models\Cause([
            'title' => 'Old Age Home',
            'slug' => 'old-age-home',
            'default_title' => 'Morning Breakfast',
        ]);

        $this->assertSame(
            'Donation: Old Age Home - Custom Amount',
            RazorpayDonationLabels::description($cause, null)
        );
    }

    public function test_package_donation_uses_cause_and_package_in_description(): void
    {
        $cause = new \App\Models\Cause([
            'title' => 'Old Age Home',
            'slug' => 'old-age-home',
        ]);

        $package = new \App\Models\CausePackage([
            'title' => 'Morning Breakfast',
        ]);

        $this->assertSame(
            'Donation: Old Age Home - Morning Breakfast',
            RazorpayDonationLabels::description($cause, $package)
        );
    }

    public function test_order_notes_include_cause_and_package_names(): void
    {
        $cause = new \App\Models\Cause([
            'title' => 'Old Age Home',
            'slug' => 'old-age-home',
        ]);

        $package = new \App\Models\CausePackage([
            'title' => 'Morning Breakfast',
        ]);
        $package->id = 1;

        $order = new DonationOrder;
        $order->id = 374;

        $this->assertSame([
            'donation_order_id' => '374',
            'cause' => 'old-age-home',
            'cause_name' => 'Old Age Home',
            'package_name' => 'Morning Breakfast',
            'cause_package_id' => '1',
        ], RazorpayDonationLabels::orderNotes($order, $cause, $package));
    }

    public function test_custom_donation_notes_exclude_package_id(): void
    {
        $cause = new \App\Models\Cause([
            'title' => 'Old Age Home',
            'slug' => 'old-age-home',
            'default_title' => 'Morning Breakfast',
        ]);

        $order = new DonationOrder;
        $order->id = 370;

        $this->assertSame([
            'donation_order_id' => '370',
            'cause' => 'old-age-home',
            'cause_name' => 'Old Age Home',
            'package_name' => '',
        ], RazorpayDonationLabels::orderNotes($order, $cause, null));
    }

    public function test_google_sheets_package_column_is_blank_for_custom_donations(): void
    {
        $logger = new GoogleSheetsLogger;
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('resolvePackage');
        $method->setAccessible(true);

        $items = new Collection([
            new DonationItem([
                'cause_package_id' => null,
                'title' => 'Morning Breakfast',
            ]),
        ]);

        $this->assertSame('', $method->invoke($logger, $items));
    }

    public function test_google_sheets_package_column_uses_selected_package_title(): void
    {
        $logger = new GoogleSheetsLogger;
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('resolvePackage');
        $method->setAccessible(true);

        $items = new Collection([
            new DonationItem([
                'cause_package_id' => 1,
                'title' => 'Morning Breakfast',
            ]),
        ]);

        $this->assertSame('Morning Breakfast', $method->invoke($logger, $items));
    }
}
