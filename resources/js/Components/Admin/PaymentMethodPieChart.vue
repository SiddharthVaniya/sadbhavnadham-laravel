<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    palette: {
        type: Array,
        default: () => ['#18181b', '#f59e0b', '#10b981', '#6366f1', '#ec4899', '#0ea5e9'],
    },
    centerLabel: { type: String, default: 'methods' },
});

const segments = computed(() => {
    let offset = 0;

    return props.items.map((item, index) => {
        const slice = {
            ...item,
            color: props.palette[index % props.palette.length],
            offset,
        };
        offset += Number(item.percentage) || 0;

        return slice;
    });
});

const pieBackground = computed(() => {
    if (! props.items.length) {
        return null;
    }

    let cursor = 0;
    const stops = segments.value.map((segment) => {
        const end = cursor + (Number(segment.percentage) || 0);
        const stop = `${segment.color} ${cursor}% ${end}%`;
        cursor = end;

        return stop;
    });

    return `conic-gradient(from -90deg, ${stops.join(', ')})`;
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
</script>

<template>
    <div v-if="items.length" class="flex flex-col items-center gap-3 sm:flex-row sm:items-center">
        <div class="relative h-28 w-28 shrink-0">
            <div class="h-full w-full rounded-full" :style="{ background: pieBackground }" />
            <div class="absolute inset-[22%] flex items-center justify-center rounded-full bg-card text-center">
                <span class="text-[10px] font-medium leading-tight text-muted-foreground">{{ items.length }}<br>{{ centerLabel }}</span>
            </div>
        </div>
        <ul class="w-full flex-1 space-y-1.5 text-sm">
            <li v-for="(item, index) in segments" :key="`${item.name}-${index}`" class="flex items-center justify-between gap-2">
                <span class="inline-flex min-w-0 items-center gap-2 text-muted-foreground">
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: palette[index % palette.length] }"
                    />
                    <span class="truncate" :title="item.name">{{ item.name }}</span>
                </span>
                <span class="shrink-0 text-right text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">{{ item.percentage }}%</span>
                    · {{ formatMoney(item.amount) }}
                </span>
            </li>
        </ul>
    </div>
    <p v-else class="py-6 text-center text-sm text-muted-foreground">No breakdown for current filters.</p>
</template>
