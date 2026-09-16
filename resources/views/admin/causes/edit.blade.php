@extends('admin.layouts.minimal')

@section('title', 'Edit Cause')

@section('content')
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="admin-page-shell">
                <div class="admin-page-head mb-6 d-flex flex-column flex-lg-row justify-content-between gap-4">
                    <div>
                        <h1 class="mb-2 fw-bolder">Edit Cause</h1>
                        <p class="mb-0 opacity-75">Update campaign content and package mapping for <strong>{{ $cause->title }}</strong>.</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.causes.packages.create', $cause) }}" class="btn btn-sadbhavna fw-bold">Add Package</a>
                    </div>
                </div>

                <div class="card admin-surface mb-6">
                    <div class="card-body p-6 p-lg-8">
                        @if ($errors->any())
                            <div class="alert alert-danger mb-6">
                                <div class="fw-semibold mb-2">Please fix the following:</div>
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.causes.update', $cause) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            @include('admin.causes.form', ['cause' => $cause])

                            <div class="d-flex justify-content-end gap-3 mt-7 border-top pt-6">
                                <a href="{{ route('admin.causes.index') }}" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card admin-surface">
                    <div class="card-header border-0 pt-6">
                        <h3 class="card-title fw-bold">Packages for {{ $cause->title }}</h3>
                    </div>
                    <div class="card-body p-6 pt-2">
                        <div class="table-responsive">
                            <table class="table table-row-bordered gy-5 align-middle mb-0 admin-table" id="kt_datatable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Sort</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cause->packages as $package)
                                        <tr>
                                            <td class="fw-bold">{{ $package->title }}</td>
                                            <td>₹ {{ number_format((float) $package->amount) }}</td>
                                            <td>
                                                <span class="badge {{ $package->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                                    {{ $package->is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td>{{ $package->sort_order }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-light-primary" href="{{ route('admin.causes.packages.edit', [$cause, $package]) }}">Edit</a>
                                                <form class="d-inline" method="POST" action="{{ route('admin.causes.packages.destroy', [$cause, $package]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-light-danger" type="submit">Delete</button>
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
            });
        </script>
    @endsection
