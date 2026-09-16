@extends('admin.layouts.minimal')

@section('title', 'Dashboard')

@section('customcss')
    <style>
        .dashboard-shell {
            background: radial-gradient(circle at 100% 0, rgba(0, 167, 167, 0.08), transparent 45%),
                radial-gradient(circle at 0 100%, rgba(255, 168, 75, 0.08), transparent 55%);
            border-radius: 1.25rem;
            padding: 1.25rem;
        }

        .dash-card {
            border: 1px solid #f1f1f4;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(20, 34, 66, 0.05);
        }

        .dash-kpi {
            min-height: 150px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dash-kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(20, 34, 66, 0.1);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .status-row {
            border: 1px dashed #e9e9ef;
            border-radius: 0.75rem;
            padding: 0.7rem 0.9rem;
            margin-bottom: 0.65rem;
        }

        .soft-panel {
            background: #f9f9fb;
            border: 1px solid #efeff2;
            border-radius: 0.9rem;
        }

        .row-soft-hover:hover {
            background: #fcfcff;
        }
    </style>
@endsection

@section('content')
    @php
        $causeHealth = $causeCount > 0 ? round(($activeCauseCount / $causeCount) * 100) : 0;
        $packageHealth = $packageCount > 0 ? round(($activePackageCount / $packageCount) * 100) : 0;

        $statusLabels = [
            \App\Models\DonationOrder::STATUS_PAID => 'Paid',
            \App\Models\DonationOrder::STATUS_PENDING => 'Pending',
            \App\Models\DonationOrder::STATUS_FAILED => 'Failed',
        ];

        $statusColors = [
            \App\Models\DonationOrder::STATUS_PAID => 'success',
            \App\Models\DonationOrder::STATUS_PENDING => 'warning',
            \App\Models\DonationOrder::STATUS_FAILED => 'danger',
        ];
    @endphp

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="dashboard-shell">
                <div class="row g-5 mb-5">
                    <div class="col-12">
                        <div class="card dash-card border-0" style="background: linear-gradient(125deg, #1f2a44 0%, #0f7f87 100%);">
                            <div class="card-body p-8 p-lg-10">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-6">
                                    <div>
                                        <div class="text-uppercase fw-bold text-white opacity-75 fs-8 mb-2">{{ $branding['adminLabel'] }}</div>
                                        <h1 class="text-white fw-bolder fs-2x mb-3">Dashboard Overview</h1>
                                        <div class="d-flex flex-wrap gap-3">
                                            <span class="badge badge-light text-gray-800 fw-semibold px-4 py-2">{{ $totalDonations ?? 0 }} total orders</span>
                                            <span class="badge badge-light text-gray-800 fw-semibold px-4 py-2">{{ $activeCauseCount ?? 0 }} active causes</span>
                                            <span class="badge badge-light text-gray-800 fw-semibold px-4 py-2">{{ $activePackageCount ?? 0 }} active packages</span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-start align-items-lg-end justify-content-between">
                                        <div class="text-white opacity-75 fw-semibold mb-4">Updated: {{ now()->format('d M Y, h:i A') }}</div>
                                        <div class="d-flex flex-wrap gap-3">
                                            <a href="{{ route('admin.causes.index') }}" class="btn btn-light fw-semibold">Manage Causes</a>
                                            <a href="{{ route('admin.donations.create') }}" class="btn btn-sadbhavna fw-bold">Add Donation</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-5 mb-5">
                    <div class="col-md-6 col-xl-3">
                        <div class="card dash-card dash-kpi h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="kpi-icon bg-light-success"><i class="ki-outline ki-wallet fs-2 text-success"></i></div>
                                <div>
                                    <div class="text-gray-700 fw-semibold mb-1">Total Donation Value</div>
                                    <div class="fw-bolder fs-2x text-gray-900">Rs {{ number_format($totalDonationAmount ?? 0, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card dash-card dash-kpi h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="kpi-icon bg-light-primary"><i class="ki-outline ki-element-11 fs-2 text-primary"></i></div>
                                <div>
                                    <div class="text-gray-700 fw-semibold mb-1">Total Donation Orders</div>
                                    <div class="fw-bolder fs-2x text-gray-900">{{ $totalDonations ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card dash-card dash-kpi h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="kpi-icon bg-light-info"><i class="ki-outline ki-abstract-14 fs-2 text-info"></i></div>
                                <div>
                                    <div class="text-gray-700 fw-semibold mb-1">Today</div>
                                    <div class="fw-bolder fs-2x text-gray-900">Rs {{ number_format($todayAmount ?? 0, 2) }}</div>
                                    <div class="text-muted fs-7">{{ $todayDonations ?? 0 }} donations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card dash-card dash-kpi h-100">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="kpi-icon bg-light-warning"><i class="ki-outline ki-package fs-2 text-warning"></i></div>
                                <div>
                                    <div class="text-gray-700 fw-semibold mb-1">This Month</div>
                                    <div class="fw-bolder fs-2x text-gray-900">Rs {{ number_format($thisMonthAmount ?? 0, 2) }}</div>
                                    <div class="text-muted fs-7">{{ $thisMonthDonations ?? 0 }} donations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-5 mb-5">
                    <div class="col-xl-4">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Collection Health</h3>
                            </div>
                            <div class="card-body pt-2">
                                <div class="soft-panel p-4 mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-gray-700 fw-semibold">Active Causes</span>
                                        <span class="fw-bold">{{ $activeCauseCount }} / {{ $causeCount }}</span>
                                    </div>
                                    <div class="progress h-8px bg-light-success">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $causeHealth }}%;"></div>
                                    </div>
                                </div>
                                <div class="soft-panel p-4 mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-gray-700 fw-semibold">Active Packages</span>
                                        <span class="fw-bold">{{ $activePackageCount }} / {{ $packageCount }}</span>
                                    </div>
                                    <div class="progress h-8px bg-light-primary">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $packageHealth }}%;"></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-4">
                                    <div>
                                        <div class="text-muted fs-7">Donation Items</div>
                                        <div class="fw-bold fs-4">{{ $totalDonationItems ?? 0 }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-muted fs-7">Avg Donation</div>
                                        <div class="fw-bold fs-4">Rs {{ number_format($averageDonationAmount ?? 0, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Donation Status</h3>
                            </div>
                            <div class="card-body pt-2">
                                @forelse($statusLabels as $statusKey => $label)
                                    @php
                                        $statusCount = (int) ($donationStatusCounts[$statusKey] ?? 0);
                                        $statusPercent = (float) ($donationStatusPercentages[$statusKey] ?? 0);
                                        $statusColor = $statusColors[$statusKey] ?? 'secondary';
                                    @endphp
                                    <div class="status-row">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-semibold text-gray-700">{{ $label }}</span>
                                            <span class="badge badge-light-{{ $statusColor }}">{{ $statusCount }}</span>
                                        </div>
                                        <div class="progress h-6px bg-light-{{ $statusColor }}">
                                            <div class="progress-bar bg-{{ $statusColor }}" role="progressbar" style="width: {{ $statusPercent }}%;"></div>
                                        </div>
                                        <div class="text-muted fs-8 mt-1">{{ number_format($statusPercent, 2) }}%</div>
                                    </div>
                                @empty
                                    <div class="text-muted">No status records yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Quick Actions</h3>
                            </div>
                            <div class="card-body pt-2">
                                <div class="soft-panel p-4 mb-3 row-soft-hover">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-gray-900">Manage Causes</div>
                                            <div class="text-muted fs-7">Create and activate campaigns</div>
                                        </div>
                                        <a href="{{ route('admin.causes.index') }}" class="btn btn-sm btn-light-primary">Open</a>
                                    </div>
                                </div>
                                <div class="soft-panel p-4 mb-3 row-soft-hover">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-gray-900">Donation Orders</div>
                                            <div class="text-muted fs-7">Review pending and paid orders</div>
                                        </div>
                                        <a href="{{ route('admin.donations.index') }}" class="btn btn-sm btn-light-primary">Open</a>
                                    </div>
                                </div>
                                <div class="soft-panel p-4 mb-3 row-soft-hover">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-gray-900">Notification Settings</div>
                                            <div class="text-muted fs-7">Enable or disable receipt and WhatsApp flows</div>
                                        </div>
                                        <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-light-primary">Open</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-5 mb-5">
                    <div class="col-xl-8">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Monthly Donation Trend</h3>
                            </div>
                            <div class="card-body pt-2">
                                <div id="monthly-chart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Top Causes</h3>
                            </div>
                            <div class="card-body pt-2">
                                @forelse($topCauses ?? [] as $cause)
                                    <div class="d-flex align-items-center justify-content-between soft-panel p-3 mb-3 row-soft-hover">
                                        <div class="me-2">
                                            <div class="fw-bold text-gray-900">{{ $cause->title }}</div>
                                            <div class="text-muted fs-7">{{ $cause->donations }} donations</div>
                                        </div>
                                        <div class="fw-bold text-primary">Rs {{ number_format($cause->amount, 2) }}</div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-8">No top causes yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-5">
                    <div class="col-xl-8">
                        <div class="card dash-card h-100">
                            <div class="card-header border-0 pt-5">
                                <h3 class="card-title fw-bold text-gray-900">Recent Donations</h3>
                                <div class="card-toolbar">
                                    <a href="{{ route('admin.donations.index') }}" class="btn btn-sm btn-light-primary">View All</a>
                                </div>
                            </div>
                            <div class="card-body py-0">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed gy-4">
                                        <thead>
                                            <tr class="text-muted fw-semibold fs-7 text-uppercase">
                                                <th>Donor</th>
                                                <th>Cause</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentDonations ?? [] as $donation)
                                                @php
                                                    $badgeClass = match ($donation->status) {
                                                        \App\Models\DonationOrder::STATUS_PAID => 'badge-light-success',
                                                        \App\Models\DonationOrder::STATUS_PENDING => 'badge-light-warning',
                                                        \App\Models\DonationOrder::STATUS_FAILED => 'badge-light-danger',
                                                        default => 'badge-light-secondary',
                                                    };
                                                @endphp
                                                <tr class="row-soft-hover">
                                                    <td>
                                                        <div class="fw-bold text-gray-900">{{ $donation->donor_name }}</div>
                                                        <div class="text-muted fs-7">{{ $donation->donor_email }}</div>
                                                    </td>
                                                    <td class="fw-semibold text-gray-700">{{ $donation->items->first()?->causeModel->title ?? 'N/A' }}</td>
                                                    <td class="fw-bold text-gray-900">Rs {{ number_format($donation->total_amount, 2) }}</td>
                                                    <td class="text-muted">{{ $donation->created_at->format('d M Y') }}</td>
                                                    <td><span class="badge {{ $badgeClass }}">{{ ucfirst($donation->status ?? 'unknown') }}</span></td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-8">No recent donations</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="d-flex flex-column gap-5 h-100">
                            <div class="card dash-card">
                                <div class="card-header border-0 pt-5">
                                    <h3 class="card-title fw-bold text-gray-900">Top Donors</h3>
                                </div>
                                <div class="card-body pt-2">
                                    @forelse($topDonors ?? [] as $donor)
                                        <div class="soft-panel p-3 mb-3 row-soft-hover">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="fw-bold text-gray-900">{{ $donor->donor_name }}</div>
                                                <div class="fw-bold text-success">Rs {{ number_format($donor->amount, 2) }}</div>
                                            </div>
                                            <div class="text-muted fs-8 mb-1">{{ $donor->donor_email }}</div>
                                            <div class="text-muted fs-8">{{ $donor->orders }} orders</div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-8">No donor analytics yet.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="card dash-card flex-grow-1">
                                <div class="card-header border-0 pt-5">
                                    <div>
                                        <h3 class="card-title fw-bold text-gray-900 mb-1">Today's Birthdays</h3>
                                        <div class="text-muted fs-7">Donors whose date of birth matches today</div>
                                    </div>
                                </div>
                                <div class="card-body pt-2">
                                    @forelse($todaysBirthdays ?? [] as $birthdayDonor)
                                        <div class="soft-panel p-3 mb-3 row-soft-hover">
                                            <div class="d-flex justify-content-between align-items-center mb-1 gap-3">
                                                <a href="{{ route('admin.donations.show', $birthdayDonor->latestOrder) }}" class="fw-bold text-gray-900 text-hover-primary">
                                                    {{ $birthdayDonor->donor_name }}
                                                </a>
                                                <span class="badge badge-light-warning">
                                                    {{ $birthdayDonor->date_of_birth?->age ?? now()->year - \Illuminate\Support\Carbon::parse($birthdayDonor->date_of_birth)->year }} yrs
                                                </span>
                                            </div>
                                            <div class="text-muted fs-8 mb-1">{{ $birthdayDonor->donor_email ?: 'No email' }}</div>
                                            <div class="d-flex justify-content-between align-items-center fs-8 text-muted gap-3">
                                                <span>{{ $birthdayDonor->orders }} orders</span>
                                                <span>Lifetime Rs {{ number_format($birthdayDonor->lifetime_amount, 2) }}</span>
                                            </div>
                                            <div class="mt-3">
                                                <a href="{{ route('admin.donations.show', $birthdayDonor->latestOrder) }}" class="btn btn-sm btn-light-primary">Open Latest Donation</a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-8">No donor birthdays today.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="card dash-card">
                                <div class="card-header border-0 pt-5">
                                    <div>
                                        <h3 class="card-title fw-bold text-gray-900 mb-1">Upcoming Birthdays</h3>
                                        <div class="text-muted fs-7">Next 7 days from donor records</div>
                                    </div>
                                </div>
                                <div class="card-body pt-2">
                                    @forelse($upcomingBirthdays ?? [] as $birthdayDonor)
                                        <div class="soft-panel p-3 mb-3 row-soft-hover">
                                            <div class="d-flex justify-content-between align-items-center mb-1 gap-3">
                                                <a href="{{ route('admin.donations.show', $birthdayDonor->latestOrder) }}" class="fw-bold text-gray-900 text-hover-primary">
                                                    {{ $birthdayDonor->donor_name }}
                                                </a>
                                                <span class="badge badge-light-info">In {{ $birthdayDonor->days_until_birthday }} day{{ $birthdayDonor->days_until_birthday === 1 ? '' : 's' }}</span>
                                            </div>
                                            <div class="text-muted fs-8 mb-1">
                                                {{ $birthdayDonor->next_birthday?->format('d M') }} · {{ $birthdayDonor->donor_email ?: 'No email' }}
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center fs-8 text-muted gap-3">
                                                <span>{{ $birthdayDonor->orders }} orders</span>
                                                <span>Lifetime Rs {{ number_format($birthdayDonor->lifetime_amount, 2) }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-8">No upcoming donor birthdays.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        google.charts.load('current', { packages: ['corechart'] });
        google.charts.setOnLoadCallback(drawMonthlyChart);

        function drawMonthlyChart() {
            const data = new google.visualization.DataTable();
            data.addColumn('string', 'Month');
            data.addColumn('number', 'Donations');
            data.addColumn('number', 'Amount (Rs)');

            const monthlyData = @json($monthlyData ?? []);

            const rows = monthlyData
                .map((item) => [
                    `${item.year}-${String(item.month).padStart(2, '0')}`,
                    Number.parseInt(item.donations, 10),
                    Number.parseFloat(item.amount),
                ])
                .reverse();

            if (rows.length === 0) {
                rows.push(['No Data', 0, 0]);
            }

            data.addRows(rows);

            const options = {
                backgroundColor: 'transparent',
                chartArea: { left: 45, right: 50, top: 20, bottom: 40 },
                legend: { position: 'bottom' },
                hAxes: {
                    0: { title: 'Donations', textStyle: { color: '#7e8299' } },
                    1: { title: 'Amount (Rs)', textStyle: { color: '#7e8299' } },
                },
                vAxis: {
                    textStyle: { color: '#7e8299' },
                },
                series: {
                    0: { targetAxisIndex: 0, color: '#0f7f87' },
                    1: { targetAxisIndex: 1, color: '#f59f44' },
                },
                bar: { groupWidth: '62%' },
            };

            const chart = new google.visualization.BarChart(document.getElementById('monthly-chart'));
            chart.draw(data, options);
        }

        window.addEventListener('resize', drawMonthlyChart);
    </script>
@endpush