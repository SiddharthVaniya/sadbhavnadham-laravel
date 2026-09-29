<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    current: { type: String, required: true },
});

const tabs = [
    { key: 'month', href: '/admin/marketers', label: 'This month' },
    { key: 'today', href: '/admin/marketers/today', label: 'Today limit' },
    { key: 'history', href: '/admin/marketers/history', label: 'Spending history' },
];
</script>

<template>
    <nav class="mb-4 flex flex-wrap gap-2">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="rounded-lg border px-3 py-1.5 text-sm"
            :class="current === tab.key
                ? 'border-foreground bg-foreground text-background'
                : 'border-border bg-card text-foreground'"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
