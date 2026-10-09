<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'No data to chart.' },
    valueKey: { type: String, default: 'spend' },
    valueFormat: { type: String, default: 'money' },
});

const colors = ['#2563eb', '#ea580c', '#7c3aed', '#059669', '#db2777', '#0f766e', '#ca8a04', '#94a3b8'];

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const formatValue = (value) => {
    if (props.valueFormat === 'number') {
        return Number(value || 0).toLocaleString('en-IN');
    }

    return formatMoney(value);
};

const segments = computed(() => {
    const total = props.items.reduce(
        (sum, item) => sum + Number(item[props.valueKey] ?? item.spend ?? item.count ?? 0),
        0,
    );

    if (total <= 0) {
        return [];
    }

    let offset = 0;
    const radius = 42;
    const circumference = 2 * Math.PI * radius;

    return props.items.map((item, index) => {
        const value = Number(item[props.valueKey] ?? item.spend ?? item.count ?? 0);
        const fraction = value / total;
        const dash = fraction * circumference;
        const segment = {
            name: item.name,
            value,
            percentage: item.percentage ?? Math.round(fraction * 1000) / 10,
            color: colors[index % colors.length],
            dasharray: `${dash} ${circumference - dash}`,
            dashoffset: -offset,
        };
        offset += dash;

        return segment;
    });
});

const totalSpend = computed(() => segments.value.reduce((sum, row) => sum + row.value, 0));
</script>

<template>
    <div v-if="segments.length" class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <div class="relative shrink-0">
            <svg viewBox="0 0 100 100" class="h-40 w-40 -rotate-90">
                <circle cx="50" cy="50" r="42" fill="none" stroke="#eef1f4" stroke-width="14" />
                <circle
                    v-for="(seg, index) in segments"
                    :key="seg.name"
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    :stroke="seg.color"
                    stroke-width="14"
                    stroke-linecap="butt"
                    :stroke-dasharray="seg.dasharray"
                    :stroke-dashoffset="seg.dashoffset"
                    class="chart-line-path"
                    :style="{ animationDelay: `${index * 0.08}s` }"
                />
            </svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <p class="text-xs text-muted-foreground">Total</p>
                <p class="text-sm font-semibold tabular-nums">{{ formatValue(totalSpend) }}</p>
            </div>
        </div>
        <ul class="min-w-0 flex-1 space-y-2">
            <li
                v-for="(seg, index) in segments"
                :key="seg.name"
                class="chart-fade-up flex items-start gap-2 text-sm"
                :style="{ animationDelay: `${index * 0.05}s` }"
            >
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: seg.color }" />
                <span class="min-w-0">
                    <span class="block truncate font-medium" :title="seg.name">{{ seg.name }}</span>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ formatValue(seg.value) }} · {{ seg.percentage }}%
                    </span>
                </span>
            </li>
        </ul>
    </div>
    <p v-else class="flex min-h-[200px] items-center justify-center text-center text-sm text-muted-foreground">
        {{ emptyMessage }}
    </p>
</template>
