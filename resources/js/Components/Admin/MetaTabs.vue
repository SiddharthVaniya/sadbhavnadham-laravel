<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    current: { type: String, required: true },
});

const tabs = [
    { key: 'spend', href: '/admin/meta', label: 'Ads spend' },
    { key: 'accounts', href: '/admin/meta/accounts', label: 'Accounts' },
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
