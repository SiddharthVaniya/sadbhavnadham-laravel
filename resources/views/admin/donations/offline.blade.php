@extends('admin.layouts.minimal')

@section('title', 'Offline Donations History')

@section('content')
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column my-0">Offline Donations History</h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a></li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.donations.index') }}" class="text-muted text-hover-primary">Donations</a></li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Offline</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.donations.create') }}" class="btn btn-sm btn-success">
                    <i class="ki-outline ki-plus fs-4 me-1"></i> Record Offline Donation
                </a>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="card card-flush">
                <div class="card-body p-6">

                    {{-- Filters --}}
                    <form method="GET" action="{{ route('admin.donations.offline') }}" class="row g-3 mb-6 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Search Donor</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email or phone…" value="{{ request('search') }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Cause</label>
                            <select name="cause_id" class="form-select">
                                <option value="">All Causes</option>
                                @foreach ($causes as $cause)
                                    <option value="{{ $cause->id }}" @selected(request('cause_id') == $cause->id)>
                                        {{ $cause->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                            <a href="{{ route('admin.donations.offline') }}" class="btn btn-light w-100">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-row-bordered gy-5 align-middle mb-0">
                            <thead>
                                <tr class="fw-semibold fs-6 text-muted">
                                    <th>Receipt #</th>
                                    <th>Donor</th>
                                    <th>Cause</th>
                                    <th>Amount</th>
                                    <th>Receipt Email</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($donations as $order)
                                    <tr>
                                        <td class="fw-bold">
                                            {{ $order->hasReceipt() ? $order->receiptNumberFormatted() : '—' }}
                                        </td>
                                        <td>
                                            <div class="fw-bold">{{ $order->donor_name }}</div>
                                            <small class="text-muted">{{ $order->donor_email }}</small><br>
                                            <small class="text-muted">{{ $order->donor_phone }}</small>
                                        </td>
                                        <td>
                                            {{ $order->items->first()?->causeModel?->title ?? '—' }}
                                        </td>
                                        <td class="fw-bold">₹ {{ number_format($order->total_amount, 2) }}</td>
                                        <td>
                                            @if ($order->receipt_sent_at)
                                                <span class="badge badge-light-success">Sent</span>
                                            @elseif ($order->receipt_failed_at)
                                                <span class="badge badge-light-danger">Failed</span>
                                            @else
                                                <span class="badge badge-light-warning">Not Sent</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $order->created_at->format('d M Y') }}<br>
                                            <span class="text-muted fs-7">{{ $order->created_at->format('h:i A') }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex gap-1 justify-content-end">
                                                @can('manage receipts')
                                                    <a href="{{ route('admin.donations.receipt.preview', $order) }}"
                                                        class="btn btn-icon btn-light btn-sm" title="Preview Receipt">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.donations.receipt.print', $order) }}"
                                                        class="btn btn-icon btn-light btn-sm" title="Download Receipt">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <form method="POST"
                                                        action="{{ route('admin.donations.receipt.resend', $order) }}">
                                                        @csrf
                                                        <button class="btn btn-icon btn-light-warning btn-sm" title="Resend Receipt">
                                                            <i class="bi bi-envelope"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                                <a href="{{ route('admin.donations.show', $order) }}"
                                                    class="btn btn-sm btn-light-primary">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">No offline donations found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($donations->hasPages())
                        <div class="mt-4 pt-3 border-top">
                            {{ $donations->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
