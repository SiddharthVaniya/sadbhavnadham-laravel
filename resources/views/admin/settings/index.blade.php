@extends('admin.layouts.minimal')

@section('title', 'Settings')

@section('content')
    <div class="d-flex flex-column flex-column-fluid">
        {{-- Toolbar --}}
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column my-0">
                        Notification Settings
                    </h1>
                    <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted">
                            <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <span class="bullet bg-gray-500 w-5px h-2px"></span>
                        </li>
                        <li class="breadcrumb-item text-muted">Settings</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-fluid">
                <div class="card card-flush">
                    <div class="card-header pt-6">
                        <div class="card-title">
                            <h3 class="card-label fw-bold text-gray-900">Notification Toggles</h3>
                        </div>
                    </div>
                    <div class="card-body p-6">
                        <p class="text-muted mb-6">
                            Toggle each notification channel on or off. Changes take effect immediately — no page reload needed.
                        </p>

                        <div class="table-responsive">
                            <table class="table table-row-bordered gy-5 align-middle mb-0">
                                <thead>
                                    <tr class="fw-semibold fs-6 text-muted">
                                        <th>Notification</th>
                                        <th>Description</th>
                                        <th class="text-center w-150px">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($settings as $setting)
                                        <tr id="row-{{ $setting->id }}">
                                            <td class="fw-bold">{{ $setting->label }}</td>
                                            <td class="text-muted">{{ $setting->description }}</td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-3">
                                                    <div class="form-check form-switch">
                                                        <input
                                                            class="form-check-input setting-toggle"
                                                            type="checkbox"
                                                            role="switch"
                                                            id="toggle-{{ $setting->id }}"
                                                            data-url="{{ route('admin.settings.toggle', $setting) }}"
                                                            data-id="{{ $setting->id }}"
                                                            @checked($setting->value)
                                                            title="{{ $setting->value ? 'Enabled – click to disable' : 'Disabled – click to enable' }}"
                                                        >
                                                    </div>
                                                    <span id="badge-{{ $setting->id }}"
                                                        class="badge badge-light-{{ $setting->value ? 'success' : 'danger' }} fs-7 w-75px">
                                                        {{ $setting->value ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-6">No settings found.</td>
                                        </tr>
                                    @endforelse
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
            document.querySelectorAll('.setting-toggle').forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    const url = this.dataset.url;
                    const id = this.dataset.id;
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
                            el.checked = data.enabled;
                            el.title = data.enabled ? 'Enabled – click to disable' : 'Disabled – click to enable';

                            const badge = document.getElementById('badge-' + id);
                            badge.textContent = data.enabled ? 'Enabled' : 'Disabled';
                            badge.className = 'badge fs-7 w-75px badge-light-' + (data.enabled ? 'success' : 'danger');

                            window.adminNotify(
                                data.enabled ? 'success' : 'info',
                                data.label + (data.enabled ? ' enabled.' : ' disabled.')
                            );
                        })
                        .catch(function () {
                            el.checked = !el.checked;
                            window.adminNotify('error', 'Failed to update setting. Please try again.');
                        })
                        .finally(function () { el.disabled = false; });
                });
            });
        });
    </script>
@endsection
