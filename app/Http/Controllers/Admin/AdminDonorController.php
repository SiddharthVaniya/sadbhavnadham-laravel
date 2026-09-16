<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportDonorsRequest;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Services\DonationAttributionService;
use App\Services\DonorCsvImportService;
use App\Support\AdminInertiaResources;
use App\Support\DonorAudienceQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDonorController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = (string) $request->string('sort');
        $dir = $request->string('dir')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $repeatOnly = $request->boolean('repeat');

        if (! in_array($sort, $this->donorSortableColumns(), true)) {
            $sort = 'last_paid_at';
        }

        $query = $this->filteredDonorsQuery($request, $repeatOnly);
        $this->applyDonorSort($query, $sort, $dir);

        $donors = $query
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        $activeFilterCount = collect([
            $request->filled('search'),
            $request->filled('city'),
            $request->filled('state'),
            $request->filled('source'),
            $request->filled('from_date'),
            $request->filled('to_date'),
            $request->filled('min_paid'),
            $request->filled('owner_user_id'),
            $repeatOnly,
        ])->filter()->count();

        return Inertia::render('Admin/Donors/Index', [
            'stats' => $this->donorIndexStats(),
            'activeFilterCount' => $activeFilterCount,
            'can_export' => $request->user()?->can('export donors') ?? false,
            'sourceOptions' => collect(DonationAttributionService::trafficSourceOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'cityOptions' => Donor::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->limit(200)
                ->pluck('city')
                ->values(),
            'stateOptions' => Donor::query()
                ->whereNotNull('state')
                ->where('state', '!=', '')
                ->distinct()
                ->orderBy('state')
                ->limit(200)
                ->pluck('state')
                ->values(),
            'staffOptions' => AdminInertiaResources::staffOptions(),
            'filters' => [
                'sort' => $sort,
                'dir' => $dir,
                'repeat' => $repeatOnly,
                'search' => $request->input('search'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'source' => $request->input('source'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'min_paid' => $request->input('min_paid'),
                'owner_user_id' => $request->input('owner_user_id'),
            ],
            'urls' => [
                'export' => route('admin.donors.export-all'),
                'import' => route('admin.donors.import'),
                'import_template' => route('admin.donors.import.template'),
            ],
            'donors' => AdminInertiaResources::paginated($donors, fn (Donor $donor) => AdminInertiaResources::donorListRow($donor)),
        ]);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('export donors'), 403);

        $sort = (string) $request->string('sort');
        $dir = $request->string('dir')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $repeatOnly = $request->boolean('repeat');

        if (! in_array($sort, $this->donorSortableColumns(), true)) {
            $sort = 'last_paid_at';
        }

        $query = $this->filteredDonorsQuery($request, $repeatOnly);
        $this->applyDonorSort($query, $sort, $dir);
        $donors = $query->get();

        $filename = 'donors_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($donors): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'Name',
                'Email',
                'Phone',
                'City',
                'State',
                'Owner',
                'Source',
                'Paid donations',
                'Paid amount',
                'Open tasks',
                'Attempts',
                'Last paid',
                'PAN',
                'Date of birth',
                'WhatsApp opt out',
            ]);

            foreach ($donors as $donor) {
                $row = AdminInertiaResources::donorListRow($donor);
                fputcsv($file, [
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $donor->city,
                    $donor->state,
                    $row['owner']['name'] ?? '',
                    $row['source'],
                    $row['paid_donations'],
                    $row['paid_amount'],
                    $row['open_tasks_count'],
                    $row['total_attempts'],
                    $row['last_paid_at'] ?? '',
                    $donor->pan_number,
                    $donor->date_of_birth?->format('Y-m-d'),
                    $donor->whatsapp_opt_out ? 'yes' : 'no',
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('export donors'), 403);

        return response()->streamDownload(function (): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, DonorCsvImportService::headers());
            fputcsv($file, [
                'Example Donor',
                'donor@example.com',
                '9898000000',
                '1990-01-15',
                'ABCDE1234F',
                '12 Sample Street',
                '360001',
                'Rajkot',
                'Gujarat',
                'INDIA',
                'IN',
                'no',
            ]);
            fclose($file);
        }, 'donors_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(ImportDonorsRequest $request, DonorCsvImportService $importer): RedirectResponse
    {
        $result = $importer->import($request->file('file'));

        $message = sprintf(
            'Import finished: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            $message .= ' '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        toastr()->success($message);

        return redirect()
            ->route('admin.donors.index')
            ->with('status', $message)
            ->with('import_errors', $result['errors']);
    }

    public function show(Request $request, Donor $donor): Response
    {
        $base = $this->donationQueryForDonor($donor);

        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $paidQuery = (clone $base)->where('status', DonationOrder::STATUS_PAID);

        $donorData = [
            'name' => $donor->name,
            'email' => $donor->email,
            'phone' => $donor->phone,
            'whatsapp_opt_out' => (bool) $donor->whatsapp_opt_out,
            'whatsapp_opted_out_at' => $donor->whatsapp_opted_out_at?->format('d M Y, h:i A'),
            'paid_amount' => (float) (clone $paidQuery)->sum('total_amount'),
            'paid_donations' => (int) ($statusCounts[DonationOrder::STATUS_PAID] ?? 0),
            'total_attempts' => (int) $statusCounts->sum(),
            'pending_attempts' => (int) ($statusCounts[DonationOrder::STATUS_PENDING] ?? 0),
            'failed_attempts' => (int) ($statusCounts[DonationOrder::STATUS_FAILED] ?? 0),
            'first_donation' => (clone $paidQuery)->orderBy('created_at')->value('created_at'),
            'last_donation' => (clone $paidQuery)->orderByDesc('created_at')->value('created_at'),
        ];

        $causeBreakdown = DonationItem::query()
            ->whereIn('donation_order_id', (clone $paidQuery)->select('id'))
            ->get()
            ->groupBy('cause')
            ->map(fn ($items) => [
                'cause' => $items->first()->cause ?? 'Unknown',
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values();

        $donations = (clone $base)
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        $donor->loadMissing('owner:id,name');

        $notes = $donor->notes()
            ->with('author:id,name')
            ->limit(50)
            ->get();

        $tasks = $donor->tasks()
            ->with(['assignee:id,name', 'creator:id,name'])
            ->limit(50)
            ->get();

        $openTasksCount = $donor->tasks()->open()->count();
        $overdueTasksCount = $donor->tasks()->overdue()->count();

        return Inertia::render('Admin/Donors/Show', [
            'donorId' => $donor->id,
            'donor' => $donorData,
            'profile' => [
                'pan_number' => $donor->pan_number,
                'location' => implode(', ', array_filter([$donor->city, $donor->state])),
            ],
            'crm' => [
                'can_manage' => $request->user()?->can('manage donor crm') ?? false,
                'owner_user_id' => $donor->owner_user_id,
                'owner' => $donor->owner ? [
                    'id' => $donor->owner->id,
                    'name' => $donor->owner->name,
                ] : null,
                'staff_options' => ($request->user()?->can('manage donor crm') ?? false)
                    ? AdminInertiaResources::staffOptions()
                    : [],
                'notes' => $notes->map(fn ($note) => AdminInertiaResources::donorNote($note))->values()->all(),
                'tasks' => $tasks->map(fn ($task) => AdminInertiaResources::donorTask($task))->values()->all(),
                'open_tasks_count' => $openTasksCount,
                'overdue_tasks_count' => $overdueTasksCount,
                'urls' => [
                    'owner' => route('admin.donors.owner.update', $donor),
                    'notes_store' => route('admin.donors.notes.store', $donor),
                    'tasks_store' => route('admin.donors.tasks.store', $donor),
                ],
            ],
            'donations' => AdminInertiaResources::paginated(
                $donations,
                fn (DonationOrder $order) => [
                    'id' => $order->id,
                    'uuid' => $order->order_uuid,
                    'cause' => $order->items->first()?->cause ?? '—',
                    'total_amount' => (float) $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at?->format('d M Y, h:i A'),
                ]
            ),
            'causeBreakdown' => $causeBreakdown,
            'back_url' => $this->resolveDonorsBackUrl($request),
            'opt_out_url' => route('admin.donors.whatsapp-opt-out', $donor),
            'has_donation_history' => (clone $base)->exists(),
        ]);
    }

    public function updateWhatsappOptOut(Request $request, Donor $donor): RedirectResponse
    {
        $optOut = $request->boolean('whatsapp_opt_out');

        $donor->forceFill([
            'whatsapp_opt_out' => $optOut,
            'whatsapp_opted_out_at' => $optOut ? now() : null,
        ])->save();

        return back()->with(
            'status',
            $optOut
                ? 'Donor opted out of WhatsApp campaigns.'
                : 'Donor can receive WhatsApp campaigns again.',
        );
    }

    public function export(Donor $donor): StreamedResponse
    {
        $orders = $this->donationQueryForDonor($donor)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        abort_if($orders->isEmpty(), 404);

        $filename = 'donations_'.str_replace('@', '_', $donor->email).'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Order ID',
                'Cause',
                'Amount',
                'Status',
                'Date',
                'Payment Provider',
            ]);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_uuid,
                    $order->items->first()?->cause ?? '',
                    $order->total_amount,
                    $order->status,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->payment_provider,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function filteredDonorsQuery(Request $request, bool $repeatOnly = false): Builder
    {
        $paidScope = fn (Builder $query): Builder => $query->where('status', DonationOrder::STATUS_PAID);

        $query = Donor::query()
            ->with(['latestPaidDonationOrder', 'owner:id,name'])
            ->withCount([
                'donationOrders as total_attempts',
                'paidDonationOrders as paid_donations',
                'openTasks as open_tasks_count',
            ])
            ->withSum('paidDonationOrders as paid_amount', 'total_amount')
            ->withMax(['paidDonationOrders as last_paid_donation_at' => $paidScope], 'created_at');

        $this->applyDonorFilters($query, $request);

        if ($request->filled('owner_user_id')) {
            $query->where('owner_user_id', (int) $request->input('owner_user_id'));
        }

        if ($repeatOnly) {
            $query->whereIn('id', $this->repeatDonorIdsSubquery());
        }

        return $query;
    }

    private function applyDonorFilters(Builder $query, Request $request): void
    {
        DonorAudienceQuery::apply($query, [
            'search' => $request->input('search'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'source' => $request->input('source'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'min_paid' => $request->input('min_paid'),
        ]);
    }

    private function donorIndexStats(): array
    {
        $base = Donor::query();

        return [
            'total_donors' => (clone $base)->count(),
            'total_amount' => (float) DonationOrder::query()
                ->where('status', DonationOrder::STATUS_PAID)
                ->whereNotNull('donor_id')
                ->sum('total_amount'),
            'repeat_donors' => (clone $base)
                ->whereIn('id', $this->repeatDonorIdsSubquery())
                ->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function donorSortableColumns(): array
    {
        return [
            'name',
            'email',
            'phone',
            'location',
            'source',
            'paid_donations',
            'paid_amount',
            'total_attempts',
            'last_paid_at',
        ];
    }

    private function applyDonorSort(Builder $query, string $sort, string $dir): void
    {
        if ($sort === 'location') {
            $query->orderBy('city', $dir)->orderBy('state', $dir)->orderBy('name');

            return;
        }

        if ($sort === 'source') {
            $query->orderBy(
                DonationOrder::query()
                    ->select('utm_source')
                    ->whereColumn('donation_orders.donor_id', 'donors.id')
                    ->where('status', DonationOrder::STATUS_PAID)
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->limit(1),
                $dir
            )->orderBy('name');

            return;
        }

        $column = match ($sort) {
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'paid_donations' => 'paid_donations',
            'paid_amount' => 'paid_amount',
            'total_attempts' => 'total_attempts',
            default => 'last_paid_donation_at',
        };

        $query->orderBy($column, $dir);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }
    }

    private function resolveDonorsBackUrl(Request $request): string
    {
        $candidate = $request->query('return');

        if (is_string($candidate) && $this->isSafeDonorsIndexUrl($candidate)) {
            session(['admin.donors.index_url' => $candidate]);

            return $candidate;
        }

        $fromSession = session('admin.donors.index_url');

        if (is_string($fromSession) && $this->isSafeDonorsIndexUrl($fromSession)) {
            return $fromSession;
        }

        return route('admin.donors.index');
    }

    private function isSafeDonorsIndexUrl(string $url): bool
    {
        if (str_contains($url, '://') || str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }

        if (! str_starts_with($url, '/admin/donors')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path === '/admin/donors' || $path === '/admin/donors/';
    }

    private function repeatDonorIdsSubquery(): \Closure
    {
        return function ($query): void {
            $query->select('donor_id')
                ->from('donation_orders')
                ->where('status', DonationOrder::STATUS_PAID)
                ->whereNotNull('donor_id')
                ->groupBy('donor_id')
                ->havingRaw('count(*) > 1');
        };
    }

    private function donationQueryForDonor(Donor $donor): Builder
    {
        return $donor->linkedDonationOrdersQuery();
    }
}
