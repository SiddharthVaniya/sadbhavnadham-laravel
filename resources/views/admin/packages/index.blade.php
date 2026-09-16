@extends('admin.layouts.minimal')

@section('title', 'Packages')

@section('content')
    @php
        $activeCount = $packages->where('is_active', true)->count();
    @endphp
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="admin-page-shell">
                <div class="admin-page-head mb-6">
                    <h1 class="mb-2 fw-bolder">Packages</h1>
                    <p class="mb-3 opacity-75">Review all cause packages and quickly toggle availability.</p>
                    <div class="admin-pills">
                        <span class="admin-pill">Total: {{ $packages->count() }}</span>
                        <span class="admin-pill">Active: {{ $activeCount }}</span>
                        <span class="admin-pill">Inactive: {{ $packages->count() - $activeCount }}</span>
                    </div>
                </div>

                <div class="card admin-surface">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table id="kt_datatable" class="table table-row-bordered gy-5 align-middle mb-0 admin-table">
                                <thead>
                                    <tr>
                                        <th>Cause</th>
                                        <th>Title</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Sort</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($packages as $package)
                                        <tr>
                                            <td class="text-muted">{{ $package->cause?->title ?? '—' }}</td>
                                            <td class="fw-bold">{{ $package->title }}</td>
                                            <td>₹ {{ number_format((float) $package->amount) }}</td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                                        data-url="{{ $package->cause ? route('admin.causes.packages.toggle-active', [$package->cause, $package]) : '#' }}"
                                                        @checked($package->is_active) @disabled(! $package->cause)
                                                        title="{{ $package->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                                </div>
                                            </td>
                                            <td>{{ $package->sort_order }}</td>
                                            <td class="text-end">
                                                @if ($package->cause)
                                                    <a class="btn btn-sm btn-light-primary" href="{{ route('admin.causes.packages.edit', [$package->cause, $package]) }}">Edit</a>
                                                @endif
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
        document.addEventListener('DOMContentLoaded', function() {
            $('#kt_datatable').DataTable();

            document.querySelectorAll('.toggle-active').forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    const url = this.dataset.url;
                    const el = this;
                    if (url === '#') return;
                    el.disabled = true;

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    })
                    .then(r => r.json())
                    .then(data => {
                        el.checked = data.is_active;
                        el.title = data.is_active ? 'Active – click to deactivate' : 'Inactive – click to activate';
                        window.adminNotify(data.is_active ? 'success' : 'info', data.is_active ? 'Package activated.' : 'Package deactivated.');
                    })
                    .catch(() => {
                        el.checked = !el.checked;
                        window.adminNotify('error', 'Failed to update status. Please try again.');
                    })
                    .finally(() => { el.disabled = false; });
                });
            });
        });
    </script>
@endsection
