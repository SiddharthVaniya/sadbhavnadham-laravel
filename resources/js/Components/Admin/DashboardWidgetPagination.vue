<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    links: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    scrollTarget: { type: String, default: '' },
    only: { type: Array, default: () => [] },
    preserveState: { type: Boolean, default: false },
    preserveScroll: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const shouldShow = computed(() => {
    const total = props.meta?.total ?? 0;
    const lastPage = props.meta?.last_page ?? 1;

    return total > 0 && lastPage > 1;
});

const prevLink = computed(() => props.links.find((link) => link.label.includes('Previous')) ?? null);
const nextLink = computed(() => props.links.find((link) => link.label.includes('Next')) ?? null);
</script>

<template>
    <div
        v-if="shouldShow"
        :class="compact
            ? 'mt-3 flex flex-col gap-2 border-t border-border pt-3'
            : 'mt-4 flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between'"
    >
        <p v-if="meta" :class="compact ? 'text-xs text-muted-foreground' : 'text-xs text-muted-foreground sm:text-sm'">
            Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
        </p>
        <div class="flex items-center gap-2">
            <Button v-if="prevLink?.url" as-child variant="outline" size="sm">
                <Link
                    :href="prevLink.url"
                    :only="only.length ? only : undefined"
                    :preserve-state="preserveState"
                    :preserve-scroll="preserveScroll"
                >Previous</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Previous</Button>

            <span :class="compact ? 'px-1 text-xs text-muted-foreground' : 'px-1 text-xs text-muted-foreground sm:text-sm'">
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>

            <Button v-if="nextLink?.url" as-child variant="outline" size="sm">
                <Link
                    :href="nextLink.url"
                    :only="only.length ? only : undefined"
                    :preserve-state="preserveState"
                    :preserve-scroll="preserveScroll"
                >Next</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Next</Button>
        </div>
    </div>
</template>
