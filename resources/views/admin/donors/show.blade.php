@extends('admin.layouts.minimal')

@section('title', 'Donor Details')

@section('content')
    <div class="d-flex flex-column flex-column-fluid">
        <x-admin.page-header :title="$donor['name']" :subtitle="$donor['email'] . ' · ' . $donor['phone']">
            <x-slot:actions>
                <a href="{{ route('admin.donors.export', $donorModel) }}" class="btn btn-sm btn-light">Export CSV</a>
                <a href="{{ route('admin.donors.index') }}" class="btn btn-sm btn-light">Back</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="app-content flex-column-fluid">
            <div class="app-container container-fluid">
                <div class="row g-6">

                    {{-- LEFT: DONOR PROFILE --}}
                    <div class="col-xl-4">
                        <div class="card card-flush h-100">
                            <div class="card-body text-center pt-10 pb-6">

                                <div class="symbol symbol-100px symbol-circle mb-5">
                                    <span class="symbol-label bg-light-primary text-primary fs-1 fw-bold">
                                        {{ strtoupper(substr($donor['name'], 0, 1)) }}
                                    </span>
                                </div>

                                <div class="fw-bold fs-3">{{ $donor['name'] }}</div>
                                <div class="text-muted mb-1">{{ $donor['email'] }}</div>
                                <div class="text-muted">{{ $donor['phone'] }}</div>

                                {{-- Stats Strip --}}
                                <div class="d-flex flex-wrap flex-center gap-3 mt-6 mb-4">
                                    <x-admin.stat-card
                                        tone="success"
                                        :value="'₹ '.number_format($donor['paid_amount'], 0)"
                                        label="Total Donated (Paid)"
                                    />
                                    <x-admin.stat-card
                                        :value="(string) $donor['paid_donations']"
                                        label="Paid Donations"
                                    />
                                </div>
                                <div class="d-flex flex-wrap justify-content-center gap-2 mb-6">
                                    <span class="badge badge-light fs-8">{{ $donor['total_attempts'] }} attempts</span>
                                    @if ($donor['pending_attempts'] > 0)
                                        <span class="badge badge-light-warning fs-8">{{ $donor['pending_attempts'] }} pending</span>
                                    @endif
                                    @if ($donor['failed_attempts'] > 0)
                                        <span class="badge badge-light-danger fs-8">{{ $donor['failed_attempts'] }} failed</span>
                                    @endif
                                </div>

                                <div class="separator separator-dashed mb-6"></div>

                                {{-- Quick Details --}}
                                <div class="text-start">
                                    @if ($donorModel->pan_number)
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="text-muted fw-semibold fs-7 w-100px">PAN</span>
                                            <span class="fw-bold fs-6 text-gray-800">{{ $donorModel->pan_number }}</span>
                                        </div>
                                    @endif

                                    @if ($donorModel->city || $donorModel->state)
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="text-muted fw-semibold fs-7 w-100px">Location</span>
                                            <span class="fw-semibold fs-6 text-gray-700">
                                                {{ implode(', ', array_filter([$donorModel->city, $donorModel->state])) }}
                                            </span>
                                        </div>
                                    @endif

                                    @if ($donorModel->pincode)
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="text-muted fw-semibold fs-7 w-100px">Pincode</span>
                                            <span class="fw-semibold fs-6 text-gray-700">{{ $donorModel->pincode }}</span>
                                        </div>
                                    @endif

                                    @if ($donorModel->country)
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="text-muted fw-semibold fs-7 w-100px">Country</span>
                                            <span class="fw-semibold fs-6 text-gray-700">{{ $donorModel->country }}</span>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: TABS --}}
                    <div class="col-xl-8">
                        <div class="card card-flush">

                            <div class="card-header pt-5 pb-0">
                                <ul class="nav nav-tabs nav-line-tabs fw-bold fs-6 border-0">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#overview">Overview</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#donations">
                                            All Attempts
                                            <span class="badge badge-light-primary ms-1">{{ $donor['total_attempts'] }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#causes">Causes</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#logs">Logs</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="card-body pt-6">
                                <div class="tab-content">

                                    {{-- OVERVIEW --}}
                                    <div class="tab-pane fade show active" id="overview">
                                        <table class="table table-row-dashed fs-6 gy-3">
                                            <tr>
                                                <td class="fw-bold text-muted w-150px">First Paid Donation</td>
                                                <td>{{ $donor['first_donation']?->format('d M Y') ?? '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Last Paid Donation</td>
                                                <td>{{ $donor['last_donation']?->format('d M Y') ?? '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Payment Attempts</td>
                                                <td>{{ $donor['total_attempts'] }} total ({{ $donor['paid_donations'] }} paid)</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Email</td>
                                                <td>{{ $donor['email'] }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-muted">Phone</td>
                                                <td>{{ $donor['phone'] }}</td>
                                            </tr>
                                            @if ($donorModel->pan_number)
                                                <tr>
                                                    <td class="fw-bold text-muted">PAN</td>
                                                    <td>{{ $donorModel->pan_number }}</td>
                                                </tr>
                                            @endif
                                            @if ($donorModel->date_of_birth)
                                                <tr>
                                                    <td class="fw-bold text-muted">Date of Birth</td>
                                                    <td>{{ $donorModel->date_of_birth->format('d M Y') }}</td>
                                                </tr>
                                            @endif
                                            @if ($donorModel->address)
                                                <tr>
                                                    <td class="fw-bold text-muted">Address</td>
                                                    <td>{{ $donorModel->address }}</td>
                                                </tr>
                                            @endif
                                            @if ($donorModel->city || $donorModel->state)
                                                <tr>
                                                    <td class="fw-bold text-muted">City / State</td>
                                                    <td>{{ implode(', ', array_filter([$donorModel->city, $donorModel->state])) }}</td>
                                                </tr>
                                            @endif
                                            @if ($donorModel->pincode)
                                                <tr>
                                                    <td class="fw-bold text-muted">Pincode</td>
                                                    <td>{{ $donorModel->pincode }}</td>
                                                </tr>
                                            @endif
                                            @if ($donorModel->country)
                                                <tr>
                                                    <td class="fw-bold text-muted">Country</td>
                                                    <td>{{ $donorModel->country }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </div>

                                    {{-- DONATIONS --}}
                                    <div class="tab-pane fade" id="donations">
                                        <div class="table-responsive">
                                            <table class="table table-row-dashed align-middle fs-6 gy-4">
                                                <thead>
                                                    <tr class="text-muted fw-bold">
                                                        <th>Receipt No</th>
                                                        <th>Cause</th>
                                                        <th>Status</th>
                                                        <th>Amount</th>
                                                        <th>Date</th>
                                                        <th class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="fw-semibold">
                                                    @forelse ($donations as $order)
                                                        @php
                                                            $statusColor = match ($order->status) {
                                                                'paid'    => 'success',
                                                                'pending' => 'warning',
                                                                'failed'  => 'danger',
                                                                default   => 'secondary',
                                                            };
                                                        @endphp
                                                        <tr>
                                                            <td class="fw-bold text-gray-700">
                                                                {{ $order->receiptNumberFormatted() }}
                                                            </td>
                                                            <td>{{ $order->items->first()?->cause ?? '-' }}</td>
                                                            <td>
                                                                <span class="badge badge-light-{{ $statusColor }}">
                                                                    {{ ucfirst($order->status) }}
                                                                </span>
                                                            </td>
                                                            <td class="fw-bold {{ $order->status === 'paid' ? 'text-success' : 'text-gray-700' }}">
                                                                ₹ {{ number_format($order->total_amount, 0) }}
                                                            </td>
                                                            <td class="text-muted">
                                                                {{ $order->created_at->format('d M Y, h:i A') }}
                                                            </td>
                                                            <td class="text-end">
                                                                <a href="{{ route('admin.donations.show', $order) }}"
                                                                    class="btn btn-sm btn-light-primary">
                                                                    View
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-5">
                                                                No donations found.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {{-- CAUSES BREAKDOWN --}}
                                    <div class="tab-pane fade" id="causes">
                                        @if ($causeBreakdown->isEmpty())
                                            <div class="text-center text-muted py-8">No cause data available.</div>
                                        @else
                                            <table class="table table-row-dashed align-middle fs-6 gy-4">
                                                <thead>
                                                    <tr class="text-muted fw-bold">
                                                        <th>Cause</th>
                                                        <th class="text-center">Donations</th>
                                                        <th class="text-end">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($causeBreakdown as $row)
                                                        <tr>
                                                            <td>
                                                                <span class="fw-bold text-gray-800">
                                                                    {{ ucwords(str_replace(['-', '_'], ' ', $row['cause'])) }}
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge badge-light-primary">
                                                                    {{ $row['count'] }}
                                                                </span>
                                                            </td>
                                                            <td class="text-end fw-bold text-success">
                                                                ₹ {{ number_format($row['amount'], 0) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="fw-bold text-gray-700 border-top border-gray-200">
                                                        <td>Total</td>
                                                        <td class="text-center">{{ $causeBreakdown->sum('count') }}</td>
                                                        <td class="text-end text-success">
                                                            ₹ {{ number_format($causeBreakdown->sum('amount'), 0) }}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        @endif
                                    </div>

                                    {{-- LOGS --}}
                                    <div class="tab-pane fade" id="logs">
                                        @forelse ($donations as $order)
                                            <div class="mb-8">
                                                <div class="fw-bold fs-6 text-gray-700 mb-3">
                                                    {{ $order->receiptNumberFormatted() }}
                                                    <span class="text-muted fw-normal fs-7 ms-2">
                                                        {{ $order->created_at->format('d M Y') }}
                                                    </span>
                                                </div>
                                                <div class="timeline">
                                                    @if ($order->payment_link_sent_at)
                                                        <div class="timeline-item">
                                                            <div class="timeline-label fw-semibold text-muted fs-7">
                                                                {{ $order->payment_link_sent_at->format('d M, h:i A') }}
                                                            </div>
                                                            <div class="timeline-badge">
                                                                <i class="ki-duotone ki-abstract-8 text-primary fs-3">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </div>
                                                            <div class="timeline-content fw-semibold ps-3">
                                                                Payment link sent via WhatsApp
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($order->whatsapp_sent_at)
                                                        <div class="timeline-item">
                                                            <div class="timeline-label fw-semibold text-muted fs-7">
                                                                {{ $order->whatsapp_sent_at->format('d M, h:i A') }}
                                                            </div>
                                                            <div class="timeline-badge">
                                                                <i class="ki-duotone ki-abstract-8 text-success fs-3">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </div>
                                                            <div class="timeline-content fw-semibold ps-3">
                                                                Thank You WhatsApp sent
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($order->receipt_sent_at)
                                                        <div class="timeline-item">
                                                            <div class="timeline-label fw-semibold text-muted fs-7">
                                                                {{ $order->receipt_sent_at->format('d M, h:i A') }}
                                                            </div>
                                                            <div class="timeline-badge">
                                                                <i class="ki-duotone ki-abstract-8 text-info fs-3">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </div>
                                                            <div class="timeline-content fw-semibold ps-3">
                                                                Receipt sent to donor
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($order->sheet_logged_at)
                                                        <div class="timeline-item">
                                                            <div class="timeline-label fw-semibold text-muted fs-7">
                                                                {{ $order->sheet_logged_at->format('d M, h:i A') }}
                                                            </div>
                                                            <div class="timeline-badge">
                                                                <i class="ki-duotone ki-abstract-8 text-warning fs-3">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </div>
                                                            <div class="timeline-content fw-semibold ps-3">
                                                                Logged in Google Sheet
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if (!$order->payment_link_sent_at && !$order->whatsapp_sent_at && !$order->receipt_sent_at && !$order->sheet_logged_at)
                                                        <div class="text-muted fs-7">No logs available for this order.</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-muted text-center py-8">No donation logs available.</div>
                                        @endforelse
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
