<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $branding['shortName'] }} · @yield('title')</title>
    <link rel="shortcut icon" href="{{ $branding['faviconUrl'] }}"/>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    @endif
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet">
    @yield('customcss')
</head>
<body class="bg-zinc-50 font-sans text-zinc-900 antialiased">
    <div class="flex min-h-screen">
        @include('admin.layouts.partials.minimal-sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center justify-between border-b border-zinc-200 bg-white px-6">
                <h1 class="text-sm font-semibold">@yield('title')</h1>
            </header>
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    @flasher_render
    @yield('customjs')
    @stack('scripts')
</body>
</html>
