<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDonationOrderRequest;
use App\Http\Requests\Admin\UpdateDonationOrderRequest;
use App\Jobs\UpdateDonationOnSheetJob;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Services\AnalyticsService;
use App\Services\DonationAttributionService;
use App\Services\DonationPaymentService;
use App\Services\PostalCodeLookupService;
use App\Support\AdminInertiaData;
use App\Support\AdminInertiaResources;
use App\Support\DonationVisibility;
use App\Support\PanRequirementService;
use App\Support\PeriodRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDonationController extends Controller
{
    public function __construct(
        private DonationPaymentService $donationPaymentService,
        private AnalyticsService $analytics,
    ) {}

    public function index(Request $request): Response
    {
        $duration = $this->resolveDonationsListDuration($request);
        $query = $this->filteredDonationsQuery($request, $duration);

        $overviewBase = clone $query;

        $totalCollected = (float) (clone $overviewBase)->sum('total_amount');
        $paidAmount = (float) (clone $overviewBase)
            ->where('status', DonationOrder::STATUS_PAID)
            ->sum('total_amount');
        $failedCount = (clone $overviewBase)
            ->where('status', DonationOrder::STATUS_FAILED)
            ->count();
        $totalCount = (clone $overviewBase)->count();

        $statusCounts = (clone $overviewBase)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $paymentMethodBreakdown = (clone $overviewBase)
            ->where('status', DonationOrder::STATUS_PAID)
            ->select(
                DB::raw("COALESCE(NULLIF(payment_provider, ''), 'Unknown') as payment_provider"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as amount')
            )
            ->groupBy('payment_provider')
            ->orderByDesc('amount')
            ->get();

        $paidMethodTotal = (float) $paymentMethodBreakdown->sum(fn ($row) => (float) $row->amount);

        $paymentMethodBreakdown = $paymentMethodBreakdown
            ->map(function ($row) use ($paidMethodTotal) {
                $amount = (float) $row->amount;
                $percentage = $paidMethodTotal > 0
                    ? round(($amount / $paidMethodTotal) * 100, 1)
                    : 0.0;

                return (object) [
                    'name' => DonationOrder::providerDisplayName((string) $row->payment_provider),
                    'count' => (int) $row->count,
                    'amount' => $amount,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        $activeFilterCount = collect([
            $request->filled('from_date'),
            $request->filled('to_date'),
            $request->filled('status'),
            $request->filled('provider'),
            $request->filled('cause_id'),
            $request->filled('package_id'),
            $request->filled('cause_title'),
            $request->filled('search'),
            $request->filled('source'),
            $request->filled('platform'),
            $request->filled('utm_campaign'),
            $request->filled('utm_content'),
        ])->filter()->count();

        [$sort, $dir] = $this->resolveDonationsListSort($request);
        $this->applyDonationsListSort($query, $sort, $dir);

        $donations = $query
            ->paginate(25)
            ->withQueryString(); // keeps filters during pagination

        $causeTitleFilter = $request->filled('cause_title')
            ? trim((string) $request->input('cause_title'))
            : null;

        $durationOptions = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This week',
            'last_week' => 'Previous week',
            'this_month' => 'This month',
            'last_month' => 'Previous month',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'last_90_days' => 'Last 90 days',
            'all' => 'All time',
            'custom' => 'Custom range',
        ];

        $campaignOptions = DonationOrder::query()
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->distinct()
            ->orderBy('utm_campaign')
            ->limit(150)
            ->pluck('utm_campaign')
            ->values();

        $employeeOptions = DonationOrder::query()
            ->whereNotNull('utm_content')
            ->where('utm_content', '!=', '')
            ->distinct()
            ->orderBy('utm_content')
            ->limit(150)
            ->pluck('utm_content')
            ->values();

        return Inertia::render('Admin/Donations/Index', [
            'donations' => AdminInertiaData::paginatedDonations(
                $donations,
                causeTitleFilter: $causeTitleFilter,
            ),
            'duration' => $duration,
            'durationOptions' => $durationOptions,
            'overviewDateLabel' => $this->formatOverviewDateLabel($request, $duration, $durationOptions),
            'paidAmount' => $paidAmount,
            'attemptVolume' => $totalCollected,
            'failedCount' => $failedCount,
            'totalCount' => $totalCount,
            'statusCounts' => [
                'paid' => (int) ($statusCounts[DonationOrder::STATUS_PAID] ?? 0),
                'pending' => (int) ($statusCounts[DonationOrder::STATUS_PENDING] ?? 0),
                'failed' => (int) ($statusCounts[DonationOrder::STATUS_FAILED] ?? 0),
            ],
            'paymentMethodBreakdown' => $paymentMethodBreakdown->map(fn (object $method) => [
                'name' => $method->name,
                'count' => $method->count,
                'amount' => $method->amount,
                'percentage' => $method->percentage,
            ])->values(),
            'activeFilterCount' => $activeFilterCount,
            'providerOptions' => [
                ['value' => DonationOrder::PROVIDER_RAZORPAY, 'label' => 'Razorpay (incl. QR)'],
                ['value' => DonationOrder::PROVIDER_RAZORPAY_QR, 'label' => 'Razorpay QR only'],
                ['value' => DonationOrder::PROVIDER_OFFLINE, 'label' => 'Offline'],
                ['value' => DonationOrder::PROVIDER_DANAMOJO, 'label' => 'Danamojo'],
            ],
            'sourceOptions' => collect(DonationAttributionService::trafficSourceOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'platformOptions' => collect(DonationAttributionService::platformOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'campaignOptions' => $campaignOptions,
            'employeeOptions' => $employeeOptions,
            'causes' => Cause::query()->orderBy('title')->get(['id', 'title']),
            'packages' => CausePackage::query()
                ->with('cause:id,title')
                ->orderBy('cause_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'cause_id', 'title'])
                ->map(fn (CausePackage $package) => [
                    'id' => $package->id,
                    'cause_id' => $package->cause_id,
                    'title' => $package->title,
                    'label' => $package->cause
                        ? $package->cause->title.' · '.$package->title
                        : $package->title,
                ])
                ->values(),
            'sort' => $sort,
            'dir' => $dir,
            'filters' => [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'status' => $request->input('status'),
                'provider' => $request->input('provider'),
                'cause_id' => $request->input('cause_id'),
                'package_id' => $request->input('package_id'),
                'cause_title' => $request->input('cause_title'),
                'search' => $request->input('search'),
                'source' => $request->input('source'),
                'platform' => $request->input('platform'),
                'utm_campaign' => $request->input('utm_campaign'),
                'utm_content' => $request->input('utm_content'),
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $duration = $this->resolveDonationsListDuration($request);

        [$sort, $dir] = $this->resolveDonationsListSort($request);
        $exportQuery = $this->filteredDonationsQuery($request, $duration);
        $this->applyDonationsListSort($exportQuery, $sort, $dir);
        $orders = $exportQuery->get();
        $causeTitleFilter = $request->filled('cause_title')
            ? trim((string) $request->input('cause_title'))
            : null;

        $filename = 'donations_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders, $causeTitleFilter): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Order UUID',
                'Payment ID',
                'Donor Name',
                'Donor Email',
                'Donor Phone',
                'Cause',
                'Cause title',
                'Amount (INR)',
                'Status',
                'Payment Provider',
                'Receipt Number',
                'Created At',
                'Paid At',
            ]);

            foreach ($orders as $order) {
                foreach (AdminInertiaData::donationTableRows($order, $causeTitleFilter) as $row) {
                    fputcsv($file, [
                        $row['uuid'],
                        $row['payment_id'],
                        $row['donor_name'],
                        $row['donor_email'],
                        $row['donor_phone'],
                        $row['cause'] === '—' ? '' : $row['cause'],
                        $row['cause_title'] === '—' ? '' : $row['cause_title'],
                        $row['total_amount'],
                        $row['status'],
                        $order->payment_provider,
                        $order->hasReceipt() ? $order->receiptNumberFormatted() : '',
                        $order->created_at?->format('Y-m-d H:i:s'),
                        $order->paid_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function formatOverviewDateLabel(Request $request, string $duration, array $durationOptions): string
    {
        if ($duration === 'custom') {
            $fromDate = $request->filled('from_date')
                ? Carbon::parse((string) $request->from_date)->format('d M Y')
                : null;
            $toDate = $request->filled('to_date')
                ? Carbon::parse((string) $request->to_date)->format('d M Y')
                : null;

            if ($fromDate && $toDate) {
                return $fromDate.' – '.$toDate;
            }

            if ($fromDate) {
                return 'From '.$fromDate;
            }

            if ($toDate) {
                return 'Until '.$toDate;
            }

            return $durationOptions['custom'] ?? 'Custom range';
        }

        return $durationOptions[$duration] ?? 'Today';
    }

    public function create(): Response
    {
        $nextReceipts = $this->donationPaymentService->peekNextReceiptNumbersByProvider();
        $defaultProvider = DonationOrder::PROVIDER_OFFLINE;

        return Inertia::render('Admin/Donations/Create', [
            'causes' => Cause::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'title', 'pan_required']),
            'packages' => CausePackage::query()
                ->where('is_active', true)
                ->whereHas('cause', fn (Builder $query) => $query->where('is_active', true))
                ->with('cause:id,title')
                ->orderBy('cause_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'cause_id', 'title', 'amount'])
                ->map(fn (CausePackage $package) => [
                    'id' => $package->id,
                    'cause_id' => $package->cause_id,
                    'title' => $package->title,
                    'amount' => (float) $package->amount,
                    'label' => $package->cause
                        ? $package->cause->title.' · '.$package->title
                        : $package->title,
                ])
                ->values(),
            'paymentProviders' => collect(DonationOrder::paymentProviderOptions())
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'next_receipts' => $nextReceipts,
            'next_receipt_number' => $nextReceipts[$defaultProvider]['number'],
            'next_receipt_formatted' => $nextReceipts[$defaultProvider]['formatted'],
        ]);
    }

    public function postalLookup(Request $request, PostalCodeLookupService $postalCodeLookup): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'postal' => ['required', 'string', 'max:16'],
        ]);

        $result = $postalCodeLookup->lookup(
            (string) $validated['country_code'],
            (string) $validated['postal'],
        );

        if ($result === null) {
            return response()->json([
                'found' => false,
                'message' => 'Postal code not found. Please enter city and state manually.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            ...$result,
        ]);
    }

    public function panRequirement(Request $request, PanRequirementService $panRequirement): JsonResponse
    {
        $validated = $request->validate([
            'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
            'total_amount' => ['nullable', 'numeric', 'min:1', 'max:500000'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $amount = (float) ($validated['total_amount'] ?? 0);
        $email = (string) ($validated['donor_email'] ?? '');
        $phone = (string) ($validated['donor_phone'] ?? '');

        if (empty($validated['cause_id'])) {
            $fyPaidTotal = $phone !== '' ? $panRequirement->financialYearPaidTotal($phone) : 0.0;
            $combinedTotal = $fyPaidTotal + $amount;
            $threshold = $panRequirement->threshold();

            return response()->json([
                'required' => $amount >= $threshold || $combinedTotal >= $threshold,
                'current_amount' => round($amount, 2),
                'fy_paid_total' => round($fyPaidTotal, 2),
                'combined_total' => round($combinedTotal, 2),
                'threshold' => $threshold,
                'known_pan_number' => $phone !== '' ? $panRequirement->knownPanNumber($phone) : null,
            ]);
        }

        $cause = Cause::query()->findOrFail((int) $validated['cause_id']);

        if (! $cause->pan_required) {
            return response()->json([
                'required' => false,
                'current_amount' => round($amount, 2),
                'fy_paid_total' => 0,
                'combined_total' => round($amount, 2),
                'threshold' => $panRequirement->threshold(),
                'known_pan_number' => null,
            ]);
        }

        return response()->json(
            $panRequirement->evaluate($cause, $amount, $email, $phone)
        );
    }

    public function store(StoreDonationOrderRequest $request)
    {
        $donorSnapshot = $this->donorSnapshotFromRequest($request);

        if ($donorSnapshot['donor_name'] === '') {
            $donorSnapshot['donor_name'] = 'Unknown Donor';
        }

        if ($donorSnapshot['donor_phone'] === '') {
            // Unique placeholder so QR/manual rows without phone do not merge into one donor.
            $donorSnapshot['donor_phone'] = 'u-'.substr(md5(uniqid((string) mt_rand(), true)), 0, 12);
        }

        $donor = $this->resolveDonorFromSnapshot($donorSnapshot);

        $donationDate = Carbon::parse(
            $request->input('donation_date') ?: now()->toDateString()
        )->startOfDay();

        $paymentProvider = (string) ($request->input('payment_provider') ?: DonationOrder::PROVIDER_OFFLINE);

        $order = DonationOrder::create([
            'payment_provider' => $paymentProvider,
            'source_channel' => DonationAttributionService::CHANNEL_OFFLINE,
            'provider_order_id' => 'manual-'.now()->format('YmdHis').'-'.uniqid(),
            'donor_id' => $donor?->id,
            'created_by' => $request->user()?->id,
            'donor_name' => $donorSnapshot['donor_name'],
            'donor_email' => $donorSnapshot['donor_email'],
            'donor_phone' => $donorSnapshot['donor_phone'],
            'pan_number' => $donorSnapshot['pan_number'],
            'date_of_birth' => $donorSnapshot['date_of_birth'],
            'address' => $donorSnapshot['address'],
            'pincode' => $donorSnapshot['pincode'],
            'city' => $donorSnapshot['city'],
            'state' => $donorSnapshot['state'],
            'country' => $donorSnapshot['country'],
            'donor_country_code' => $donorSnapshot['donor_country_code'],
            'consent_indian_citizen' => $donorSnapshot['consent_indian_citizen'],
            'currency' => 'INR',
            'total_amount' => $request->total_amount,
            'status' => DonationOrder::STATUS_PAID,
            'paid_at' => $donationDate,
        ]);

        $order->created_at = $donationDate;
        $order->saveQuietly();

        $this->analytics->stampDonationIpLocation($order, $request);

        if ($request->filled('cause_id') || $request->filled('item_title') || $request->filled('cause_package_id')) {
            $cause = $request->filled('cause_id') ? Cause::query()->find($request->cause_id) : null;
            $package = $request->filled('cause_package_id') ? CausePackage::query()->find($request->cause_package_id) : null;

            $quantity = max(1, (int) $request->input('quantity', 1));
            $unitAmount = $package?->amount ?? $request->total_amount;

            DonationItem::create([
                'donation_order_id' => $order->id,
                'cause_id' => $cause?->id,
                'cause_package_id' => $package?->id,
                'cause' => $cause?->slug ?? $cause?->title ?? '',
                'title' => $request->item_title ?: ($package?->title ?? $cause?->title ?? ''),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'amount' => $request->total_amount,
            ]);
        }

        $sendReceiptEmail = $request->boolean('send_receipt_email');
        $sendWhatsAppThankYou = $request->boolean('send_whatsapp_thank_you');
        $sendWhatsAppCertificate = $request->boolean('send_whatsapp_certificate');

        $this->donationPaymentService->generateManualReceipt(
            $order,
            $sendReceiptEmail,
            $sendWhatsAppThankYou,
            $sendWhatsAppCertificate,
            $request->filled('receipt_number') ? (int) $request->input('receipt_number') : null,
        );

        $messageParts = ['Offline donation saved.'];

        if ($sendReceiptEmail) {
            $messageParts[] = 'Receipt email queued.';
        }

        if ($sendWhatsAppThankYou) {
            $messageParts[] = 'Thank-you WhatsApp queued.';
        }

        if ($sendWhatsAppCertificate) {
            $messageParts[] = 'Certificate WhatsApp queued.';
        }

        $messageParts[] = 'Google Sheet entry queued.';

        $message = implode(' ', $messageParts);

        toastr()->success($message);

        $redirectRoute = DonationVisibility::userCanViewAll($request->user())
            ? 'admin.donations.index'
            : 'admin.donations.offline';

        return redirect()->route($redirectRoute)
            ->with('status', $message);
    }

    public function edit(DonationOrder $donationOrder): Response
    {
        abort_unless($donationOrder->isPaid(), 404);
        $this->authorize('update', $donationOrder);

        $donationOrder->load('items');

        return Inertia::render('Admin/Donations/Edit', [
            'donation' => AdminInertiaResources::donationEditForm($donationOrder),
            'causes' => Cause::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'title', 'pan_required']),
            'packages' => CausePackage::query()
                ->where('is_active', true)
                ->whereHas('cause', fn (Builder $query) => $query->where('is_active', true))
                ->with('cause:id,title')
                ->orderBy('cause_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'cause_id', 'title', 'amount'])
                ->map(fn (CausePackage $package) => [
                    'id' => $package->id,
                    'cause_id' => $package->cause_id,
                    'title' => $package->title,
                    'amount' => (float) $package->amount,
                    'label' => $package->cause
                        ? $package->cause->title.' · '.$package->title
                        : $package->title,
                ])
                ->values(),
        ]);
    }

    public function update(UpdateDonationOrderRequest $request, DonationOrder $donationOrder)
    {
        abort_unless($donationOrder->isPaid(), 404);
        $this->authorize('update', $donationOrder);

        $donationOrder->loadMissing('items');

        if ($donationOrder->allowsAdminAmountEdit()) {
            $this->updateOfflineDonation($request, $donationOrder);
            $this->syncDonationItemCause($donationOrder, $request, updateAmounts: true);
            $message = 'Offline donation updated.';
            $redirect = redirect()->route('admin.donations.offline');
        } elseif ($donationOrder->allowsAdminDonorEdit()) {
            $this->updateDonationDonorDetails($request, $donationOrder);
            $this->syncDonationItemCause($donationOrder, $request, updateAmounts: false);
            $message = 'Donor details updated.';
            $redirect = redirect()->route('admin.donations.show', $donationOrder);
        } else {
            $this->syncDonationItemCause($donationOrder, $request, updateAmounts: false);
            $message = 'Donation cause updated.';
            $redirect = redirect()->route('admin.donations.show', $donationOrder);
        }

        dispatch(new UpdateDonationOnSheetJob($donationOrder->fresh(['items.causeModel'])));

        toastr()->success($message);

        return $redirect->with('status', $message);
    }

    private function updateOfflineDonation(UpdateDonationOrderRequest $request, DonationOrder $donationOrder): void
    {
        $donorSnapshot = $this->donorSnapshotFromRequest($request);

        $donor = $this->resolveDonorFromSnapshot($donorSnapshot);

        $donationDate = Carbon::parse(
            $request->input('donation_date') ?: ($donationOrder->paid_at?->toDateString() ?? now()->toDateString())
        )->startOfDay();

        $donationOrder->update([
            'donor_id' => $donor?->id ?? $donationOrder->donor_id,
            'donor_name' => $donorSnapshot['donor_name'],
            'donor_email' => $donorSnapshot['donor_email'],
            'donor_phone' => $donorSnapshot['donor_phone'],
            'pan_number' => $donorSnapshot['pan_number'],
            'date_of_birth' => $donorSnapshot['date_of_birth'],
            'address' => $donorSnapshot['address'],
            'pincode' => $donorSnapshot['pincode'],
            'city' => $donorSnapshot['city'],
            'state' => $donorSnapshot['state'],
            'country' => $donorSnapshot['country'],
            'donor_country_code' => $donorSnapshot['donor_country_code'],
            'total_amount' => $request->total_amount,
            'paid_at' => $donationDate,
        ]);

        $donationOrder->created_at = $donationDate;
        $donationOrder->saveQuietly();
    }

    private function updateDonationDonorDetails(UpdateDonationOrderRequest $request, DonationOrder $donationOrder): void
    {
        $donorSnapshot = $this->donorSnapshotFromRequest($request);
        $donor = $this->resolveDonorFromSnapshot($donorSnapshot);

        $donationOrder->update([
            'donor_id' => $donor?->id ?? $donationOrder->donor_id,
            'donor_name' => $donorSnapshot['donor_name'] !== ''
                ? $donorSnapshot['donor_name']
                : $donationOrder->donor_name,
            'donor_email' => $donorSnapshot['donor_email'],
            'donor_phone' => $donorSnapshot['donor_phone'],
            'pan_number' => $donorSnapshot['pan_number'],
            'date_of_birth' => $donorSnapshot['date_of_birth'],
            'address' => $donorSnapshot['address'],
            'pincode' => $donorSnapshot['pincode'],
            'city' => $donorSnapshot['city'],
            'state' => $donorSnapshot['state'],
            'country' => $donorSnapshot['country'],
            'donor_country_code' => $donorSnapshot['donor_country_code'],
        ]);
    }

    /**
     * @return array{donor_name: string, donor_email: string, donor_phone: string, date_of_birth: mixed, pan_number: mixed, address: mixed, pincode: mixed, city: mixed, state: mixed, country: string, donor_country_code: string, consent_indian_citizen: bool}
     */
    private function donorSnapshotFromRequest(Request $request): array
    {
        return [
            'donor_name' => trim((string) ($request->input('donor_name') ?? '')),
            'donor_email' => mb_strtolower(trim((string) ($request->input('donor_email') ?? ''))),
            'donor_phone' => trim((string) ($request->input('donor_phone') ?? '')),
            'date_of_birth' => $request->date_of_birth,
            'pan_number' => $request->pan_number,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'city' => $request->city,
            'state' => $request->state,
            'country' => strtoupper((string) ($request->input('country') ?: 'INDIA')),
            'donor_country_code' => strtoupper((string) ($request->input('donor_country_code') ?: 'IN')),
            'consent_indian_citizen' => $this->isIndiaDonorSnapshot(
                (string) ($request->input('country') ?: 'INDIA'),
                (string) ($request->input('donor_country_code') ?: 'IN'),
            ),
        ];
    }

    private function isIndiaDonorSnapshot(string $country, string $countryCode): bool
    {
        $country = strtoupper(trim($country));
        $countryCode = strtoupper(trim($countryCode));

        return $countryCode === 'IN'
            || in_array($country, ['INDIA', 'IN', 'BHARAT'], true);
    }

    /**
     * Anonymous entries (no email and no phone) are not linked to a donor
     * profile so they don't all collapse into a single donor record.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function resolveDonorFromSnapshot(array $snapshot): ?Donor
    {
        if ($snapshot['donor_email'] === '' && $snapshot['donor_phone'] === '') {
            return null;
        }

        return Donor::resolveFromDonationSnapshot($snapshot);
    }

    private function syncDonationItemCause(
        DonationOrder $donationOrder,
        UpdateDonationOrderRequest $request,
        bool $updateAmounts,
    ): void {
        $cause = $request->filled('cause_id') ? Cause::query()->find($request->cause_id) : null;
        $package = $request->filled('cause_package_id') ? CausePackage::query()->find($request->cause_package_id) : null;
        $item = $donationOrder->items()->first();

        $title = $request->input('item_title') ?: ($package?->title ?? $cause?->title);

        $itemPayload = [
            'cause_id' => $cause?->id,
            'cause_package_id' => $package?->id,
            // Both columns are NOT NULL; fall back to the stored values.
            'cause' => $cause?->slug ?? $cause?->title ?? $item?->cause ?? '',
            'title' => $title ?? $item?->title ?? '',
        ];

        $quantity = max(1, (int) $request->input('quantity', $item?->quantity ?? 1));

        if ($updateAmounts) {
            $itemPayload['quantity'] = $quantity;
            $itemPayload['unit_amount'] = $package?->amount ?? $request->total_amount;
            $itemPayload['amount'] = $request->total_amount;
        } elseif ($donationOrder->allowsAdminDonorEdit()) {
            $orderTotal = (float) $donationOrder->total_amount;
            $itemPayload['quantity'] = $quantity;
            $itemPayload['unit_amount'] = $package?->amount ?? ($orderTotal / $quantity);
            $itemPayload['amount'] = $orderTotal;
        }

        if ($item) {
            $meta = is_array($item->meta) ? $item->meta : [];
            $meta['cause_title'] = $cause?->title;
            $meta['cause_slug'] = $cause?->slug;
            unset($meta['campaign']);

            $item->update([
                ...$itemPayload,
                'meta' => array_filter($meta),
                'donation_campaign_id' => null,
            ]);

            return;
        }

        if ($cause || $package || $request->filled('item_title')) {
            DonationItem::create([
                'donation_order_id' => $donationOrder->id,
                ...$itemPayload,
                'quantity' => $itemPayload['quantity'] ?? 1,
                'unit_amount' => $itemPayload['unit_amount'] ?? $donationOrder->total_amount,
                'amount' => $itemPayload['amount'] ?? $donationOrder->total_amount,
                'meta' => array_filter([
                    'cause_title' => $cause?->title,
                    'cause_slug' => $cause?->slug,
                ]),
            ]);
        }
    }

    public function offline(Request $request): Response
    {
        // Manual entries use "manual-" provider_order_id; Razorpay multi-use QR
        // payments are auto-created with provider razorpay_qr (and qr-* ids).
        $query = DonationOrder::query()
            ->where(function (Builder $providerQuery): void {
                $providerQuery
                    ->where('payment_provider', DonationOrder::PROVIDER_OFFLINE)
                    ->orWhere('payment_provider', DonationOrder::PROVIDER_RAZORPAY_QR)
                    ->orWhere('provider_order_id', 'like', 'manual-%')
                    ->orWhere('provider_order_id', 'like', 'qr-%');
            })
            ->with(['items.causeModel', 'items.package']);

        DonationVisibility::apply($query, $request->user());

        $this->applyCreatedAtDateRange($query, $request->input('from_date'), $request->input('to_date'));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('cause_id')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('cause_id', $request->cause_id);
            });
        }

        $donations = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $causes = Cause::where('is_active', true)->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/Donations/Offline', [
            'donations' => AdminInertiaData::paginatedDonations($donations, offline: true),
            'causes' => $causes,
            'filters' => [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'search' => $request->input('search'),
                'cause_id' => $request->input('cause_id'),
            ],
        ]);
    }

    public function show(Request $request, DonationOrder $donationOrder): Response
    {
        $this->authorize('view', $donationOrder);

        $donationOrder->load('items.causeModel');

        return Inertia::render('Admin/Donations/Show', [
            'donation' => AdminInertiaResources::donationDetail($donationOrder),
            'back_url' => $this->resolveDonationsBackUrl($request),
        ]);
    }

    private function filteredDonationsQuery(Request $request, string $duration = 'all'): Builder
    {
        $query = DonationOrder::query()->with(['items.causeModel', 'items.package']);

        DonationVisibility::apply($query, $request->user());

        // Search must look across all donations unless the user picks an explicit custom range.
        if ($duration === 'custom') {
            $this->applyCreatedAtDateRange($query, $request->input('from_date'), $request->input('to_date'));
        } elseif (! $this->hasDonationsSearchTerm($request)) {
            $this->applyDurationFilter($query, $duration);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('provider')) {
            $this->applyProviderFilter($query, (string) $request->provider);
        }

        if ($request->filled('cause_id')) {
            $causeId = (int) $request->input('cause_id');
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('cause_id', $causeId));
        }

        if ($request->filled('package_id')) {
            $packageId = (int) $request->input('package_id');
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('cause_package_id', $packageId));
        }

        if ($request->filled('cause_title')) {
            $this->applyCauseTitleFilter($query, trim((string) $request->input('cause_title')));
        }

        if ($request->filled('source')) {
            DonationAttributionService::applyTrafficSourceFilter($query, (string) $request->input('source'));
        }

        if ($request->filled('platform')) {
            DonationAttributionService::applyPlatformFilter($query, (string) $request->input('platform'));
        }

        if ($request->filled('utm_campaign')) {
            $query->where('utm_campaign', (string) $request->input('utm_campaign'));
        }

        if ($request->filled('utm_content')) {
            $query->where('utm_content', (string) $request->input('utm_content'));
        }

        if ($this->hasDonationsSearchTerm($request)) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('provider_payment_id', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhere('utm_source', 'like', "%{$search}%")
                    ->orWhere('utm_campaign', 'like', "%{$search}%")
                    ->orWhere('utm_content', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDonationsListSort(Request $request): array
    {
        $sort = (string) $request->input('sort', 'created_at_ts');
        $allowed = [
            'payment_id',
            'donor_name',
            'source',
            'cause',
            'cause_title',
            'total_amount',
            'status',
            'city',
            'created_at_ts',
            'created_at',
        ];

        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at_ts';
        }

        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [$sort, $dir];
    }

    private function applyDonationsListSort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'payment_id') {
            $query->orderByRaw('COALESCE(provider_payment_id, provider_order_id) '.$dir)
                ->orderBy('id', $dir);

            return;
        }

        if ($sort === 'cause') {
            $query->orderBy(
                DonationItem::query()
                    ->select('causes.title')
                    ->leftJoin('causes', 'causes.id', '=', 'donation_items.cause_id')
                    ->whereColumn('donation_items.donation_order_id', 'donation_orders.id')
                    ->orderBy('donation_items.id')
                    ->limit(1),
                $dir
            )->orderBy('donation_orders.id', $dir);

            return;
        }

        if ($sort === 'cause_title') {
            $query->orderBy(
                DonationItem::query()
                    ->selectRaw('COALESCE(cause_packages.title, donation_items.title)')
                    ->leftJoin('cause_packages', 'cause_packages.id', '=', 'donation_items.cause_package_id')
                    ->whereColumn('donation_items.donation_order_id', 'donation_orders.id')
                    ->orderBy('donation_items.id')
                    ->limit(1),
                $dir
            )->orderBy('donation_orders.id', $dir);

            return;
        }

        if ($sort === 'source') {
            $query->orderByRaw('COALESCE(utm_source, \'\') '.$dir)
                ->orderBy('id', $dir);

            return;
        }

        $column = match ($sort) {
            'donor_name' => 'donor_name',
            'total_amount' => 'total_amount',
            'status' => 'status',
            'city' => 'city',
            default => 'created_at',
        };

        $query->orderBy($column, $dir)->orderBy('id', $dir);
    }

    private function resolveDonationsBackUrl(Request $request): string
    {
        $candidate = $request->query('return');

        if (is_string($candidate) && $this->isSafeDonationsIndexUrl($candidate)) {
            session(['admin.donations.index_url' => $candidate]);

            return $candidate;
        }

        $fromSession = session('admin.donations.index_url');

        if (is_string($fromSession) && $this->isSafeDonationsIndexUrl($fromSession)) {
            return $fromSession;
        }

        return route('admin.donations.index');
    }

    private function isSafeDonationsIndexUrl(string $url): bool
    {
        if (str_contains($url, '://') || str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }

        if (! str_starts_with($url, '/admin/donations')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path === '/admin/donations' || $path === '/admin/donations/';
    }

    /**
     * When staff search by donor/phone/etc., default "Today" must not hide older matches.
     */
    private function resolveDonationsListDuration(Request $request): string
    {
        $duration = (string) $request->input('duration', 'today');

        if ($this->hasDonationsSearchTerm($request) && $duration !== 'custom') {
            return 'all';
        }

        return $duration;
    }

    private function hasDonationsSearchTerm(Request $request): bool
    {
        return trim((string) $request->input('search', '')) !== '';
    }

    /**
     * "Razorpay" covers both checkout and multi-use QR payments so totals
     * match the Razorpay account; "razorpay_qr" narrows to QR only.
     */
    private function applyProviderFilter(Builder $query, string $provider): void
    {
        if ($provider === DonationOrder::PROVIDER_RAZORPAY) {
            $query->whereIn('payment_provider', [
                DonationOrder::PROVIDER_RAZORPAY,
                DonationOrder::PROVIDER_RAZORPAY_QR,
            ]);

            return;
        }

        $query->where('payment_provider', $provider);
    }

    private function applyCreatedAtDateRange(Builder $query, mixed $fromDate, mixed $toDate): void
    {
        if (! empty($fromDate)) {
            $start = Carbon::parse((string) $fromDate)->startOfDay();
            $this->applyActivityDateLowerBound($query, $start);
        }

        if (! empty($toDate)) {
            $end = Carbon::parse((string) $toDate)->endOfDay();
            $this->applyActivityDateUpperBound($query, $end);
        }
    }

    private function applyCauseTitleFilter(Builder $query, string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        $variants = collect([
            $title,
            trim((string) preg_replace('/\s*\([^)]*\)/', '', $title)),
        ])
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique()
            ->values();

        $query->whereHas('items', function (Builder $itemQuery) use ($variants) {
            $itemQuery->where(function (Builder $inner) use ($variants) {
                foreach ($variants as $index => $variant) {
                    $like = '%'.addcslashes($variant, '%_\\').'%';
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $inner->{$method}(function (Builder $match) use ($variant, $like) {
                        $match
                            ->whereHas('package', fn (Builder $packageQuery) => $packageQuery
                                ->where('title', $variant)
                                ->orWhere('title', 'like', $like))
                            ->orWhere('title', 'like', $like)
                            ->orWhere('cause', 'like', $like)
                            ->orWhereRaw('CAST(meta AS CHAR) LIKE ?', [$like]);
                    });
                }
            });
        });
    }

    private function applyDurationFilter(Builder $query, string $duration): void
    {
        $now = now();

        if ($duration === 'today') {
            $this->applyActivityDateRange(
                $query,
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay()
            );

            return;
        }

        if ($calendar = PeriodRange::forKey($duration)) {
            $this->applyActivityDateRange($query, $calendar['start'], $calendar['end']);

            return;
        }

        if ($duration === 'last_7_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(6)->startOfDay());

            return;
        }

        if ($duration === 'last_30_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(29)->startOfDay());

            return;
        }

        if ($duration === 'last_90_days') {
            $this->applyActivityDateLowerBound($query, $now->copy()->subDays(89)->startOfDay());
        }
    }

    private function applyActivityDateRange(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->whereActivityBetween($start, $end);
    }

    private function applyActivityDateLowerBound(Builder $query, Carbon $start): void
    {
        $query->whereActivityOnOrAfter($start);
    }

    private function applyActivityDateUpperBound(Builder $query, Carbon $end): void
    {
        $query->where(function (Builder $activityQuery) use ($end): void {
            $activityQuery
                ->where('paid_at', '<=', $end)
                ->orWhere(function (Builder $unpaidQuery) use ($end): void {
                    $unpaidQuery
                        ->whereNull('paid_at')
                        ->where('created_at', '<=', $end);
                });
        });
    }
}
