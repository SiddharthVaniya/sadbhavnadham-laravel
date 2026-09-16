@extends('admin.layouts.minimal')

@section('title', 'Donation Details')

@section('customcss')
    <style>
        .donation-detail-shell {
            background: radial-gradient(circle at 100% 0, rgba(15, 127, 135, 0.08), transparent 45%),
                radial-gradient(circle at 0 100%, rgba(245, 159, 68, 0.08), transparent 55%);
            border-radius: 1rem;
            padding: 1rem;
        }

        .donation-hero {
            background: linear-gradient(135deg, #1f2a44 0%, #0f7f87 100%);
            border-radius: 1.25rem;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .donation-hero::after {
            content: '';
            position: absolute;
            inset: auto -60px -60px auto;
            width: 180px;
            height: 180px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .donation-hero .hero-pill {
            background: rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            padding: 0.45rem 0.75rem;
        }

        .donation-kpi {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            padding: 1rem;
        }

        .donation-kpi-label {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .donation-kpi-value {
            color: #fff;
            font-size: 1.35rem;
            font-weight: 800;
        }

        .detail-card {
            border: 1px solid #eef0f3;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(20, 34, 66, 0.05);
        }

        .detail-card .card-header {
            border-bottom: 1px solid #f1f3f7;
            min-height: auto;
            padding: 1.25rem 1.5rem 0;
        }

        .detail-card .card-body {
            padding: 1.5rem;
        }

        .detail-metric {
            background: #f9fafc;
            border: 1px solid #edf0f5;
            border-radius: 0.9rem;
            height: 100%;
            padding: 1rem;
        }

        .detail-metric-label {
            color: #7e8299;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
        }

        .detail-metric-value {
            color: #1f2a44;
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.45;
            word-break: break-word;
        }

        .detail-side-card {
            background: #fff;
            border: 1px solid #eef0f3;
            border-radius: 1rem;
            padding: 1.1rem;
        }

        .detail-side-row {
            align-items: flex-start;
            border-bottom: 1px dashed #e8ebf1;
            display: flex;
            gap: 0.85rem;
            justify-content: space-between;
            padding: 0.8rem 0;
        }

        .detail-side-row:first-child {
            padding-top: 0;
        }

        .detail-side-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .detail-side-label {
            color: #7e8299;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .detail-side-value {
            color: #252f4a;
            font-size: 0.92rem;
            font-weight: 700;
            text-align: right;
        }

        .detail-status-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .detail-status-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #edf0f5;
            border-radius: 1rem;
            min-height: 130px;
            padding: 1rem;
        }

        .detail-status-badge {
            border-radius: 999px;
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
        }

        .detail-items-table thead th {
            color: #7e8299;
            font-size: 0.75rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .detail-items-table tbody tr:hover {
            background: #fbfcff;
        }
    </style>
@endsection

@section('content')
    @php
        $statusBadgeClass = match ($donationOrder->status) {
            'paid' => 'badge-light-success',
            'failed' => 'badge-light-danger',
            default => 'badge-light-warning',
        };

        $paymentStatusText = ucfirst($donationOrder->status ?? 'pending');
        $receiptStatusText = ucfirst($donationOrder->receiptStatus());
        $receiptBadgeClass = match ($donationOrder->receiptStatus()) {
            'sent' => 'badge-light-success',
            'failed' => 'badge-light-danger',
            'generated' => 'badge-light-info',
            default => 'badge-light-warning',
        };
    @endphp

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="donation-detail-shell">
                <div class="donation-hero p-6 p-lg-8 mb-6">
                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-6 position-relative">
                        <div class="pe-xl-6">
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="hero-pill">Donation Overview</span>
                                <span class="hero-pill">{{ strtoupper($donationOrder->payment_provider) }}</span>
                                @if ($donationOrder->hasReceipt())
                                    <span class="hero-pill">{{ $donationOrder->receiptNumberFormatted() }}</span>
                                @endif
                            </div>
                            <h1 class="fw-bolder text-white mb-2">{{ $donationOrder->donor_name ?: 'Donation Order' }}</h1>
                            <div class="fs-4 fw-semibold text-white opacity-75 mb-3">Order #{{ $donationOrder->order_uuid }}</div>
                            <div class="d-flex flex-wrap gap-3 mb-5">
                                <span class="badge {{ $statusBadgeClass }} fs-7 fw-bold">{{ $paymentStatusText }}</span>
                                <span class="badge {{ $receiptBadgeClass }} fs-7 fw-bold">Receipt {{ $receiptStatusText }}</span>
                                <span class="badge badge-light-primary fs-7 fw-bold">{{ $donationOrder->created_at?->format('d M Y, h:i A') }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('admin.donations.index') }}" class="btn btn-light fw-semibold">Back to Donations</a>
                                @if ($donationOrder->hasReceipt())
                                    <a href="{{ route('admin.donations.receipt.preview', $donationOrder) }}" class="btn btn-light-primary fw-semibold">Preview Receipt</a>
                                    <a href="{{ route('admin.donations.receipt.print', $donationOrder) }}" class="btn btn-light fw-semibold">Download Receipt</a>
                                    @can('manage receipts')
                                        <form method="POST" action="{{ route('admin.donations.receipt.resend', $donationOrder) }}">
                                            @csrf
                                            <button class="btn btn-light-success fw-semibold" type="submit">Resend Receipt</button>
                                        </form>
                                    @endcan
                                @elseif ($donationOrder->isPaid())
                                    <form method="POST" action="{{ route('admin.donations.receipt.generate', $donationOrder) }}">
                                        @csrf
                                        <button class="btn btn-warning fw-bold" type="submit">Generate Receipt</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="d-grid gap-4 flex-shrink-0" style="min-width: min(100%, 340px);">
                            <div class="donation-kpi">
                                <div class="donation-kpi-label">Total Donation</div>
                                <div class="donation-kpi-value">Rs {{ number_format($donationOrder->total_amount, 2) }}</div>
                            </div>
                            <div class="row g-4">
                                <div class="col-sm-6">
                                    <div class="donation-kpi h-100">
                                        <div class="donation-kpi-label">Items</div>
                                        <div class="donation-kpi-value">{{ $donationOrder->items->count() }}</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="donation-kpi h-100">
                                        <div class="donation-kpi-label">Receipt</div>
                                        <div class="donation-kpi-value">{{ $donationOrder->hasReceipt() ? 'Issued' : 'Pending' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

                <div class="row g-6 mb-6">
                    <div class="col-xl-8">
                        <div class="card detail-card mb-6">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title fw-bold mb-1">Donor Snapshot</h3>
                                    <div class="text-muted fs-7">Key donor identity and contact information.</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Donor Name</div>
                                            <div class="detail-metric-value">{{ $donationOrder->donor_name ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Phone</div>
                                            <div class="detail-metric-value">{{ $donationOrder->donor_phone ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Email</div>
                                            <div class="detail-metric-value">{{ $donationOrder->donor_email ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">PAN</div>
                                            <div class="detail-metric-value">{{ $donationOrder->pan_number ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Date of Birth</div>
                                            <div class="detail-metric-value">{{ $donationOrder->date_of_birth?->format('d M Y') ?? '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Citizen Consent</div>
                                            <div class="detail-metric-value">{{ $donationOrder->consent_indian_citizen ? 'Confirmed' : '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Address</div>
                                            <div class="detail-metric-value">{{ $donationOrder->address ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">Pincode</div>
                                            <div class="detail-metric-value">{{ $donationOrder->pincode ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">City</div>
                                            <div class="detail-metric-value">{{ $donationOrder->city ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-metric">
                                            <div class="detail-metric-label">State</div>
                                            <div class="detail-metric-value">{{ $donationOrder->state ?: '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card detail-card mb-6">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title fw-bold mb-1">Donation Items</h3>
                                    <div class="text-muted fs-7">Cause-wise breakdown of this donation order.</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-bordered detail-items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Cause</th>
                                                <th>Title</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Unit Amount</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($donationOrder->items as $item)
                                                <tr>
                                                    <td class="fw-semibold text-gray-900">{{ $item->causeModel?->title ?? $item->cause ?? 'General Donation' }}</td>
                                                    <td>{{ $item->title }}</td>
                                                    <td class="text-center">{{ $item->quantity ?? 1 }}</td>
                                                    <td class="text-end">Rs {{ number_format($item->unit_amount ?? $item->amount, 2) }}</td>
                                                    <td class="text-end fw-bolder text-gray-900">Rs {{ number_format($item->amount, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-8">No donation items recorded for this order.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end mt-5">
                                    <div class="detail-side-card" style="min-width: min(100%, 300px);">
                                        <div class="detail-side-row">
                                            <div class="detail-side-label">Items Count</div>
                                            <div class="detail-side-value">{{ $donationOrder->items->count() }}</div>
                                        </div>
                                        <div class="detail-side-row">
                                            <div class="detail-side-label">Grand Total</div>
                                            <div class="detail-side-value">Rs {{ number_format($donationOrder->total_amount, 2) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="detail-side-card mb-6">
                            <div class="fw-bolder fs-4 text-gray-900 mb-4">Payment Snapshot</div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Provider</div>
                                <div class="detail-side-value">{{ ucfirst($donationOrder->payment_provider ?? '—') }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Currency</div>
                                <div class="detail-side-value">{{ $donationOrder->currency ?: 'INR' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Provider Order ID</div>
                                <div class="detail-side-value">{{ $donationOrder->provider_order_id ?: '—' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Payment ID</div>
                                <div class="detail-side-value">{{ $donationOrder->provider_payment_id ?: '—' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Receipt Number</div>
                                <div class="detail-side-value">{{ $donationOrder->hasReceipt() ? $donationOrder->receiptNumberFormatted() : 'Not issued' }}</div>
                            </div>
                        </div>

                        <div class="detail-side-card mb-6">
                            <div class="fw-bolder fs-4 text-gray-900 mb-4">Order Metadata</div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Created</div>
                                <div class="detail-side-value">{{ $donationOrder->created_at?->format('d M Y, h:i A') ?? '—' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Paid At</div>
                                <div class="detail-side-value">{{ $donationOrder->paid_at?->format('d M Y, h:i A') ?? '—' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Failed At</div>
                                <div class="detail-side-value">{{ $donationOrder->failed_at?->format('d M Y, h:i A') ?? '—' }}</div>
                            </div>
                            <div class="detail-side-row">
                                <div class="detail-side-label">Country Code</div>
                                <div class="detail-side-value">{{ $donationOrder->donor_country_code ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="card detail-card">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title fw-bold mb-1">Lifecycle Status</h3>
                                    <div class="text-muted fs-7">Operational checkpoints for this donation.</div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="detail-status-grid">
                                    <div class="detail-status-card">
                                        <div class="detail-metric-label">Payment</div>
                                        <div class="mb-3"><span class="detail-status-badge {{ $statusBadgeClass }}">{{ $paymentStatusText }}</span></div>
                                        <div class="text-muted fs-7">{{ $donationOrder->paid_at?->format('d M Y, h:i A') ?? 'Awaiting payment confirmation' }}</div>
                                    </div>
                                    <div class="detail-status-card">
                                        <div class="detail-metric-label">Receipt</div>
                                        <div class="mb-3"><span class="detail-status-badge {{ $receiptBadgeClass }}">{{ $receiptStatusText }}</span></div>
                                        <div class="text-muted fs-7">{{ $donationOrder->receipt_sent_at?->format('d M Y, h:i A') ?? 'Receipt not sent yet' }}</div>
                                    </div>
                                    <div class="detail-status-card">
                                        <div class="detail-metric-label">WhatsApp</div>
                                        <div class="mb-3">
                                            <span class="detail-status-badge {{ $donationOrder->whatsapp_sent_at ? 'badge-light-success' : 'badge-light-warning' }}">
                                                {{ $donationOrder->whatsapp_sent_at ? 'Sent' : 'Pending' }}
                                            </span>
                                        </div>
                                        <div class="text-muted fs-7">{{ $donationOrder->whatsapp_sent_at?->format('d M Y, h:i A') ?? 'No WhatsApp confirmation recorded' }}</div>
                                    </div>
                                    <div class="detail-status-card">
                                        <div class="detail-metric-label">Sheets Log</div>
                                        <div class="mb-3">
                                            <span class="detail-status-badge {{ $donationOrder->sheet_logged_at ? 'badge-light-success' : 'badge-light-info' }}">
                                                {{ $donationOrder->sheet_logged_at ? 'Logged' : 'Not Logged' }}
                                            </span>
                                        </div>
                                        <div class="text-muted fs-7">{{ $donationOrder->sheet_logged_at?->format('d M Y, h:i A') ?? 'No sheet log timestamp available' }}</div>
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
