@props([
    'title',
    'subtitle' => null,
])

<div class="admin-page-head mb-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="fs-2 fw-bold mb-1">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mb-0 opacity-90 fs-6">{{ $subtitle }}</p>
            @endif
            @if (isset($breadcrumb))
                <div class="mt-2">{{ $breadcrumb }}</div>
            @endif
        </div>
        @if (isset($actions))
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endif
    </div>
</div>
