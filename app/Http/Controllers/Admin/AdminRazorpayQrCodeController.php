<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRazorpayQrCodeRequest;
use App\Http\Requests\Admin\UpdateRazorpayQrCodeRequest;
use App\Models\RazorpayQrCode;
use App\Services\RazorpayQrCodeService;
use App\Support\AdminInertiaResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AdminRazorpayQrCodeController extends Controller
{
    public function __construct(private RazorpayQrCodeService $qrCodeService) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'active');

        $query = RazorpayQrCode::query()->with(['cause:id,title,slug', 'package:id,title,amount']);

        if ($status === 'active') {
            $query->where('status', RazorpayQrCode::STATUS_ACTIVE);
        } elseif ($status === 'closed') {
            $query->where('status', RazorpayQrCode::STATUS_CLOSED);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('razorpay_qr_code_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $statusCounts = [
            'active' => RazorpayQrCode::query()->where('status', RazorpayQrCode::STATUS_ACTIVE)->count(),
            'closed' => RazorpayQrCode::query()->where('status', RazorpayQrCode::STATUS_CLOSED)->count(),
            'all' => RazorpayQrCode::query()->count(),
        ];

        $codes = $query
            ->latest('id')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/QrCodes/Index', [
            'qrCodes' => AdminInertiaResources::paginated(
                $codes,
                fn (RazorpayQrCode $qr) => AdminInertiaResources::qrCodeListRow($qr)
            ),
            'stats' => [
                'active' => $statusCounts['active'],
                'closed' => $statusCounts['closed'],
                'all' => $statusCounts['all'],
            ],
            'statusTabs' => [
                ['key' => 'active', 'label' => 'Active', 'count' => $statusCounts['active']],
                ['key' => 'closed', 'label' => 'Closed', 'count' => $statusCounts['closed']],
                ['key' => 'all', 'label' => 'All', 'count' => $statusCounts['all']],
            ],
            'filters' => [
                'status' => $status,
                'search' => $request->input('search', ''),
            ],
            'can_create' => $request->user()?->can('create qr codes')
                || $request->user()?->can('manage qr codes'),
            'can_sync' => $request->user()?->can('sync qr codes')
                || $request->user()?->can('manage qr codes'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/QrCodes/Create', [
            'causes' => AdminInertiaResources::causeOptionsForCampaigns(),
        ]);
    }

    public function store(StoreRazorpayQrCodeRequest $request): RedirectResponse
    {
        try {
            $qr = $this->qrCodeService->create($request->createPayload(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['razorpay' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['razorpay' => 'Could not create QR code.'])->withInput();
        }

        return redirect()
            ->route('admin.qr-codes.show', $qr)
            ->with('status', 'QR code created in Razorpay.');
    }

    public function show(Request $request, RazorpayQrCode $qrCode): Response
    {
        return Inertia::render('Admin/QrCodes/Show', [
            'qrCode' => AdminInertiaResources::qrCodeDetail($qrCode),
            'causes' => AdminInertiaResources::causeOptionsForCampaigns(),
            'can_update' => $request->user()?->can('create qr codes')
                || $request->user()?->can('manage qr codes'),
        ]);
    }

    public function update(UpdateRazorpayQrCodeRequest $request, RazorpayQrCode $qrCode): RedirectResponse
    {
        $this->qrCodeService->updateLocalMapping($qrCode, $request->mappingPayload());

        return back()->with('status', 'QR cause mapping saved.');
    }

    public function close(Request $request, RazorpayQrCode $qrCode): RedirectResponse
    {
        try {
            $this->qrCodeService->close($qrCode);
        } catch (RuntimeException $e) {
            return back()->withErrors(['razorpay' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['razorpay' => 'Could not close QR code.']);
        }

        return back()->with('status', 'QR code closed.');
    }

    public function sync(RazorpayQrCode $qrCode): RedirectResponse
    {
        try {
            $this->qrCodeService->syncOne($qrCode);
        } catch (RuntimeException $e) {
            return back()->withErrors(['razorpay' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['razorpay' => 'Could not sync QR code.']);
        }

        return back()->with('status', 'QR code synced from Razorpay.');
    }

    public function syncAll(): RedirectResponse
    {
        try {
            $result = $this->qrCodeService->syncAll();
        } catch (RuntimeException $e) {
            return back()->withErrors(['razorpay' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['razorpay' => 'Could not sync QR codes.']);
        }

        return back()->with(
            'status',
            "Synced {$result['synced']} QR code(s) ({$result['created']} new, {$result['updated']} updated)."
        );
    }
}
