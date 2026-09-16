<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'No campaign revenue in this period yet.' },
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const formatPercent = (value) => `${Number(value || 0).toFixed(1).replace(/\.0$/, '')}%`;

const colors = ['#1A1A1A', '#F5A623', '#0f7f87', '#334155', '#16a394', '#cbd5e1'];

const rows = computed(() => props.items.map((item, index) => ({
    ...item,
    color: colors[index % colors.length],
    percentage: Number(item.percentage) || 0,
    amount: Number(item.count) || 0,
})));
</script>

<template>
    <ul v-if="rows.length" class="flex h-full min-h-[280px] flex-col justify-center space-y-4">
        <li
            v-for="(row, index) in rows"
            :key="row.name"
            class="chart-fade-up space-y-2"
            :style="{ animationDelay: `${index * 0.08}s` }"
        >
            <div class="flex items-start justify-between gap-3">
                <span class="inline-flex min-w-0 items-start gap-2.5">
                    <span
                        class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: row.color }"
                    />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium leading-snug text-foreground" :title="row.name">
                            {{ row.name }}
                        </span>
                        <span class="mt-0.5 block text-xs tabular-nums text-muted-foreground">{{ formatMoney(row.amount) }}</span>
                    </span>
                </span>
                <span class="shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-[#1A1A1A]">
                    {{ formatPercent(row.percentage) }}
                </span>
            </div>
            <div class="h-1 overflow-hidden rounded-full bg-[#eef1f4]">
                <div
                    class="chart-scale-x h-full rounded-full"
                    :style="{
                        width: `${Math.max(row.percentage, row.percentage > 0 ? 4 : 0)}%`,
                        backgroundColor: row.color,
                        animationDelay: `${0.12 + (index * 0.08)}s`,
                    }"
                />
            </div>
        </li>
    </ul>

    <p v-else class="flex min-h-[220px] items-center justify-center text-center text-sm text-muted-foreground">
        {{ emptyMessage }}
    </p>
</template>
