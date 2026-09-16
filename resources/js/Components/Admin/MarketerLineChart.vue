<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    granularity: { type: String, default: 'day' },
    emptyMessage: { type: String, default: 'No campaign revenue for this period yet.' },
});

const activeSeries = ref(null);
const chartContainer = ref(null);
const containerWidth = ref(960);

const height = 280;
const padding = { top: 28, right: 16, bottom: 36, left: 52 };

let resizeObserver = null;

const updateWidth = () => {
    if (! chartContainer.value) {
        return;
    }

    containerWidth.value = Math.max(320, Math.round(chartContainer.value.clientWidth));
};

onMounted(() => {
    updateWidth();
    resizeObserver = new ResizeObserver(updateWidth);

    if (chartContainer.value) {
        resizeObserver.observe(chartContainer.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
});

const width = computed(() => containerWidth.value);
const plotWidth = computed(() => width.value - padding.left - padding.right);
const plotHeight = height - padding.top - padding.bottom;

const brandSeries = [
    { color: '#2563eb', fill: 'rgba(37, 99, 235, 0.1)' },
    { color: '#ea580c', fill: 'rgba(234, 88, 12, 0.1)' },
    { color: '#7c3aed', fill: 'rgba(124, 58, 237, 0.1)' },
    { color: '#059669', fill: 'rgba(5, 150, 105, 0.1)' },
    { color: '#db2777', fill: 'rgba(219, 39, 119, 0.1)' },
];

const niceScale = (maxValue) => {
    const padded = Math.max(Number(maxValue) || 0, 1) * 1.08;
    const roughStep = padded / 4;
    const magnitude = 10 ** Math.floor(Math.log10(roughStep));
    const normalized = roughStep / magnitude;
    let step = magnitude;

    if (normalized > 5) {
        step = 10 * magnitude;
    } else if (normalized > 2.5) {
        step = 5 * magnitude;
    } else if (normalized > 2) {
        step = 2.5 * magnitude;
    } else if (normalized > 1) {
        step = 2 * magnitude;
    }

    return { max: step * 4, step };
};

const formatCompactMoney = (value) => {
    const amount = Number(value) || 0;

    if (amount <= 0) {
        return '₹0';
    }

    if (amount >= 100000) {
        const lakhs = amount / 100000;
        const digits = Number.isInteger(lakhs) ? 0 : 1;

        return `₹${lakhs.toFixed(digits).replace(/\.0$/, '')}L`;
    }

    if (amount >= 1000) {
        const thousands = amount / 1000;
        const digits = Number.isInteger(thousands) || thousands >= 10 ? 0 : 1;

        return `₹${thousands.toFixed(digits).replace(/\.0$/, '')}k`;
    }

    return `₹${Math.round(amount)}`;
};

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const rawMaxRevenue = computed(() => Math.max(
    ...props.series.flatMap((item) => (item.points || []).map((point) => Number(point.revenue) || 0)),
    1,
));

const scale = computed(() => niceScale(rawMaxRevenue.value));
const maxRevenue = computed(() => scale.value.max);

const xForIndex = (index) => {
    if (props.labels.length <= 1) {
        return padding.left + (plotWidth.value / 2);
    }

    return padding.left + (index / (props.labels.length - 1)) * plotWidth.value;
};

const yForValue = (value) => padding.top + plotHeight - ((Number(value) || 0) / maxRevenue.value) * plotHeight;

const buildSmoothPath = (dots) => {
    if (dots.length === 0) {
        return '';
    }

    if (dots.length === 1) {
        return `M ${dots[0].x} ${dots[0].y}`;
    }

    let path = `M ${dots[0].x} ${dots[0].y}`;

    for (let index = 1; index < dots.length; index += 1) {
        const previous = dots[index - 1];
        const current = dots[index];
        const midX = (previous.x + current.x) / 2;

        path += ` C ${midX} ${previous.y}, ${midX} ${current.y}, ${current.x} ${current.y}`;
    }

    return path;
};

const buildAreaPath = (dots) => {
    if (dots.length === 0) {
        return '';
    }

    const baseline = padding.top + plotHeight;
    const line = buildSmoothPath(dots);

    return `${line} L ${dots[dots.length - 1].x} ${baseline} L ${dots[0].x} ${baseline} Z`;
};

const linePaths = computed(() => props.series.map((item, index) => {
    const theme = brandSeries[index % brandSeries.length];
    const dots = (item.points || []).map((point, pointIndex) => ({
        x: xForIndex(pointIndex),
        y: yForValue(point.revenue),
        revenue: Number(point.revenue) || 0,
    }));
    const total = dots.reduce((sum, dot) => sum + dot.revenue, 0);

    return {
        ...item,
        total,
        color: theme.color,
        fill: theme.fill,
        path: buildSmoothPath(dots),
        areaPath: buildAreaPath(dots),
        dots: dots.filter((dot) => dot.revenue > 0),
    };
}));

const yTicks = computed(() => {
    const ticks = [];

    for (let i = 0; i <= 4; i += 1) {
        const value = scale.value.step * i;
        ticks.push({
            value,
            y: yForValue(value),
            label: formatCompactMoney(value),
        });
    }

    return ticks.reverse();
});

const xLabelIndexes = computed(() => {
    const count = props.labels.length;

    if (props.granularity === 'hour' || count === 24) {
        return [0, 6, 12, 18, 23];
    }

    if (count <= 7) {
        return props.labels.map((_, index) => index);
    }

    if (count <= 14) {
        return [...new Set([0, Math.floor(count / 2), count - 1])];
    }

    const step = Math.max(1, Math.floor(count / 6));

    return [...new Set(
        Array.from({ length: Math.ceil(count / step) }, (_, index) => Math.min(index * step, count - 1)),
    )];
});

const seriesOpacity = (index) => {
    if (activeSeries.value === null) {
        return 1;
    }

    return activeSeries.value === index ? 1 : 0.28;
};

const legendCardGlow = (color) => `0 0 0 1px ${color}40, 0 0 18px -2px ${color}55, 0 10px 24px -8px ${color}35`;

const setActiveSeries = (index) => {
    activeSeries.value = index;
};

const clearActiveSeries = () => {
    activeSeries.value = null;
};
</script>

<template>
    <div
        v-if="labels.length && series.length"
        class="w-full"
        @mouseleave="clearActiveSeries"
    >
        <div
            ref="chartContainer"
            class="w-full bg-white py-3"
        >
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="block h-[280px] w-full overflow-visible"
                preserveAspectRatio="xMinYMid meet"
            >
                <line
                    v-for="tick in yTicks"
                    :key="tick.label"
                    :x1="padding.left"
                    :x2="width - padding.right"
                    :y1="tick.y"
                    :y2="tick.y"
                    stroke="#eef1f4"
                    stroke-width="1"
                    vector-effect="non-scaling-stroke"
                />
                <text
                    v-for="tick in yTicks"
                    :key="`${tick.label}-label`"
                    :x="padding.left - 8"
                    :y="tick.y + 4"
                    text-anchor="end"
                    class="fill-muted-foreground text-[10px]"
                >
                    {{ tick.label }}
                </text>

                <g
                    v-for="(item, seriesIndex) in linePaths"
                    :key="item.name"
                    class="transition-opacity duration-300 ease-out"
                    :style="{ opacity: seriesOpacity(seriesIndex) }"
                    @mouseenter="setActiveSeries(seriesIndex)"
                >
                    <path
                        v-if="activeSeries === seriesIndex"
                        :d="item.areaPath"
                        :fill="item.fill"
                        stroke="none"
                    />
                    <path
                        :d="item.path"
                        pathLength="1"
                        fill="none"
                        :stroke="item.color"
                        :stroke-width="activeSeries === seriesIndex ? 3 : 2.25"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                        vector-effect="non-scaling-stroke"
                        class="chart-line-path"
                        :style="{ animationDelay: `${seriesIndex * 0.12}s` }"
                    />
                    <circle
                        v-for="(dot, index) in item.dots"
                        :key="`${item.name}-${index}`"
                        class="chart-dot-animate"
                        :cx="dot.x"
                        :cy="dot.y"
                        :r="activeSeries === seriesIndex ? 4.5 : 3.5"
                        :fill="item.color"
                        stroke="#ffffff"
                        stroke-width="1.5"
                        vector-effect="non-scaling-stroke"
                        :style="{ animationDelay: `${0.4 + (seriesIndex * 0.12) + (index * 0.03)}s` }"
                    />
                </g>

                <text
                    v-for="index in xLabelIndexes"
                    :key="labels[index].key"
                    :x="xForIndex(index)"
                    :y="height - 8"
                    text-anchor="middle"
                    class="fill-muted-foreground text-[10px]"
                >
                    {{ labels[index].label }}
                </text>
            </svg>
        </div>

        <ul class="grid gap-2 border-t border-[#eef1f4] px-6 py-4 sm:grid-cols-2">
            <li
                v-for="(item, index) in linePaths"
                :key="item.name"
                class="chart-fade-up cursor-pointer rounded-lg border bg-white px-3 py-2.5 transition-all duration-300 ease-out"
                :class="[
                    activeSeries === null
                        ? 'border-border/60 opacity-100'
                        : activeSeries === index
                            ? 'z-10 border-2 opacity-100'
                            : 'border-border/50 opacity-40 saturate-50',
                ]"
                :style="{
                    animationDelay: `${0.45 + (index * 0.06)}s`,
                    borderColor: activeSeries === index ? item.color : undefined,
                    boxShadow: activeSeries === index ? legendCardGlow(item.color) : undefined,
                }"
                @mouseenter="setActiveSeries(index)"
            >
                <span class="flex items-start gap-2.5">
                    <span
                        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium leading-snug text-foreground">
                            {{ item.name || 'Untitled' }}
                        </span>
                        <span class="mt-0.5 block text-xs tabular-nums text-muted-foreground">
                            {{ formatMoney(item.total) }} total
                        </span>
                    </span>
                </span>
            </li>
        </ul>
    </div>

    <p v-else class="py-10 text-center text-sm text-muted-foreground">{{ emptyMessage }}</p>
</template>
