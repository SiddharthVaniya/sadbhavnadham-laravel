<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $branding['shortName'] }} · Admin Login</title>
    <link rel="shortcut icon" href="{{ $branding['faviconUrl'] }}"/>
    @if (file_exists(public_path('vite/manifest.json')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    @endif
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased">
@php
    $loginLogo = $branding['logoUrl'] ?: $branding['logoPublicUrl'];
@endphp
    <div class="flex min-h-screen">
        <aside class="relative hidden w-[44%] overflow-hidden bg-zinc-900 lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12 xl:px-16">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.12),transparent_55%)]"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-white/5"></div>

            <div class="relative space-y-10">
                <a href="{{ $branding['urls']['website'] ?? url('/') }}" class="inline-flex">
                    <span class="inline-flex items-center rounded-2xl bg-white px-4 py-3 shadow-sm">
                        <img
                            src="{{ $loginLogo }}"
                            alt="{{ $branding['shortName'] }}"
                            class="h-10 w-auto max-w-[220px] object-contain"
                            onerror="this.closest('span').innerHTML='<span class=\'text-lg font-semibold text-zinc-900\'>{{ addslashes($branding['shortName']) }}</span>'"
                        >
                    </span>
                </a>
                <div class="max-w-md space-y-4">
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-zinc-400">Admin portal</p>
                    <h1 class="text-4xl font-semibold leading-tight tracking-tight text-white xl:text-5xl">
                        Welcome back
                    </h1>
                    <p class="text-base leading-relaxed text-zinc-300">
                        {{ $branding['tagline'] ?: 'Sign in to manage donations, causes, and reports.' }}
                    </p>
                </div>
            </div>

            <p class="relative text-sm text-zinc-400">{{ $branding['name'] }}</p>
        </aside>

        <main class="flex flex-1 flex-col justify-center px-6 py-10 sm:px-10 lg:px-16 xl:px-24">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 space-y-3 lg:mb-10">
                    <a href="{{ $branding['urls']['website'] ?? url('/') }}" class="inline-flex lg:hidden">
                        <img
                            src="{{ $loginLogo }}"
                            alt="{{ $branding['shortName'] }}"
                            class="h-10 w-auto max-w-[220px] object-contain"
                            onerror="this.outerHTML='<span class=\'text-xl font-semibold text-zinc-900\'>{{ addslashes($branding['shortName']) }}</span>'"
                        >
                    </a>
                    <h2 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl">Sign in</h2>
                    <p class="text-sm text-zinc-500">Use your admin email and password to continue.</p>
                </div>

                @if ($errors->any())
                    <div
                        role="alert"
                        aria-live="polite"
                        class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                    >
                        <p class="font-medium">Could not sign you in</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-rose-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-5" id="admin-login-form" novalidate>
                    @csrf
                    <input type="hidden" name="fingerprint" id="fingerprint" value="{{ old('fingerprint') }}">
                    <div class="space-y-1.5">
                        <label for="email" class="text-sm font-medium text-zinc-700">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            required
                            autofocus
                            autocomplete="username"
                            @class([
                                'block w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2',
                                'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' => $errors->has('email'),
                                'border-zinc-300 focus:border-zinc-500 focus:ring-zinc-500/20' => ! $errors->has('email'),
                            ])
                        >
                        @error('email')
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="password" class="text-sm font-medium text-zinc-700">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                                @class([
                                    'block w-full rounded-lg border bg-white px-3 py-2.5 pr-11 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2',
                                    'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' => $errors->has('password'),
                                    'border-zinc-300 focus:border-zinc-500 focus:ring-zinc-500/20' => ! $errors->has('password'),
                                ])
                            >
                            <button
                                type="button"
                                id="toggle-password"
                                class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-zinc-500 transition hover:text-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('fingerprint')
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        id="admin-login-submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 disabled:opacity-60"
                    >
                        Sign in
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script src="{{ \App\Support\PublicAsset::url('js/fingerprintjs.min.js') }}"></script>
    <script>
        (() => {
            // Avoid submitting a cached login page with an expired CSRF token (bfcache / back button).
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.location.reload();
                }
            });

            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('toggle-password');
            const fingerprintInput = document.getElementById('fingerprint');

            if (passwordInput && toggleButton) {
                const eyeOpen = toggleButton.querySelector('[data-eye-open]');
                const eyeClosed = toggleButton.querySelector('[data-eye-closed]');

                toggleButton.addEventListener('click', () => {
                    const isVisible = passwordInput.type === 'text';
                    passwordInput.type = isVisible ? 'password' : 'text';
                    const nowVisible = ! isVisible;
                    toggleButton.setAttribute('aria-pressed', nowVisible ? 'true' : 'false');
                    toggleButton.setAttribute('aria-label', nowVisible ? 'Hide password' : 'Show password');
                    eyeOpen.classList.toggle('hidden', nowVisible);
                    eyeClosed.classList.toggle('hidden', ! nowVisible);
                });
            }

            if (! fingerprintInput || typeof FingerprintJS === 'undefined') {
                return;
            }

            // Used only for super_admin device lock on the server.
            FingerprintJS.load()
                .then((agent) => agent.get())
                .then((result) => {
                    fingerprintInput.value = result.visitorId || '';
                    console.log('FingerprintJS visitorId:', fingerprintInput.value);
                })
                .catch((error) => {
                    fingerprintInput.value = '';
                    console.log('FingerprintJS error:', error);
                });
        })();
    </script>
</body>
</html>
