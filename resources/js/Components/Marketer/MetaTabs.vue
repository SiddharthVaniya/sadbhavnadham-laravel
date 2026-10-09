<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { metaMarketerTabHref } from '@/utils/metaFilterQuery';

const props = defineProps({
    current: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
});

const tabs = computed(() => [
    { key: 'overview', href: metaMarketerTabHref('/marketer/meta', props.filters), label: 'Overview' },
    { key: 'analytics', href: metaMarketerTabHref('/marketer/meta/analytics', props.filters), label: 'Analytics' },
    { key: 'ads', href: metaMarketerTabHref('/marketer/meta/ads', props.filters), label: 'Your ads' },
]);
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
