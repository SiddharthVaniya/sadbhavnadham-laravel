<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px"
    data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    {{-- Logo --}}
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ route('admin.dashboard') }}">
            <img alt="Logo" src="{{ $branding['logoUrl'] }}" class="app-sidebar-logo-default"
                width="100%" />
            <img alt="Logo" src="{{ asset('assets/img/logo/loader.png') }}"
                class="h-40px app-sidebar-logo-minimize" />
        </a>

        {{-- Minimize toggle --}}
        <div id="kt_app_sidebar_toggle"
            class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
            data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-outline ki-black-left-line fs-3 rotate-180"></i>
        </div>
    </div>
    {{-- Menu --}}
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
            <div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true"
                data-kt-scroll-activate="true" data-kt-scroll-height="auto"
                data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
                data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px">

                <div class="menu menu-column menu-rounded menu-sub-indentation fw-semibold fs-6"
                    id="kt_app_sidebar_menu" data-kt-menu="true">

                    {{-- Dashboard --}}
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                            href="{{ route('admin.dashboard') }}">
                            <span class="menu-icon">
                                <i class="ki-outline ki-element-11 fs-2"></i>
                            </span>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </div>

                    {{-- Causes --}}
                    @can('manage causes')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.causes.*') ? 'active' : '' }}"
                                href="{{ route('admin.causes.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-flag fs-2"></i>
                                </span>
                                <span class="menu-title">Causes</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Packages --}}
                    @can('manage packages')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}"
                                href="{{ route('admin.packages.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-package fs-2"></i>
                                </span>
                                <span class="menu-title">Packages</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Aisensy Accounts --}}
                    @can('manage aisensy accounts')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.aisensy-accounts.*') ? 'active' : '' }}"
                                href="{{ route('admin.aisensy-accounts.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-abstract-26 fs-2"></i>
                                </span>
                                <span class="menu-title">Aisensy Accounts</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Donations --}}
                    @can('view donations')
                        <div data-kt-menu-trigger="click"
                            class="menu-item menu-accordion {{ request()->routeIs('admin.donations.*') ? 'show' : '' }}">
                            <span class="menu-link">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-wallet fs-2"></i>
                                </span>
                                <span class="menu-title">Donations</span>
                                <span class="menu-arrow"></span>
                            </span>
                            <div class="menu-sub menu-sub-accordion">
                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('admin.donations.index') || (request()->routeIs('admin.donations.*') && ! request()->routeIs('admin.donations.offline') && ! request()->routeIs('admin.donations.create')) ? 'active' : '' }}"
                                        href="{{ route('admin.donations.index') }}">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">All Donations</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('admin.donations.offline') ? 'active' : '' }}"
                                        href="{{ route('admin.donations.offline') }}">
                                        <span class="menu-bullet">
                                            <span class="bullet bullet-dot"></span>
                                        </span>
                                        <span class="menu-title">Offline Donations</span>
                                    </a>
                                </div>
                                @can('manage donations')
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs('admin.donations.create') ? 'active' : '' }}"
                                            href="{{ route('admin.donations.create') }}">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title">Record Offline</span>
                                        </a>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    @endcan

                    {{-- Donors --}}
                    @can('view donors')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.donors.*') ? 'active' : '' }}"
                                href="{{ route('admin.donors.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-profile-circle fs-2"></i>
                                </span>
                                <span class="menu-title">Donors</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Users --}}
                    @can('manage users')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                                href="{{ route('admin.users.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-profile-user fs-2"></i>
                                </span>
                                <span class="menu-title">Users</span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                                href="{{ route('admin.roles.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-shield-tick fs-2"></i>
                                </span>
                                <span class="menu-title">Roles</span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}"
                                href="{{ route('admin.permissions.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-key fs-2"></i>
                                </span>
                                <span class="menu-title">Permissions</span>
                            </a>
                        </div>
                    @endcan

                    {{-- Settings --}}
                    @can('manage settings')
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                                href="{{ route('admin.settings.index') }}">
                                <span class="menu-icon">
                                    <i class="ki-outline ki-setting-2 fs-2"></i>
                                </span>
                                <span class="menu-title">Settings</span>
                            </a>
                        </div>
                    @endcan

                </div>

            </div>
        </div>
    </div>
</div>
