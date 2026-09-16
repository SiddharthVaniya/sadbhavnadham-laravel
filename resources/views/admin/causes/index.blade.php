@extends('admin.layouts.minimal')

@section('title', 'Causes')

@section('content')
    @php
        $activeCount = $causes->where('is_active', true)->count();
    @endphp
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="admin-page-shell">
                <div class="admin-page-head mb-6 d-flex flex-column flex-lg-row justify-content-between gap-4">
                    <div>
                        <h1 class="mb-2 fw-bolder">Causes</h1>
                        <p class="mb-3 opacity-75">Manage active campaigns, visibility, and display order.</p>
                        <div class="admin-pills">
                            <span class="admin-pill">Total: {{ $causes->count() }}</span>
                            <span class="admin-pill">Active: {{ $activeCount }}</span>
                            <span class="admin-pill">Inactive: {{ $causes->count() - $activeCount }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start align-items-lg-center gap-3">
                        <a href="{{ route('admin.causes.create') }}" class="btn btn-sadbhavna fw-bold">Add Cause</a>
                    </div>
                </div>

                <div class="card admin-surface">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table id="kt_datatable" class="table table-row-bordered gy-5 align-middle mb-0 admin-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Slug</th>
                                        <th>Status</th>
                                        <th>Sort</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($causes as $cause)
                                        <tr>
                                            <td class="fw-bold text-gray-900">{{ $cause->title }}</td>
                                            <td class="text-muted">{{ $cause->slug }}</td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-active" type="checkbox" role="switch"
                                                        data-url="{{ route('admin.causes.toggle-active', $cause) }}" @checked($cause->is_active)
                                                        title="{{ $cause->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                                </div>
                                            </td>
                                            <td>{{ $cause->sort_order }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.causes.edit', $cause) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                                <form class="d-inline delete-form" method="POST" action="{{ route('admin.causes.destroy', $cause) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-light-danger delete-btn">Delete</button>
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
        document.addEventListener('DOMContentLoaded', function() {

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
                    .then(r => r.json())
                    .then(data => {
                        el.checked = data.is_active;
                        el.title = data.is_active ? 'Active – click to deactivate' : 'Inactive – click to activate';
                        window.adminNotify(data.is_active ? 'success' : 'info', data.is_active ? 'Cause activated.' : 'Cause deactivated.');
                    })
                    .catch(() => {
                        el.checked = !el.checked;
                        window.adminNotify('error', 'Failed to update status. Please try again.');
                    })
                    .finally(() => { el.disabled = false; });
                });
            });

            document.querySelectorAll('.delete-btn').forEach(function(button) {

                button.addEventListener('click', function() {

                    let form = this.closest('form');

                    Swal.fire({
                        title: "Are you sure?",
                        text: "You will not be able to recover this cause!",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Yes, delete it!"
                    }).then((result) => {

                        if (result.isConfirmed) {
                            form.submit();
                        }

                    });

                });

            });

        });
    </script>
@endsection
