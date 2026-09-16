@extends('admin.layouts.minimal')

@section('title', 'Edit Permission')

@section('content')
<div class="d-flex flex-column flex-column-fluid">

    <div class="app-content flex-column-fluid">
        <div class="app-container container-fluid">

            <div class="card card-flush">
                <div class="card-body p-6">

                    <form method="POST"
                          action="{{ route('admin.permissions.update', $permission) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.permissions.form')

                        <div class="text-end mt-6">
                            <a href="{{ route('admin.permissions.index') }}"
                               class="btn btn-light me-2">
                                Cancel
                            </a>

                            <button class="btn btn-primary">
                                Update
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection
