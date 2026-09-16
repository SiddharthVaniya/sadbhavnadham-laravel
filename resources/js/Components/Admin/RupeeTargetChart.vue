<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    target: { type: Object, default: () => ({}) },
    emptyMessage: { type: String, default: 'No rupee target set yet.' },
});

const hovered = ref(null);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1).replace(/\.0$/, '')}%`;

const legendItems = computed(() => {
    if (! props.target?.goal) {
        return [];
    }

    return [
        {
            name: 'Collected',
            percentage: Number(props.target.achieved_percent) || 0,
            amount: Number(props.target.achieved) || 0,
            color: '#1A1A1A',
        },
        {
            name: 'Left',
            percentage: Number(props.target.remaining_percent) || 0,
            amount: Number(props.target.remaining) || 0,
            color: '#F5A623',
        },
    ];
});

const radius = 64;
const stroke = 16;
const size = 172;
const center = size / 2;
const circumference = 2 * Math.PI * radius;

const segments = computed(() => {
    const collected = Math.max(0, Math.min(100, legendItems.value[0]?.percentage || 0));
    const remaining = Math.max(0, Math.min(100, legendItems.value[1]?.percentage || 0));

    if (collected <= 0 && remaining <= 0) {
        return [];
    }

    if (remaining <= 0) {
        return [{
            ...legendItems.value[0],
            dash: circumference,
            gap: 0,
            rotation: -90,
        }];
    }

    let rotation = -90;

    return legendItems.value
        .filter((item) => item.percentage > 0)
        .map((item) => {
            const percentage = Math.max(0, Math.min(100, item.percentage));
            const dash = (percentage / 100) * circumference;
            const segment = {
                ...item,
                dash,
                gap: Math.max(0, circumference - dash),
                rotation,
            };

            rotation += (percentage / 100) * 360;

            return segment;
        });
});

const formatCompactMoney = (value) => {
    const amount = Number(value) || 0;

    if (amount >= 100000) {
        const lakhs = amount / 100000;

        return `₹${lakhs.toFixed(lakhs >= 10 ? 0 : 1).replace(/\.0$/, '')}L`;
    }

    if (amount >= 1000) {
        const thousands = amount / 1000;
        const digits = thousands >= 10 ? 0 : 1;

        return `₹${thousands.toFixed(digits).replace(/\.0$/, '')}k`;
    }

    return `₹${Math.round(amount)}`;
};

const centerValue = computed(() => {
    if (hovered.value === 'Collected') {
        return formatCompactMoney(props.target?.achieved);
    }

    if (hovered.value === 'Left') {
        return formatCompactMoney(props.target?.remaining);
    }

    if (props.target?.goal) {
        return formatPercent(props.target.achieved_percent);
    }

    return formatMoney(props.target?.achieved);
});

const centerLabel = computed(() => {
    if (hovered.value === 'Left') {
        return 'left';
    }

    return props.target?.goal ? 'collected' : 'lifetime';
});

const summaryLine = computed(() => {
    if (! props.target?.goal) {
        return '';
    }

    return `${formatMoney(props.target.achieved)} of ${formatMoney(props.target.goal)}`;
});
</script>

<template>
    <div v-if="legendItems.length" class="flex h-full min-h-[280px] flex-col">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex min-h-0 flex-1 items-center justify-center py-1">
                <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
                    <svg :viewBox="`0 0 ${size} ${size}`" class="h-full w-full">
                        <circle
                            :cx="center"
                            :cy="center"
                            :r="radius"
                            fill="none"
                            stroke="#eef1f4"
                            :stroke-width="stroke"
                        />
                        <circle
                            v-for="(segment, index) in segments"
                            :key="segment.name"
                            class="chart-donut-segment cursor-pointer transition-opacity duration-200"
                            :cx="center"
                            :cy="center"
                            :r="radius"
                            fill="none"
                            :stroke="segment.color"
                            :stroke-width="stroke"
                            stroke-linecap="round"
                            :stroke-dasharray="`${segment.dash} ${segment.gap}`"
                            :style="{
                                '--donut-target': `${segment.dash} ${segment.gap}`,
                                animationDelay: `${index * 0.12}s`,
                                opacity: hovered && hovered !== segment.name ? 0.28 : 1,
                            }"
                            :transform="`rotate(${segment.rotation} ${center} ${center})`"
                            @mouseenter="hovered = segment.name"
                            @mouseleave="hovered = null"
                        />
                    </svg>

                    <div class="chart-center-pop pointer-events-none absolute inset-[22%] flex flex-col items-center justify-center text-center">
                        <span class="text-[1.65rem] font-semibold leading-none tracking-tight text-[#1A1A1A]">{{ centerValue }}</span>
                        <span class="mt-1.5 text-[11px] font-medium capitalize text-muted-foreground">{{ centerLabel }}</span>
                    </div>
                </div>
            </div>

            <ul class="w-full min-w-0 space-y-4">
                <li
                    v-for="(item, index) in legendItems"
                    :key="item.name"
                    class="chart-fade-up cursor-default space-y-2 rounded-md px-1 py-0.5 transition-opacity duration-200"
                    :style="{
                        animationDelay: `${0.25 + (index * 0.08)}s`,
                        opacity: hovered && hovered !== item.name ? 0.45 : 1,
                    }"
                    @mouseenter="hovered = item.name"
                    @mouseleave="hovered = null"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="inline-flex min-w-0 items-center gap-2.5 text-sm text-muted-foreground">
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: item.color }"
                            />
                            <span>{{ item.name }}</span>
                        </span>
                        <span class="shrink-0 text-right text-sm font-semibold tabular-nums text-[#1A1A1A]">
                            {{ formatPercent(item.percentage) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-[#eef1f4]">
                            <div
                                class="chart-scale-x h-full rounded-full"
                                :style="{
                                    width: `${Math.max(item.percentage, item.percentage > 0 ? 4 : 0)}%`,
                                    backgroundColor: item.color,
                                    animationDelay: `${0.3 + (index * 0.08)}s`,
                                }"
                            />
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
                            {{ formatMoney(item.amount) }}
                        </span>
                    </div>
                </li>
            </ul>
        </div>

        <p v-if="summaryLine" class="mt-5 border-t border-border/60 pt-3 text-sm tabular-nums text-muted-foreground">
            <span class="font-medium text-foreground">{{ formatMoney(target.achieved) }}</span>
            of {{ formatMoney(target.goal) }} collected lifetime
        </p>
    </div>

    <div v-else-if="target?.achieved > 0" class="flex h-full min-h-[280px] flex-col items-center justify-center rounded-xl border border-dashed border-border/80 bg-muted/15 px-4 py-8 text-center">
        <p class="text-2xl font-semibold tabular-nums tracking-tight text-[#1A1A1A]">{{ formatMoney(target.achieved) }}</p>
        <p class="mt-2 text-sm text-muted-foreground">Lifetime paid collection</p>
        <p class="mt-1 text-xs text-muted-foreground">Ask an admin to assign your rupee target.</p>
    </div>

    <p v-else class="flex h-full min-h-[280px] items-center justify-center text-center text-sm text-muted-foreground">
        {{ emptyMessage }}
    </p>
</template>
