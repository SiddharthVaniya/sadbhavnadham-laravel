<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'No campaign donations for this period yet.' },
});

const brandPoints = [
    { from: '#0f7f87', to: '#1f2a44' },
    { from: '#f59f44', to: '#ffb347' },
    { from: '#16a394', to: '#0f7f87' },
    { from: '#334155', to: '#1f2a44' },
    { from: '#e8943a', to: '#f5bf68' },
    { from: '#64748b', to: '#94a3b8' },
];

const width = 640;
const height = 240;
const padding = { top: 16, right: 16, bottom: 40, left: 52 };

const maxDonations = computed(() => Math.max(...props.points.map((point) => Number(point.donations) || 0), 1));
const maxRevenue = computed(() => Math.max(...props.points.map((point) => Number(point.revenue) || 0), 1));

const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const xForValue = (value) => padding.left + ((Number(value) || 0) / maxDonations.value) * plotWidth;
const yForValue = (value) => padding.top + plotHeight - ((Number(value) || 0) / maxRevenue.value) * plotHeight;

const plottedPoints = computed(() => props.points.map((point, index) => {
    const theme = brandPoints[index % brandPoints.length];

    return {
        ...point,
        x: xForValue(point.donations),
        y: yForValue(point.revenue),
        gradientId: `scatter-${index}`,
        colorFrom: theme.from,
        colorTo: theme.to,
    };
}));

const xTicks = computed(() => {
    const steps = 4;
    const ticks = [];

    for (let i = 0; i <= steps; i += 1) {
        const value = Math.round((maxDonations.value / steps) * i);
        ticks.push({
            value,
            x: xForValue(value),
            label: String(value),
        });
    }

    return ticks;
});

const yTicks = computed(() => {
    const steps = 4;
    const ticks = [];

    for (let i = 0; i <= steps; i += 1) {
        const value = (maxRevenue.value / steps) * i;
        ticks.push({
            value,
            y: yForValue(value),
            label: `₹ ${Math.round(value).toLocaleString('en-IN')}`,
        });
    }

    return ticks.reverse();
});
</script>

<template>
    <div v-if="points.length">
        <svg :viewBox="`0 0 ${width} ${height}`" class="h-60 w-full">
            <defs>
                <radialGradient
                    v-for="point in plottedPoints"
                    :id="point.gradientId"
                    :key="point.gradientId"
                    cx="30%"
                    cy="30%"
                    r="70%"
                >
                    <stop offset="0%" :stop-color="point.colorFrom" />
                    <stop offset="100%" :stop-color="point.colorTo" />
                </radialGradient>
            </defs>
            <line
                v-for="tick in yTicks"
                :key="`y-${tick.label}`"
                :x1="padding.left"
                :x2="width - padding.right"
                :y1="tick.y"
                :y2="tick.y"
                stroke="#e4e4e7"
                stroke-width="1"
            />
            <text
                v-for="tick in yTicks"
                :key="`y-label-${tick.label}`"
                :x="padding.left - 8"
                :y="tick.y + 4"
                text-anchor="end"
                class="fill-muted-foreground text-[10px]"
            >
                {{ tick.label }}
            </text>

            <line
                v-for="tick in xTicks"
                :key="`x-${tick.label}`"
                :x1="tick.x"
                :x2="tick.x"
                :y1="padding.top"
                :y2="height - padding.bottom"
                stroke="#f4f4f5"
                stroke-width="1"
            />
            <text
                v-for="tick in xTicks"
                :key="`x-label-${tick.label}`"
                :x="tick.x"
                :y="height - 14"
                text-anchor="middle"
                class="fill-muted-foreground text-[10px]"
            >
                {{ tick.label }}
            </text>

            <circle
                v-for="point in plottedPoints"
                :key="point.label"
                :cx="point.x"
                :cy="point.y"
                r="7"
                :fill="`url(#${point.gradientId})`"
                stroke="#ffffff"
                stroke-width="1.5"
            >
                <title>
                    {{ point.label }} · {{ point.donations }} donations · ₹ {{ Number(point.revenue || 0).toLocaleString('en-IN') }}
                </title>
            </circle>
        </svg>

        <div class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span
                v-for="point in plottedPoints"
                :key="`${point.label}-legend`"
                class="inline-flex items-center gap-1.5"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :style="{ background: `linear-gradient(135deg, ${point.colorFrom}, ${point.colorTo})` }"
                />
                {{ point.label }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
            <span>X axis: Donations count</span>
            <span>Y axis: Revenue (₹)</span>
        </div>
    </div>
    <p v-else class="py-10 text-center text-sm text-muted-foreground">{{ emptyMessage }}</p>
</template>
