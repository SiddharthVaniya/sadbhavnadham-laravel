<script setup>
import { computed } from 'vue';

const props = defineProps({
    months: { type: Array, default: () => [] },
    granularity: { type: String, default: 'day' },
    amountOnly: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'No collection data yet.' },
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

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

        return `₹${lakhs.toFixed(lakhs >= 10 || Number.isInteger(lakhs) ? 0 : 1).replace(/\.0$/, '')}L`;
    }

    if (amount >= 1000) {
        const thousands = amount / 1000;
        const digits = Number.isInteger(thousands) || thousands >= 10 ? 0 : 1;

        return `₹${thousands.toFixed(digits).replace(/\.0$/, '')}k`;
    }

    return `₹${Math.round(amount)}`;
};

const rawMaxAmount = computed(() => Math.max(...props.months.map((month) => Number(month.amount) || 0), 1));
const rawMaxDonations = computed(() => Math.max(...props.months.map((month) => Number(month.donations) || 0), 1));
const amountScale = computed(() => niceScale(rawMaxAmount.value));
const maxAmount = computed(() => amountScale.value.max);
const maxDonations = computed(() => rawMaxDonations.value);

const yTicks = computed(() => {
    const ticks = [];

    for (let i = 4; i >= 0; i -= 1) {
        const value = amountScale.value.step * i;
        ticks.push({
            value,
            label: formatCompactMoney(value),
        });
    }

    return ticks;
});

const barHeight = (value, max) => {
    if (! value) {
        return '4px';
    }

    return `${Math.max(6, Math.round((value / max) * 100))}%`;
};

const labelIndexes = computed(() => {
    if (props.granularity === 'hour' || props.months.length === 24) {
        return [0, 6, 12, 18, 23];
    }

    if (props.months.length <= 8) {
        return props.months.map((_, index) => index);
    }

    const last = props.months.length - 1;
    const step = Math.max(1, Math.floor(last / 4));

    return [...new Set([0, step, step * 2, step * 3, last])];
});

const periodTotal = computed(() => props.months.reduce((sum, month) => sum + Number(month.amount || 0), 0));
const periodDonations = computed(() => props.months.reduce((sum, month) => sum + Number(month.donations || 0), 0));

const tooltipHeading = (month) => {
    const key = String(month?.key || '');

    if (props.granularity === 'hour') {
        return month.label ? `${month.label}` : 'This hour';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(key)) {
        const [year, monthNum, day] = key.split('-').map(Number);

        return new Date(year, monthNum - 1, day).toLocaleDateString('en-IN', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    if (/^\d{4}-\d{2}$/.test(key)) {
        const [year, monthNum] = key.split('-').map(Number);

        return new Date(year, monthNum - 1, 1).toLocaleDateString('en-IN', {
            month: 'long',
            year: 'numeric',
        });
    }

    return month.label || 'This period';
};

const tooltipPositionClass = (index) => {
    const last = props.months.length - 1;

    if (index <= 1) {
        return 'left-0';
    }

    if (index >= last - 1) {
        return 'right-0';
    }

    return 'left-1/2 -translate-x-1/2';
};
</script>

<template>
    <div class="flex h-full min-h-[280px] flex-col overflow-visible">
        <div v-if="months.length" class="flex min-h-0 flex-1 gap-2">
            <div class="flex w-10 shrink-0 flex-col justify-between pb-6 pt-1 text-right text-[10px] leading-none tabular-nums text-muted-foreground">
                <span v-for="tick in yTicks" :key="tick.label">{{ tick.label }}</span>
            </div>

            <div class="relative min-w-0 flex-1">
                <div class="pointer-events-none absolute inset-x-0 top-1 bottom-6 flex flex-col justify-between">
                    <span
                        v-for="tick in yTicks"
                        :key="`${tick.label}-line`"
                        class="block border-t border-[#eef1f4]"
                    />
                </div>

                <div class="relative flex h-full items-end gap-px pb-6 sm:gap-0.5">
                    <div
                        v-for="(month, index) in months"
                        :key="month.key"
                        class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end rounded-sm hover:bg-[#0f7f87]/[0.06]"
                    >
                        <div class="relative flex min-h-0 w-full flex-1 items-end justify-center gap-0.5 px-px">
                            <div
                                class="pointer-events-none absolute top-2 z-20 hidden w-max min-w-[148px] max-w-[210px] rounded-lg bg-zinc-900 px-3 py-2 text-left text-white shadow-lg group-hover:block"
                                :class="tooltipPositionClass(index)"
                            >
                                <p class="text-[11px] font-medium leading-snug text-white/80">
                                    {{ tooltipHeading(month) }}
                                </p>
                                <p class="mt-1 text-sm font-semibold tabular-nums">
                                    {{ formatMoney(month.amount) }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-white/70">
                                    {{ Number(month.donations || 0).toLocaleString('en-IN') }}
                                    donation{{ Number(month.donations) === 1 ? '' : 's' }}
                                </p>
                                <p v-if="month.donations" class="text-[11px] text-white/70">
                                    Avg {{ formatMoney(month.amount / month.donations) }}
                                </p>
                            </div>
                            <div
                                v-if="! amountOnly"
                                class="chart-bar-animate w-1.5 rounded-t bg-[#1A1A1A]/75 transition-opacity group-hover:opacity-100 sm:w-2"
                                :class="{ 'opacity-30': ! month.donations }"
                                :style="{
                                    height: barHeight(month.donations, maxDonations),
                                    animationDelay: `${index * 0.025}s`,
                                }"
                            />
                            <div
                                class="chart-bar-animate rounded-t bg-[#0f7f87] shadow-[0_0_0_1px_rgba(15,127,135,0.08)] transition-opacity group-hover:opacity-100"
                                :class="amountOnly ? 'w-full max-w-[12px] sm:max-w-[14px]' : 'w-1.5 sm:w-2'"
                                :style="{
                                    height: barHeight(month.amount, maxAmount),
                                    opacity: month.amount ? 1 : 0.22,
                                    animationDelay: `${index * 0.025 + 0.04}s`,
                                }"
                            />
                        </div>
                        <span
                            v-if="labelIndexes.includes(index)"
                            class="absolute bottom-0 truncate text-[10px] font-medium text-muted-foreground"
                        >
                            {{ month.label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="months.length" class="mt-3 flex items-center justify-between gap-3 border-t border-border/60 pt-3 text-xs text-muted-foreground">
            <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
                <span v-if="! amountOnly" class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-sm bg-[#1A1A1A]/75" /> Donations
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-sm bg-[#0f7f87]" /> Amount
                </span>
            </span>
            <span class="shrink-0 text-right tabular-nums">
                <span class="font-medium text-foreground">{{ formatMoney(periodTotal) }}</span>
                <span class="text-muted-foreground">
                    · {{ Number(periodDonations).toLocaleString('en-IN') }} donation{{ periodDonations === 1 ? '' : 's' }}
                </span>
            </span>
        </div>

        <p v-else class="flex flex-1 items-center justify-center py-10 text-center text-sm text-muted-foreground">{{ emptyMessage }}</p>
    </div>
</template>
