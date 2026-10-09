<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'No data to chart.' },
    valueKey: { type: String, default: 'spend' },
    valueFormat: { type: String, default: 'money' },
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const formatValue = (value) => {
    if (props.valueFormat === 'number') {
        return Number(value || 0).toLocaleString('en-IN');
    }

    if (props.valueFormat === 'percent') {
        return `${Number(value || 0).toFixed(1)}%`;
    }

    return formatMoney(value);
};

const colors = ['#2563eb', '#ea580c', '#7c3aed', '#059669', '#db2777', '#0f766e', '#ca8a04', '#64748b'];

const rows = computed(() => {
    const max = Math.max(...props.items.map((item) => Number(item[props.valueKey] ?? item.count ?? 0)), 1);

    return props.items.map((item, index) => {
        const value = Number(item[props.valueKey] ?? item.count ?? 0);

        return {
            name: item.name,
            value,
            percentage: item.percentage ?? (max > 0 ? (value / max) * 100 : 0),
            barWidth: max > 0 ? (value / max) * 100 : 0,
            color: colors[index % colors.length],
        };
    });
});
</script>

<template>
    <ul v-if="rows.length" class="space-y-3">
        <li
            v-for="(row, index) in rows"
            :key="row.name"
            class="chart-fade-up space-y-1.5"
            :style="{ animationDelay: `${index * 0.06}s` }"
        >
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium" :title="row.name">{{ row.name }}</span>
                <span class="shrink-0 tabular-nums text-muted-foreground">{{ formatValue(row.value) }}</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-[#eef1f4]">
                <div
                    class="chart-scale-x h-full rounded-full"
                    :style="{
                        width: `${Math.max(row.barWidth, row.value > 0 ? 3 : 0)}%`,
                        backgroundColor: row.color,
                        animationDelay: `${0.1 + (index * 0.06)}s`,
                    }"
                />
            </div>
        </li>
    </ul>
    <p v-else class="flex min-h-[200px] items-center justify-center text-center text-sm text-muted-foreground">
        {{ emptyMessage }}
    </p>
</template>
