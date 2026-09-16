@extends('admin.layouts.minimal')

@section('title', 'Edit User')

@section('content')
<div class="d-flex flex-column flex-column-fluid">

    {{-- Toolbar --}}
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column my-0">
                    Edit User
                </h1>

                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.dashboard') }}" class="text-muted text-primary">
                            Dashboard
                        </a>
                    </li>

                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>

                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.users.index') }}" class="text-muted text-primary">
                            All Users
                        </a>
                    </li>

                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>

                    <li class="breadcrumb-item text-muted">
                        Edit User
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">

            <div class="card card-flush mb-5">
                <div class="card-body p-6">

                    @if ($errors->any())
                        <div class="alert alert-danger mb-5">
                            <ul class="mb-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.users.form', ['user' => $user])

                        <div class="text-end mt-6">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-light me-2">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection
