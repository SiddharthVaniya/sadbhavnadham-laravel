@php
    $donorPortal = $donorPortal ?? ['signed_in' => false, 'display_name' => null, 'profile' => null];
    $website = $branding['urls']['website'] ?: 'https://sadbhavnadham.org';
    $headerNav = $branding['headerNav'] ?? [];
    $donorDisplayName = $donorPortal['display_name'] ?? 'Donor';
    $donorInitial = mb_strtoupper(mb_substr(trim((string) $donorDisplayName) ?: 'D', 0, 1));
@endphp

<header class="site-header">
    <div class="container site-header__inner">
        <a href="{{ $website }}" class="site-header__logo">
            <img src="{{ $branding['logoPublicUrl'] }}" alt="{{ $branding['name'] }}">
        </a>

        <nav class="site-header__nav" aria-label="Primary">
            <ul class="site-header__menu">
                @foreach ($headerNav as $item)
                    <li @class(['site-header__item', 'has-dropdown' => ($item['children'] ?? []) !== []])>
                        <a href="{{ $item['url'] }}" class="site-header__link" @if (($item['children'] ?? []) !== []) aria-haspopup="true" @endif>
                            <span>{{ $item['label'] }}</span>
                            @if (($item['children'] ?? []) !== [])
                                <svg class="site-header__chevron" viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1.5 6 6.5 11 1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @endif
                        </a>
                        @if (($item['children'] ?? []) !== [])
                            <ul class="site-header__dropdown">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="site-header__actions">
            <div class="donor-auth" data-donor-auth>
                <button
                    type="button"
                    class="site-header__signin"
                    data-donor-signin
                    title="Sign in"
                    aria-label="Sign in"
                    @if ($donorPortal['signed_in']) hidden @endif
                >
                    <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                        <path d="M12 12a4.25 4.25 0 1 0 0-8.5 4.25 4.25 0 0 0 0 8.5Z" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M4.5 19.25c1.7-3.1 4.35-4.75 7.5-4.75s5.8 1.65 7.5 4.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span>Sign in</span>
                </button>

                <div
                    class="donor-auth__signed"
                    data-donor-signed
                    @unless ($donorPortal['signed_in']) hidden @endunless
                >
                    <a href="{{ route('donate.portal.index') }}" class="donor-auth__chip" title="My donations">
                        <span class="donor-auth__avatar" data-donor-initial>{{ $donorInitial }}</span>
                        <span class="donor-auth__hello" data-donor-hello>{{ $donorDisplayName }}</span>
                    </a>
                    <a
                        href="{{ route('donate.portal.index') }}"
                        class="site-header__history"
                        title="My donations"
                        aria-label="My donations"
                        data-donor-history
                    >
                        <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                            <path d="M7 4.75h10A2.25 2.25 0 0 1 19.25 7v12.5L12 16.25 4.75 19.5V7A2.25 2.25 0 0 1 7 4.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <button
                        type="button"
                        class="site-header__signout"
                        data-donor-signout
                        aria-label="Sign out"
                        title="Sign out"
                    >
                        <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                            <path d="M10 7V6.5A2.5 2.5 0 0 1 12.5 4h5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 10 17.5V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M15 12H4m0 0 2.75-2.75M4 12l2.75 2.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>

            <a href="{{ route('donate.index') }}" class="site-header__donate" title="Donate Now" aria-label="Donate Now">
                <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                    <path d="M12 20.5s-7-4.35-7-9.15A3.85 3.85 0 0 1 12 8.2a3.85 3.85 0 0 1 7 3.15c0 4.8-7 9.15-7 9.15Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span>Donate Now</span>
            </a>
            <button
                type="button"
                class="site-header__toggle"
                aria-expanded="false"
                aria-controls="site-header-mobile"
                aria-label="Open menu"
                data-header-toggle
            >
                <span class="site-header__toggle-bar"></span>
                <span class="site-header__toggle-bar"></span>
                <span class="site-header__toggle-bar"></span>
            </button>
        </div>
    </div>

    <div id="site-header-mobile" class="site-header__mobile" hidden data-header-mobile>
        <ul class="site-header__mobile-menu">
            @foreach ($headerNav as $item)
                <li>
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @if (($item['children'] ?? []) !== [])
                        <ul>
                            @foreach ($item['children'] as $child)
                                <li><a href="{{ $child['url'] }}">{{ $child['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
            <li class="site-header__mobile-auth">
                <button
                    type="button"
                    class="site-header__signin site-header__signin--block"
                    data-donor-signin
                    @if ($donorPortal['signed_in']) hidden @endif
                >
                    <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                        <path d="M12 12a4.25 4.25 0 1 0 0-8.5 4.25 4.25 0 0 0 0 8.5Z" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M4.5 19.25c1.7-3.1 4.35-4.75 7.5-4.75s5.8 1.65 7.5 4.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span>Sign in</span>
                </button>
                <div
                    class="donor-auth__signed donor-auth__signed--mobile"
                    data-donor-signed
                    @unless ($donorPortal['signed_in']) hidden @endunless
                >
                    <a href="{{ route('donate.portal.index') }}" class="donor-auth__chip">
                        <span class="donor-auth__avatar" data-donor-initial>{{ $donorInitial }}</span>
                        <span data-donor-hello>{{ $donorDisplayName }}</span>
                    </a>
                    <a href="{{ route('donate.portal.index') }}" class="site-header__signout site-header__signout--text" data-donor-history>
                        <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                            <path d="M7 4.75h10A2.25 2.25 0 0 1 19.25 7v12.5L12 16.25 4.75 19.5V7A2.25 2.25 0 0 1 7 4.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                        <span>My donations</span>
                    </a>
                    <button type="button" class="site-header__signout site-header__signout--text" data-donor-signout>
                        <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                            <path d="M10 7V6.5A2.5 2.5 0 0 1 12.5 4h5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 10 17.5V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M15 12H4m0 0 2.75-2.75M4 12l2.75 2.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Sign out</span>
                    </button>
                </div>
            </li>
            <li>
                <a href="{{ route('donate.index') }}" class="site-header__donate site-header__donate--block">
                    <svg class="site-header__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                        <path d="M12 20.5s-7-4.35-7-9.15A3.85 3.85 0 0 1 12 8.2a3.85 3.85 0 0 1 7 3.15c0 4.8-7 9.15-7 9.15Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
                    <span>Donate Now</span>
                </a>
            </li>
        </ul>
    </div>
</header>

@once
    @push('scripts')
        <script>
            (function () {
                const toggle = document.querySelector('[data-header-toggle]');
                const panel = document.querySelector('[data-header-mobile]');
                if (!toggle || !panel) {
                    return;
                }

                toggle.addEventListener('click', () => {
                    const open = toggle.getAttribute('aria-expanded') === 'true';
                    const nextOpen = !open;
                    toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                    toggle.setAttribute('aria-label', nextOpen ? 'Close menu' : 'Open menu');
                    panel.hidden = !nextOpen;
                    document.body.classList.toggle('site-header-open', nextOpen);
                });
            })();
        </script>
    @endpush
@endonce
