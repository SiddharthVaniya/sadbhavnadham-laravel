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

const brandGradients = [
    { id: 'brand-teal', from: '#0f7f87', to: '#1f2a44' },
    { id: 'brand-orange', from: '#f59f44', to: '#ffb347' },
    { id: 'brand-mint', from: '#16a394', to: '#0f7f87' },
    { id: 'brand-navy', from: '#334155', to: '#1f2a44' },
    { id: 'brand-gold', from: '#e8943a', to: '#f5bf68' },
    { id: 'brand-soft', from: '#cbd5e1', to: '#e2e8f0' },
];

const radius = 42;
const stroke = 13;
const size = 112;
const center = size / 2;
const circumference = 2 * Math.PI * radius;

const segments = computed(() => {
    let rotation = -90;

    return props.items.map((item, index) => {
        const percentage = Math.max(0, Number(item.percentage) || 0);
        const gradient = brandGradients[index % brandGradients.length];
        const dash = (percentage / 100) * circumference;
        const segment = {
            ...item,
            percentage,
            gradientId: item.gradientId || gradient.id,
            gradientFrom: item.color || gradient.from,
            gradientTo: item.colorTo || gradient.to,
            dash,
            gap: Math.max(0, circumference - dash),
            rotation,
        };

        rotation += (percentage / 100) * 360;

        return segment;
    });
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
    <div v-if="items.length" class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <div class="relative mx-auto shrink-0 sm:mx-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg :viewBox="`0 0 ${size} ${size}`" class="h-full w-full drop-shadow-sm">
                <defs>
                    <linearGradient
                        v-for="segment in segments"
                        :id="segment.gradientId"
                        :key="segment.gradientId"
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                    >
                        <stop offset="0%" :stop-color="segment.gradientFrom" />
                        <stop offset="100%" :stop-color="segment.gradientTo" />
                    </linearGradient>
                    <filter id="pie-glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#0f7f87" flood-opacity="0.18" />
                    </filter>
                </defs>

                <circle
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    stroke="#eef2f7"
                    :stroke-width="stroke"
                />

                <circle
                    v-for="segment in segments"
                    :key="`${segment.name}-${segment.rotation}`"
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    :stroke="`url(#${segment.gradientId})`"
                    :stroke-width="stroke"
                    stroke-linecap="round"
                    :stroke-dasharray="`${segment.dash} ${segment.gap}`"
                    :transform="`rotate(${segment.rotation} ${center} ${center})`"
                    filter="url(#pie-glow)"
                />
            </svg>

            <div
                class="absolute inset-[26%] flex flex-col items-center justify-center rounded-full border border-white/80 bg-white/95 text-center shadow-inner backdrop-blur-sm"
            >
                <span v-if="centerValue" class="text-sm font-semibold leading-tight text-[#1f2a44]">{{ centerValue }}</span>
                <span class="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-[#0f7f87]">{{ centerLabel }}</span>
            </div>
        </div>

        <ul class="w-full flex-1 space-y-3">
            <li
                v-for="(item, index) in segments"
                :key="item.name"
                class="rounded-xl border border-border/70 bg-gradient-to-r from-white to-slate-50/80 px-3 py-2.5"
            >
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <span class="inline-flex min-w-0 items-center gap-2 text-sm text-foreground">
                        <span
                            class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
                            :style="{ background: `linear-gradient(135deg, ${item.gradientFrom}, ${item.gradientTo})` }"
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
                            background: `linear-gradient(90deg, ${brandGradients[index % brandGradients.length].from}, ${brandGradients[index % brandGradients.length].to})`,
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
