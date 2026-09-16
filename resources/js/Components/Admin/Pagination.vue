<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    links: { type: Array, required: true },
    meta: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const shouldShow = computed(() => {
    if ((props.meta?.total ?? 0) <= 1) {
        return false;
    }

    if (props.links.length > 3) {
        return true;
    }

    return (props.meta?.total ?? 0) > (props.meta?.to ?? 0);
});
</script>

<template>
    <div
        v-if="shouldShow"
        class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
        :class="compact ? 'mt-4' : ''"
    >
        <p v-if="meta" class="text-xs text-muted-foreground sm:text-sm">
            Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
        </p>
        <div class="flex flex-wrap gap-1">
            <template v-for="(link, index) in links" :key="index">
                <Button
                    v-if="! link.url"
                    variant="ghost"
                    size="sm"
                    disabled
                    as-child
                >
                    <span v-html="link.label" />
                </Button>
                <Button
                    v-else
                    as-child
                    size="sm"
                    :variant="link.active ? 'default' : 'ghost'"
                >
                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>
</template>
