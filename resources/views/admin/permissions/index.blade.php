@extends('admin.layouts.minimal')

@section('title', 'Permissions')

@section('content')
<div class="d-flex flex-column flex-column-fluid">

    {{-- Toolbar --}}
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div class="app-container container-fluid d-flex flex-stack">

            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">
                    Permission List
                </h1>

                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.dashboard') }}"
                           class="text-muted text-hover-primary">
                            Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">
                        Permissions
                    </li>
                </ul>
            </div>

            <div>
                <a href="{{ route('admin.permissions.create') }}"
                   class="btn btn-primary">
                    Add Permission
                </a>
            </div>

        </div>
    </div>

    {{-- Content --}}
    <div class="app-content flex-column-fluid">
        <div class="app-container container-fluid">
            <div class="card card-flush">
                <div class="card-body p-6">

                    <table id="kt_datatable"
                           class="table table-row-bordered gy-5 align-middle mb-0">

                        <thead>
                            <tr class="fw-semibold fs-6 text-muted">
                                <th>Permission Name</th>
                                <th>Assigned Roles</th>
                                <th width="150"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($permissions as $permission)
                                <tr>
                                    <td>{{ $permission->name }}</td>

                                    <td>
                                        @foreach($permission->roles as $role)
                                            <span class="badge badge-light-success mb-1">
                                                {{ ucfirst($role->name) }}
                                            </span>
                                        @endforeach
                                    </td>

                                    <td>
                                        <a href="{{ route('admin.permissions.edit', $permission) }}"
                                           class="btn btn-sm btn-light-primary">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.permissions.destroy', $permission) }}"
                                              class="d-inline">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="btn btn-sm btn-danger">
                                                Delete
                                            </button>
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
@endsection

@section('customjs')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#kt_datatable').DataTable();
});
</script>
@endsection
