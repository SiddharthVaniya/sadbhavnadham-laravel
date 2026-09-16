<?php

namespace App\Http\Controllers;

use App\Helpers\NumberHelper;
use App\Models\DonationOrder;
use App\Support\DonorPortalData;
use App\Support\DonorPortalSession;
use Illuminate\View\View;

class DonorPortalController extends Controller
{
    public function __construct(private DonorPortalSession $portalSession) {}

    public function index(): View
    {
        $donor = $this->portalSession->donor();
        abort_unless($donor !== null, 401);

        $orders = $donor->linkedDonationOrdersQuery()
            ->paid()
            ->with(['items.causeModel'])
            ->latest('paid_at')
            ->latest('id')
            ->paginate(20);

        $paidOrdersQuery = $donor->linkedDonationOrdersQuery()->paid();

        return view('donate.portal.index', [
            'donor' => $donor,
            'donorPortal' => $this->portalSession->toFrontend(),
            'orders' => $orders,
            'summary' => DonorPortalData::summary(
                (int) $paidOrdersQuery->count(),
                (float) $paidOrdersQuery->sum('total_amount'),
            ),
        ]);
    }

    public function receipt(DonationOrder $order): View
    {
        $donor = $this->portalSession->donor();
        abort_unless($donor !== null, 401);
        abort_unless($this->donorOwnsOrder($donor->id, $order), 404);
        abort_unless($order->isPaid(), 404);

        $amountInWords = NumberHelper::amountInWords((float) $order->total_amount);

        return view(config('receipt.view', 'receipts.donation-minimal'), [
            'order' => $order->loadMissing(['items.causeModel', 'items.package']),
            'amountInWords' => $amountInWords,
            'donorPortalView' => true,
        ]);
    }

    private function donorOwnsOrder(int $donorId, DonationOrder $order): bool
    {
        $donor = $this->portalSession->donor();

        if ($donor === null || $donor->id !== $donorId) {
            return false;
        }

        return $donor->linkedDonationOrdersQuery()
            ->whereKey($order->getKey())
            ->exists();
    }
}
