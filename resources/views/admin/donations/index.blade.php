@extends('admin.layouts.minimal')

@section('title', 'Donations')

@section('customcss')
    <style>
        .payments-shell {
            background: radial-gradient(circle at 100% 0, rgba(124, 108, 255, 0.07), transparent 45%),
                radial-gradient(circle at 0 100%, rgba(0, 170, 160, 0.07), transparent 55%);
            border-radius: 1rem;
            padding: 1rem;
        }

        .payments-topbar {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .payments-title {
            color: #1f2a44;
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0;
        }

        .payments-subtitle {
            color: #7e8299;
            font-size: 0.9rem;
            margin-top: 0.15rem;
        }

        .payments-duration {
            background: #fff;
            border: 1px solid #e9ebf2;
            border-radius: 0.8rem;
            min-width: 220px;
            padding: 0.55rem 0.75rem;
        }

        .payments-overview-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: 2fr 1fr;
            margin-bottom: 1rem;
        }

        .payments-overview-main {
            background: #fff;
            border: 1px solid #e9ebf2;
            border-radius: 1rem;
            box-shadow: 0 10px 24px rgba(20, 34, 66, 0.05);
            padding: 1.35rem;
        }

        .payments-overview-title {
            color: #252f4a;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.65rem;
        }

        .payments-overview-amount {
            color: #1f2a44;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.55rem;
        }

        .payments-overview-meta {
            color: #7e8299;
            font-size: 0.86rem;
        }

        .payments-kpi-line {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .payments-kpi-pill {
            background: #f7f8fb;
            border: 1px solid #eceff5;
            border-radius: 0.8rem;
            color: #44506b;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.5rem 0.75rem;
        }

        .split-card {
            background: #fff;
            border: 1px solid #e9ebf2;
            border-radius: 1rem;
            box-shadow: 0 10px 24px rgba(20, 34, 66, 0.05);
            padding: 1.2rem;
        }

        .split-wrap {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .split-donut {
            aspect-ratio: 1 / 1;
            border-radius: 999px;
            display: grid;
            flex-shrink: 0;
            place-items: center;
            width: 120px;
        }

        .split-donut::before {
            background: #fff;
            border-radius: 999px;
            content: '';
            height: 64%;
            width: 64%;
        }

        .split-list {
            display: grid;
            gap: 0.55rem;
            width: 100%;
        }

        .split-row {
            align-items: center;
            display: flex;
            font-size: 0.82rem;
            justify-content: space-between;
        }

        .split-dot {
            border-radius: 999px;
            display: inline-block;
            height: 9px;
            margin-right: 0.45rem;
            width: 9px;
        }

        .payments-status-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 1rem;
        }

        .payments-status-card {
            background: #fff;
            border: 1px solid #e9ebf2;
            border-radius: 0.95rem;
            padding: 1rem;
        }

        .payments-status-label {
            color: #7e8299;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .payments-status-value {
            color: #1f2a44;
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: 0.4rem;
        }

        .transactions-card {
            background: #fff;
            border: 1px solid #e9ebf2;
            border-radius: 1rem;
            box-shadow: 0 10px 24px rgba(20, 34, 66, 0.05);
        }

        .transaction-tabs {
            border-bottom: 1px solid #eef1f6;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            padding: 1rem 1rem 0.8rem;
        }

        .transaction-tab {
            border-radius: 0.65rem;
            color: #5e6278;
            font-size: 0.86rem;
            font-weight: 700;
            padding: 0.4rem 0.65rem;
            text-decoration: none;
        }

        .transaction-tab.active {
            background: #eef2ff;
            color: #473bf0;
        }

        .transaction-filters {
            border-bottom: 1px solid #eef1f6;
            padding: 1rem;
        }

        .transaction-filters .form-control,
        .transaction-filters .form-select {
            border-radius: 0.65rem;
        }

        .transaction-table thead th {
            color: #7e8299;
            font-size: 0.74rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .transaction-table tbody tr:hover {
            background: #fbfcff;
        }

        @media (max-width: 1199px) {
            .payments-overview-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 991px) {
            .payments-status-grid {
                grid-template-columns: 1fr;
            }
        }

        .transactions-card .pagination {
            margin-bottom: 0;
        }

        .transactions-card nav[aria-label="Pagination Navigation"] {
            width: 100%;
        }
    </style>
@endsection

@section('content')
    @php
        $splitColors = ['#7c6cff', '#00a5a5', '#f59f44', '#d946ef', '#94a3b8'];
        $firstSplit = $paymentMethodBreakdown->get(0);
        $secondSplit = $paymentMethodBreakdown->get(1);
        $firstPercent = (float) ($firstSplit?->percentage ?? 0);
        $secondPercent = (float) ($secondSplit?->percentage ?? 0);

        $donutStyle = sprintf(
            'conic-gradient(%s 0 %s%%, %s %s%% %s%%, %s %s%% 100%%)',
            $splitColors[0],
            $firstPercent,
            $splitColors[1],
            $firstPercent,
            $firstPercent + $secondPercent,
            '#e7eaf1',
            $firstPercent + $secondPercent,
        );

        $allTabQuery = request()->except('status', 'page');
        $paidTabQuery = array_merge($allTabQuery, ['status' => 'paid']);
        $pendingTabQuery = array_merge($allTabQuery, ['status' => 'pending']);
        $failedTabQuery = array_merge($allTabQuery, ['status' => 'failed']);
    @endphp

    <div class="d-flex flex-column flex-column-fluid">
        <x-admin.page-header
            title="Donations"
            :subtitle="$overviewDateLabel . ' · ' . number_format($totalCount) . ' transactions'"
        />
        {{-- <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column my-0">Donations</h1>
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted">
                            <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                        <li class="breadcrumb-item text-muted">Donations</li>
                    </ul>
                </div>
            </div>
        </div> --}}

        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-fluid">
                <div class="payments-shell">
                    <div class="payments-topbar">
                        <form method="GET" class="d-flex gap-2 align-items-center ms-auto">
                            <label for="duration" class="fw-semibold text-gray-700 fs-7 mb-0">Duration</label>
                            <select id="duration" name="duration" class="payments-duration form-select" onchange="this.form.submit()">
                                @foreach ($durationOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($duration === $value)>{{ $label }}</option>
                                @endforeach
                            </select>

                            @foreach (request()->except('duration', 'page') as $key => $value)
                                @if (is_array($value))
                                    @foreach ($value as $item)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                    @endforeach
                                @elseif ($value !== null && $value !== '')
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                        </form>
                    </div>

                    <div class="payments-overview-grid">
                        <div class="payments-overview-main">
                            <div class="payments-overview-title">Paid Collection</div>
                            <div class="payments-overview-amount">Rs {{ number_format($paidAmount, 2) }}</div>
                            <div class="payments-overview-meta">Successful donations in current filter</div>

                            <div class="payments-kpi-line">
                                <span class="payments-kpi-pill">Attempts Volume: Rs {{ number_format($attemptVolume, 2) }}</span>
                                <span class="payments-kpi-pill">{{ number_format($totalCount) }} transaction(s)</span>
                                <span class="payments-kpi-pill">{{ number_format($failedCount) }} failed</span>
                            </div>
                        </div>

                        <div class="split-card">
                            <div class="payments-overview-title mb-4">Split by payment method</div>
                            <div class="split-wrap">
                                <div class="split-donut" style="background: {{ $donutStyle }}"></div>
                                <div class="split-list">
                                    @forelse ($paymentMethodBreakdown->take(4) as $index => $method)
                                        <div class="split-row">
                                            <div>
                                                <span class="split-dot" style="background: {{ $splitColors[$index] ?? '#94a3b8' }}"></span>{{ $method->name }}
                                            </div>
                                            <div class="fw-bold">{{ rtrim(rtrim(number_format($method->percentage, 1), '0'), '.') }}%</div>
                                        </div>
                                    @empty
                                        <div class="text-muted fs-7">No payment-method data for selected filters.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    

                    <div class="transactions-card">
                        <div class="transaction-tabs">
                            <a href="{{ route('admin.donations.index', $allTabQuery) }}" class="transaction-tab {{ request('status') ? '' : 'active' }}">All</a>
                            <a href="{{ route('admin.donations.index', $paidTabQuery) }}" class="transaction-tab {{ request('status') === 'paid' ? 'active' : '' }}">Captured</a>
                            <a href="{{ route('admin.donations.index', $pendingTabQuery) }}" class="transaction-tab {{ request('status') === 'pending' ? 'active' : '' }}">Pending</a>
                            <a href="{{ route('admin.donations.index', $failedTabQuery) }}" class="transaction-tab {{ request('status') === 'failed' ? 'active' : '' }}">Failed</a>
                        </div>

                        <div class="transaction-filters">
                            <form method="GET" class="row g-3 align-items-end">
                                <input type="hidden" name="duration" value="{{ $duration }}">

                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All</option>
                                        <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                                        <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Payment Method</label>
                                    <select name="provider" class="form-select">
                                        <option value="">All</option>
                                        <option value="razorpay" @selected(request('provider') === 'razorpay')>Razorpay</option>
                                        <option value="offline" @selected(request('provider') === 'offline')>Offline</option>
                                        <option value="danamojo" @selected(request('provider') === 'danamojo')>Danamojo</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Payment ID, donor, email, phone...">
                                </div>

                                <div class="col-md-1 d-grid">
                                    <button class="btn btn-primary">Apply</button>
                                </div>

                                <div class="col-12 d-flex flex-wrap justify-content-between gap-2">
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('admin.donations.index') }}" class="btn btn-sm btn-light">Reset</a>
                                        <span class="badge badge-light-primary fs-8">Active Filters: {{ $activeFilterCount }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('admin.donations.create') }}" class="btn btn-sm btn-success">Record Offline Donation</a>
                                        <a href="#" class="btn btn-sm btn-light-success">Export Excel</a>
                                        <a href="#" class="btn btn-sm btn-light-danger">Export PDF</a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive p-4">
                            <table class="table table-row-bordered gy-5 align-middle mb-0 transaction-table">
                                <thead>
                                    <tr>
                                        <th>Donation ID</th>
                                        <th>Donor Details</th>
                                        <th>Cause</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Receipt</th>
                                        <th>Created On</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($donations as $order)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-gray-900">#{{ $order->provider_payment_id ?: $order->provider_order_id ?: $order->id }}</div>
                                                <div class="text-muted fs-8">{{ ucfirst($order->payment_provider ?? 'Unknown') }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-bold">{{ $order->donor_name }}</div>
                                                <div class="text-muted fs-8">{{ $order->donor_email }}</div>
                                                <div class="text-muted fs-8">{{ $order->donor_phone }}</div>
                                            </td>
                                            <td>{{ $order->items->first()?->causeModel?->title ?? '—' }}</td>
                                            <td class="text-end fw-bold">Rs {{ number_format($order->total_amount, 2) }}</td>
                                            <td>
                                                @if ($order->status === 'paid')
                                                    <span class="badge badge-light-success">Captured</span>
                                                @elseif ($order->status === 'failed')
                                                    <span class="badge badge-light-danger">Failed</span>
                                                @else
                                                    <span class="badge badge-light-warning">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($order->receipt_sent_at)
                                                    <span class="badge badge-light-success">Sent</span>
                                                @elseif ($order->receipt_failed_at)
                                                    <span class="badge badge-light-danger">Failed</span>
                                                @elseif ($order->isPaid())
                                                    <span class="badge badge-light-warning">Not Sent</span>
                                                @else
                                                    <span class="badge badge-light-secondary">—</span>
                                                @endif
                                            </td>
                                            <td data-order="{{ $order->created_at?->timestamp ?? 0 }}">
                                                {{ $order->created_at->format('d M Y') }}
                                                <div class="text-muted fs-8">{{ $order->created_at->format('h:i A') }}</div>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                                    <a href="{{ route('admin.donations.show', $order) }}" class="btn btn-sm btn-light-primary">Details</a>
                                                    @can('manage receipts')
                                                        @if ($order->isPaid() && $order->hasReceipt())
                                                            <form method="POST" action="{{ route('admin.donations.receipt.resend', $order) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-light-success">Resend</button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-8">No donations found for the current filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($donations->hasPages())
                            <div class="px-4 pb-4 border-top">
                                {{ $donations->onEachSide(1)->links() }}
                            </div>
                        @elseif ($donations->total() > 0)
                            <div class="px-4 pb-4 border-top text-muted fs-7">
                                Showing {{ $donations->count() }} of {{ number_format($donations->total()) }} donations
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
