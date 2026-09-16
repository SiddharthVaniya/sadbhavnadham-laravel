@extends('admin.layouts.minimal')

@section('title', 'Users')

@section('content')
<div class="d-flex flex-column flex-column-fluid">

    {{-- Toolbar --}}
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column my-0">
                    User List
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
                        Users
                    </li>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.users.create') }}"
                   class="btn btn-primary">
                    Add User
                </a>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="card card-flush">
                <div class="card-body p-6">

                    <div class="table-responsive">
                        <table id="kt_datatable"
                               class="table table-row-bordered gy-5 align-middle mb-0">

                            <thead>
                                <tr class="fw-semibold fs-6 text-muted">
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th>Created</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($users as $user)
                                    <tr>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>

                                        <td>
                                            @foreach($user->roles as $role)
                                                <span class="badge badge-light-success">
                                                    {{ ucfirst($role->name) }}
                                                </span>
                                            @endforeach
                                        </td>

                                        <td>{{ $user->created_at->format('d M Y') }}</td>

                                        <td>
                                            <a href="{{ route('admin.users.edit', $user) }}"
                                               class="btn btn-sm btn-light-primary">
                                                Edit
                                            </a>

                                            @if(!$user->hasRole('super_admin'))
                                            <form class="d-inline"
                                                  method="POST"
                                                  action="{{ route('admin.users.destroy', $user) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button class="btn btn-sm btn-danger"
                                                        type="submit">
                                                    Delete
                                                </button>
                                            </form>
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
document.addEventListener('DOMContentLoaded', function () {
    $('#kt_datatable').DataTable();
});
</script>
@endsection
