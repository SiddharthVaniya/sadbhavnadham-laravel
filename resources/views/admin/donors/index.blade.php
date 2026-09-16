@extends('admin.layouts.minimal')

@section('title', 'Donors')

@section('content')
    <div class="d-flex flex-column flex-column-fluid">
        <x-admin.page-header
            title="Donors"
            :subtitle="number_format($stats['total_donors']) . ' donors · ₹' . number_format($stats['total_amount'], 0) . ' paid total'"
        />

        <div class="app-content flex-column-fluid">
            <div class="app-container container-fluid">

                {{-- Summary Stat Cards --}}
                <div class="row g-5 mb-6">

                    <div class="col-sm-4">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 p-6">
                                <div class="symbol symbol-50px">
                                    <span class="symbol-label bg-light-primary">
                                        <i class="ki-duotone ki-people fs-2x text-primary">
                                            <span class="path1"></span><span class="path2"></span>
                                            <span class="path3"></span><span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-bold fs-2">{{ $stats['total_donors'] }}</div>
                                    <div class="text-muted fs-7">Total Donors</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 p-6">
                                <div class="symbol symbol-50px">
                                    <span class="symbol-label bg-light-success">
                                        <i class="ki-duotone ki-dollar fs-2x text-success">
                                            <span class="path1"></span><span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-bold fs-2">₹ {{ number_format($stats['total_amount'], 0) }}</div>
                                    <div class="text-muted fs-7">Total Paid Amount</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 p-6">
                                <div class="symbol symbol-50px">
                                    <span class="symbol-label bg-light-warning">
                                        <i class="ki-duotone ki-arrows-circle fs-2x text-warning">
                                            <span class="path1"></span><span class="path2"></span>
                                        </i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-bold fs-2">{{ $stats['repeat_donors'] }}</div>
                                    <div class="text-muted fs-7">Repeat Donors</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Donors Table --}}
                <div class="card card-flush">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table id="kt_datatable" class="table table-row-bordered gy-5 align-middle">
                                <thead>
                                    <tr class="fw-semibold fs-6 text-muted">
                                        <th>Donor</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>City / State</th>
                                        <th>Paid</th>
                                        <th>Paid Amount</th>
                                        <th>Attempts</th>
                                        <th>Last Paid</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($donors as $donor)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="symbol symbol-40px symbol-circle">
                                                        <span class="symbol-label bg-light-primary text-primary fw-bold fs-6">
                                                            {{ strtoupper(substr($donor->name ?? 'D', 0, 1)) }}
                                                        </span>
                                                    </div>
                                                    <span class="fw-bold text-gray-800">{{ $donor->name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-muted">{{ $donor->email }}</td>
                                            <td class="text-muted">{{ $donor->phone }}</td>
                                            <td class="text-muted">
                                                {{ implode(', ', array_filter([$donor->city, $donor->state])) ?: '-' }}
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold">{{ $donor->paid_donations ?? 0 }}</span>
                                                    @if (($donor->paid_donations ?? 0) > 1)
                                                        <span class="badge badge-light-success fs-8">Repeat</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="fw-bold text-success">
                                                ₹ {{ number_format((float) ($donor->paid_amount ?? 0), 0) }}
                                            </td>
                                            <td class="text-muted">
                                                {{ $donor->total_attempts ?? 0 }}
                                            </td>
                                            <td class="text-muted">
                                                {{ $donor->last_paid_donation_at ? \Carbon\Carbon::parse($donor->last_paid_donation_at)->format('d M Y') : '-' }}
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.donors.show', $donor) }}"
                                                    class="btn btn-sm btn-light-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        $(document).ready(function () {
            $('#kt_datatable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [],
                language: {
                    search: '',
                    searchPlaceholder: 'Search donors...',
                    lengthMenu: 'Show _MENU_ donors',
                    info: 'Showing _START_ to _END_ of _TOTAL_ donors',
                },
                columnDefs: [
                    { orderable: false, targets: -1 },
                ],
            });
        });
    </script>
@endsection
