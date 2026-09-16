<?php

namespace App\Http\Controllers;

use App\Helpers\NumberHelper;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class ReceiptDemoController extends Controller
{
    public function show(): View
    {
        $cause = new Cause([
            'title' => 'Tree Plantation',
            'slug' => 'tree-plantation',
        ]);

        $package = new CausePackage([
            'title' => 'Sapling Pack',
            'amount' => 1000,
        ]);

        $item = new DonationItem([
            'cause' => 'tree-plantation',
            'title' => 'Sapling Pack',
            'quantity' => 1,
            'unit_amount' => 1000,
            'amount' => 1000,
        ]);
        $item->setRelation('causeModel', $cause);
        $item->setRelation('package', $package);

        $order = new DonationOrder([
            'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
            'donor_name' => 'John Doe',
            'donor_email' => 'john.doe@example.com',
            'donor_phone' => '9999999999',
            'address' => '12 Green Avenue',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'pincode' => '360001',
            'country' => 'India',
            'total_amount' => 1000,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => Carbon::parse('2026-01-01', config('app.timezone')),
            'receipt_number' => 1234,
        ]);
        $order->setRelation('items', collect([$item]));
        $order->setRelation('donor', null);

        return view(config('receipt.view', 'receipts.donation-minimal'), [
            'order' => $order,
            'amountInWords' => NumberHelper::amountInWords(1000),
            'demoReceipt' => true,
        ]);
    }
}
