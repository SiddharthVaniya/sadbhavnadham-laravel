<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\Donor;
use Illuminate\Http\Request;

class PanRequirementService
{
    public function threshold(): int
    {
        return max(0, (int) config('donation.pan_threshold_inr', 100000));
    }

    public function isRequired(Cause $cause, float $currentAmount, string $email, string $phone): bool
    {
        if (! $cause->pan_required) {
            return false;
        }

        $threshold = $this->threshold();

        if ($currentAmount >= $threshold) {
            return true;
        }

        $fyPaidTotal = $this->financialYearPaidTotal($phone);

        return ($fyPaidTotal + $currentAmount) >= $threshold;
    }

    /**
     * @return array{
     *     required: bool,
     *     current_amount: float,
     *     fy_paid_total: float,
     *     combined_total: float,
     *     threshold: int,
     *     known_pan_number: string|null
     * }
     */
    public function evaluate(Cause $cause, float $currentAmount, string $email, string $phone): array
    {
        $fyPaidTotal = $this->financialYearPaidTotal($phone);
        $combinedTotal = $fyPaidTotal + $currentAmount;
        $threshold = $this->threshold();
        $required = $cause->pan_required
            && ($currentAmount >= $threshold || $combinedTotal >= $threshold);

        return [
            'required' => $required,
            'current_amount' => round($currentAmount, 2),
            'fy_paid_total' => round($fyPaidTotal, 2),
            'combined_total' => round($combinedTotal, 2),
            'threshold' => $threshold,
            'known_pan_number' => $this->knownPanNumber($phone),
        ];
    }

    public function resolveDonationTotal(Cause $cause, ?int $packageId, ?float $amount, int $quantity): float
    {
        $quantity = max(1, $quantity);

        if ($packageId) {
            $package = $cause->relationLoaded('packages')
                ? $cause->packages->firstWhere('id', $packageId)
                : CausePackage::query()->where('cause_id', $cause->id)->find($packageId);

            if ($package) {
                return (float) $package->amount * $quantity;
            }
        }

        $unitAmount = (float) ($amount ?? 0);

        return $unitAmount * $quantity;
    }

    public function resolveDonationTotalFromRequest(Request $request, Cause $cause): float
    {
        return $this->resolveDonationTotal(
            $cause,
            $request->filled('package_id') ? (int) $request->input('package_id') : null,
            $request->filled('amount') ? (float) $request->input('amount') : null,
            max(1, (int) $request->input('quantity', 1)),
        );
    }

    public function financialYearPaidTotal(string $phone): float
    {
        $phone = trim($phone);

        if ($phone === '') {
            return 0.0;
        }

        [$start, $end] = IndianFinancialYear::range();

        $donorIds = Donor::query()
            ->where('phone', $phone)
            ->pluck('id');

        return (float) DonationOrder::query()
            ->paid()
            ->whereBetween('paid_at', [$start, $end])
            ->where(function ($query) use ($phone, $donorIds): void {
                $query->where('donor_phone', $phone);

                if ($donorIds->isNotEmpty()) {
                    $query->orWhereIn('donor_id', $donorIds);
                }
            })
            ->sum('total_amount');
    }

    public function knownPanNumber(string $phone): ?string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        $donorPan = Donor::query()
            ->where('phone', $phone)
            ->whereNotNull('pan_number')
            ->where('pan_number', '!=', '')
            ->orderByDesc('last_donated_at')
            ->value('pan_number');

        if (is_string($donorPan) && $donorPan !== '') {
            return strtoupper($donorPan);
        }

        $orderPan = DonationOrder::query()
            ->paid()
            ->where('donor_phone', $phone)
            ->whereNotNull('pan_number')
            ->where('pan_number', '!=', '')
            ->latest('paid_at')
            ->value('pan_number');

        return is_string($orderPan) && $orderPan !== '' ? strtoupper($orderPan) : null;
    }
}
