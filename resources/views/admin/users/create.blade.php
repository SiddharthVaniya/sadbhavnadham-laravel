@extends('admin.layouts.minimal')
@section('title', 'Create User')

@section('content')
<div class="d-flex flex-column flex-column-fluid">

    {{-- Toolbar --}}
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center">
                <h1 class="page-heading text-gray-900 fw-bold fs-3">
                    Create User
                </h1>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="app-content flex-column-fluid">
        <div class="app-container container-fluid">

            <div class="card card-flush">
                <div class="card-body p-6">

                    <form method="POST" action="{{ route('admin.users.store') }}">
                        @csrf

                        @include('admin.users.form')

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
