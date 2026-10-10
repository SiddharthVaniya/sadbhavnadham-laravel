<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    centerLabel: { type: String, default: '' },
    centerValue: { type: String, default: '' },
    emptyMessage: { type: String, default: 'No breakdown for this period yet.' },
    valueSuffix: { type: String, default: '' },
    money: { type: Boolean, default: false },
});

const brandColors = ['#0f7f87', '#f59f44', '#16a394', '#334155', '#e8943a', '#cbd5e1'];

const segments = computed(() =>
    props.items.map((item, index) => ({
        ...item,
        percentage: Math.max(0, Number(item.percentage) || 0),
        color: item.color || brandColors[index % brandColors.length],
    })),
);

const pieBackground = computed(() => {
    if (! segments.value.length) {
        return null;
    }

    let cursor = 0;
    const stops = segments.value.map((segment) => {
        const end = cursor + segment.percentage;
        const stop = `${segment.color} ${cursor}% ${end}%`;
        cursor = end;

        return stop;
    });

    return `conic-gradient(from -90deg, ${stops.join(', ')})`;
});

const formatCount = (item) => {
    if (item.count == null) {
        return '';
    }

    if (props.money) {
        return `₹ ${Number(item.count).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
    }

    return `${Number(item.count).toLocaleString()}${props.valueSuffix}`;
};
</script>

<template>
    <div v-if="items.length && pieBackground" class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="relative mx-auto h-28 w-28 shrink-0 sm:mx-0">
            <div
                class="h-full w-full rounded-full shadow-sm ring-1 ring-black/5"
                :style="{ background: pieBackground }"
            />
            <div
                v-if="centerLabel || centerValue"
                class="absolute inset-[26%] flex flex-col items-center justify-center rounded-full border border-white/80 bg-white/95 text-center shadow-inner backdrop-blur-sm"
            >
                <span v-if="centerValue" class="text-sm font-semibold leading-tight text-[#1f2a44]">{{ centerValue }}</span>
                <span class="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-[#0f7f87]">{{ centerLabel }}</span>
            </div>
        </div>

        <ul class="w-full flex-1 space-y-3">
            <li
                v-for="(item, index) in segments"
                :key="`${item.name}-${index}`"
                class="rounded-xl border border-border/70 bg-gradient-to-r from-white to-slate-50/80 px-3 py-2.5"
            >
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <span class="inline-flex min-w-0 items-center gap-2 text-sm text-foreground">
                        <span
                            class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
                            :style="{ backgroundColor: item.color }"
                        />
                        <span class="truncate font-medium">{{ item.name }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-semibold text-[#1f2a44]">{{ item.percentage }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full"
                        :style="{
                            width: `${item.percentage}%`,
                            backgroundColor: item.color,
                        }"
                    />
                </div>
                <p v-if="item.count != null" class="mt-1 text-xs text-muted-foreground">
                    {{ formatCount(item) }}
                </p>
            </li>
        </ul>
    </div>
    <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ emptyMessage }}</p>
</template>
