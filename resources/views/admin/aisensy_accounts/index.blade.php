

@extends('admin.layouts.minimal')

@section('title', 'aiSensy Accounts')

@section('content')
    @php
        $activeCount = $accounts->where('is_active', true)->count();
    @endphp
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="admin-page-shell">
                <div class="admin-page-head mb-6 d-flex flex-column flex-lg-row justify-content-between gap-4">
                    <div>
                        <h1 class="mb-2 fw-bolder">AiSensy Accounts</h1>
                        <p class="mb-3 opacity-75">Manage API credentials and account activation for WhatsApp messaging.</p>
                        <div class="admin-pills">
                            <span class="admin-pill">Total: {{ $accounts->count() }}</span>
                            <span class="admin-pill">Active: {{ $activeCount }}</span>
                            <span class="admin-pill">Inactive: {{ $accounts->count() - $activeCount }}</span>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('admin.aisensy-accounts.create') }}" class="btn btn-sadbhavna fw-bold">Add Account</a>
                    </div>
                </div>

                <div class="card admin-surface">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table id="kt_datatable" class="table table-row-bordered gy-5 align-middle mb-0 admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Country</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accounts as $account)
                                        <tr>
                                            <td class="fw-bold">{{ $account->name }}</td>
                                            <td>{{ $account->country_code }}</td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                                        data-url="{{ route('admin.aisensy-accounts.toggle-active', $account) }}"
                                                        @checked($account->is_active)
                                                        title="{{ $account->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.aisensy-accounts.edit', $account) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                                <form action="{{ route('admin.aisensy-accounts.destroy', $account) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this account?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-light-danger">Delete</button>
                                                </form>
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
        document.addEventListener('DOMContentLoaded', function () {
            $('#kt_datatable').DataTable();

            document.querySelectorAll('.toggle-active').forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    const url = this.dataset.url;
                    const el = this;
                    el.disabled = true;

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            el.checked = data.is_active;
                            el.title = data.is_active ? 'Active – click to deactivate' : 'Inactive – click to activate';
                            window.adminNotify(data.is_active ? 'success' : 'info', data.is_active ? 'AiSensy account activated.' : 'AiSensy account deactivated.');
                        })
                        .catch(function () {
                            el.checked = !el.checked;
                            window.adminNotify('error', 'Failed to update status. Please try again.');
                        })
                        .finally(function () { el.disabled = false; });
                });
            });
        });
    </script>
@endsection
