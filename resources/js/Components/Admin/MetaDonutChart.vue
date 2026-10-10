<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'No data to chart.' },
    valueKey: { type: String, default: 'spend' },
    valueFormat: { type: String, default: 'money' },
    /** When true, shows a center cutout with total; when false, full pie. */
    donut: { type: Boolean, default: true },
});

const colors = ['#2563eb', '#ea580c', '#7c3aed', '#059669', '#db2777', '#0f766e', '#ca8a04', '#94a3b8'];

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const formatValue = (value) => {
    if (props.valueFormat === 'number') {
        return Number(value || 0).toLocaleString('en-IN');
    }

    return formatMoney(value);
};

const itemValue = (item) => Number(item[props.valueKey] ?? item.spend ?? item.count ?? 0);

const slices = computed(() => {
    const total = props.items.reduce((sum, item) => sum + itemValue(item), 0);

    if (total <= 0) {
        return [];
    }

    return props.items
        .map((item, index) => {
            const value = itemValue(item);

            if (value <= 0) {
                return null;
            }

            const fraction = value / total;

            return {
                name: item.name,
                value,
                percentage: item.percentage ?? Math.round(fraction * 1000) / 10,
                color: colors[index % colors.length],
            };
        })
        .filter(Boolean);
});

const totalValue = computed(() => slices.value.reduce((sum, row) => sum + row.value, 0));

const pieBackground = computed(() => {
    if (! slices.value.length) {
        return null;
    }

    const total = totalValue.value;

    if (total <= 0) {
        return null;
    }

    let cursor = 0;
    const stops = slices.value.map((slice) => {
        const share = (slice.value / total) * 100;
        const end = cursor + share;
        const stop = `${slice.color} ${cursor}% ${end}%`;
        cursor = end;

        return stop;
    });

    return `conic-gradient(from -90deg, ${stops.join(', ')})`;
});
</script>

<template>
    <div v-if="slices.length && pieBackground" class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <div class="relative h-40 w-40 shrink-0">
            <div
                class="h-full w-full rounded-full shadow-sm ring-1 ring-black/5"
                :style="{ background: pieBackground }"
            />
            <div
                v-if="donut"
                class="pointer-events-none absolute inset-[26%] flex flex-col items-center justify-center rounded-full bg-card text-center shadow-inner"
            >
                <p class="text-xs text-muted-foreground">Total</p>
                <p class="text-sm font-semibold tabular-nums">{{ formatValue(totalValue) }}</p>
            </div>
        </div>
        <ul class="min-w-0 flex-1 space-y-2">
            <li
                v-for="(slice, index) in slices"
                :key="`${slice.name}-${index}`"
                class="chart-fade-up flex items-start gap-2 text-sm"
                :style="{ animationDelay: `${index * 0.05}s` }"
            >
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: slice.color }" />
                <span class="min-w-0">
                    <span class="block truncate font-medium" :title="slice.name">{{ slice.name }}</span>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ formatValue(slice.value) }} · {{ slice.percentage }}%
                    </span>
                </span>
            </li>
        </ul>
    </div>
    <p v-else class="flex min-h-[200px] items-center justify-center text-center text-sm text-muted-foreground">
        {{ emptyMessage }}
    </p>
</template>
