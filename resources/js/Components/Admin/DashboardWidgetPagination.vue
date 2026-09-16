<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    links: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    scrollTarget: { type: String, default: '' },
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
        class="mt-4 flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <p v-if="meta" class="text-xs text-muted-foreground sm:text-sm">
            Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
        </p>
        <div class="flex items-center gap-2">
            <Button v-if="prevLink?.url" as-child variant="outline" size="sm">
                <Link :href="prevLink.url" :preserve-scroll="false">Previous</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Previous</Button>

            <span class="px-1 text-xs text-muted-foreground sm:text-sm">
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>

            <Button v-if="nextLink?.url" as-child variant="outline" size="sm">
                <Link :href="nextLink.url" :preserve-scroll="false">Next</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Next</Button>
        </div>
    </div>
</template>
