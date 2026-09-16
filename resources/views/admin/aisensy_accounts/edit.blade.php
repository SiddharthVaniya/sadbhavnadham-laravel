@extends('admin.layouts.minimal')

@section('title', 'Edit Aisensy Account')

@section('content')
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="admin-page-shell">
                <div class="admin-page-head mb-6">
                    <h1 class="mb-2 fw-bolder">Edit AiSensy Account</h1>
                    <p class="mb-0 opacity-75">Update API credentials and account status for <strong>{{ $aisensy_account->name }}</strong>.</p>
                </div>

                <div class="card admin-surface">
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

                        <form method="POST" action="{{ route('admin.aisensy-accounts.update', $aisensy_account) }}">
                            @csrf
                            @method('PUT')
                            @include('admin.aisensy_accounts._form')

                            <div class="d-flex justify-content-end gap-3 mt-7 border-top pt-6">
                                <a href="{{ route('admin.aisensy-accounts.index') }}" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
