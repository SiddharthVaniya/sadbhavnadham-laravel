<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, PanelLeftClose, PanelLeftOpen, X } from '@lucide/vue';
import FlashAlert from '@/Components/Admin/FlashAlert.vue';
import ValidationAlert from '@/Components/Admin/ValidationAlert.vue';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Separator } from '@/Components/ui/separator';

const SIDEBAR_STORAGE_KEY = 'admin-sidebar-collapsed';

const page = usePage();

const branding = computed(() => page.props.branding ?? {});
const user = computed(() => page.props.auth.user);
const navigation = computed(() => page.props.navigation ?? []);
const currentRoute = computed(() => page.props.currentRoute ?? '');
const portalHome = computed(() => page.props.portal?.home || '/admin');
const portalLabel = computed(() => page.props.portal?.label || 'Admin');
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);

const isActive = (routeName) => {
    if (! routeName) {
        return false;
    }

    if (currentRoute.value === routeName) {
        return true;
    }

    const prefix = routeName.replace('.index', '.');

    return currentRoute.value?.startsWith(prefix) ?? false;
};

const isItemOrChildActive = (item) => {
    if (isActive(item.route)) {
        return true;
    }

    return item.children?.some((child) => currentRoute.value === child.route) ?? false;
};

const logout = () => {
    router.post('/admin/logout');
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const persistCollapsed = (collapsed) => {
    sidebarCollapsed.value = collapsed;

    try {
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        // Ignore storage failures (private mode, disabled storage).
    }
};

const hideDesktopSidebar = () => persistCollapsed(true);
const showDesktopSidebar = () => persistCollapsed(false);

onMounted(() => {
    try {
        sidebarCollapsed.value = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        sidebarCollapsed.value = false;
    }
});

watch(currentRoute, () => {
    sidebarOpen.value = false;
});
</script>

<template>
    <div class="flex min-h-screen bg-muted/40">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-foreground/40 lg:hidden"
            @click="closeSidebar"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex h-screen w-[min(280px,88vw)] flex-col border-r border-border bg-sidebar text-sidebar-foreground transition-transform duration-300 ease-in-out motion-reduce:transition-none lg:w-[240px]"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:-translate-x-full' : 'lg:translate-x-0',
            ]"
        >
            <div class="flex h-[72px] items-center justify-between gap-2 border-b border-sidebar-border px-3 lg:px-4">
                <Link :href="portalHome" class="block min-w-0 flex-1" @click="closeSidebar">
                    <span class="mx-auto flex max-w-full items-center justify-center rounded-xl bg-white px-3 py-2 shadow-sm">
                        <img
                            :src="branding.logoUrl || branding.logoPublicUrl || '/assets/img/logo/logo-black.png'"
                            :alt="branding.shortName || portalLabel"
                            class="h-10 w-auto max-w-full object-contain"
                        >
                    </span>
                </Link>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="lg:hidden"
                    aria-label="Close menu"
                    @click="closeSidebar"
                >
                    <X />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="hidden shrink-0 lg:inline-flex"
                    title="Hide sidebar"
                    aria-label="Hide sidebar"
                    aria-expanded="true"
                    @click="hideDesktopSidebar"
                >
                    <PanelLeftClose class="size-4" />
                </Button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4" :aria-label="portalLabel">
                <template v-for="item in navigation" :key="item.label">
                    <div
                        v-if="item.section"
                        class="px-3 pb-1 pt-4 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted-foreground first:pt-0"
                    >
                        {{ item.section }}
                    </div>

                    <Link
                        :href="item.href"
                        class="block rounded-lg px-3 py-2.5 text-[13px] font-medium tracking-tight transition"
                        :class="isActive(item.route)
                            ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                            : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
                        @click="closeSidebar"
                    >
                        {{ item.label }}
                    </Link>

                    <div
                        v-if="item.children?.length && isItemOrChildActive(item)"
                        class="mb-1 ml-2 space-y-0.5 border-l border-sidebar-border pl-3"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.label"
                            :href="child.href"
                            class="block rounded-md px-2.5 py-1.5 text-[12px] font-medium"
                            :class="currentRoute === child.route
                                ? 'text-sidebar-foreground'
                                : 'text-muted-foreground hover:text-sidebar-foreground'"
                            @click="closeSidebar"
                        >
                            {{ child.label }}
                        </Link>
                    </div>
                </template>
            </nav>

            <div class="shrink-0 border-t border-sidebar-border p-3">
                <div class="flex items-center gap-2.5 rounded-lg bg-sidebar-accent px-2.5 py-2">
                    <Avatar size="sm">
                        <AvatarFallback class="bg-sidebar-primary text-sidebar-primary-foreground">
                            {{ user?.initials }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13px] font-medium">{{ user?.name }}</div>
                        <div class="truncate text-[11px] text-muted-foreground">{{ user?.email }}</div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        title="Sign out"
                        aria-label="Sign out"
                        @click="logout"
                    >
                        <LogOut />
                    </Button>
                </div>
            </div>
        </aside>

        <div
            class="flex min-w-0 flex-1 flex-col transition-[margin] duration-300 ease-in-out motion-reduce:transition-none lg:min-h-screen"
            :class="sidebarCollapsed ? 'lg:ml-0' : 'lg:ml-[240px]'"
        >
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur supports-backdrop-filter:bg-background/80 sm:px-6 lg:px-8">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="lg:hidden"
                    aria-label="Open menu"
                    @click="sidebarOpen = true"
                >
                    <Menu />
                </Button>
                <Button
                    v-if="sidebarCollapsed"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="hidden shrink-0 lg:inline-flex"
                    title="Show sidebar"
                    aria-label="Show sidebar"
                    aria-expanded="false"
                    @click="showDesktopSidebar"
                >
                    <PanelLeftOpen class="size-4" />
                </Button>
                <h1 class="min-w-0 flex-1 truncate text-[15px] font-semibold tracking-tight">
                    <slot name="header" />
                </h1>
            </header>

            <Separator class="lg:hidden" />

            <main class="flex-1 overflow-x-hidden overflow-y-auto px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                <FlashAlert />
                <ValidationAlert />
                <slot />
            </main>
        </div>
    </div>
</template>