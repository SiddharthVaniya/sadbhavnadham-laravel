<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    visitorsLabel: { type: String, default: 'Cause views' },
    checkoutsLabel: { type: String, default: 'Checkouts' },
    paidLabel: { type: String, default: 'Paid' },
    emptyMessage: { type: String, default: 'No analytics data for this period yet.' },
});

const maxValue = computed(() => Math.max(
    ...props.points.flatMap((point) => [point.visitors, point.checkouts, point.paid]),
    1,
));

const barHeight = (value) => `${Math.max(4, Math.round((value / maxValue.value) * 100))}%`;
</script>

<template>
    <div>
        <div class="flex h-44 min-w-0 items-end gap-2 overflow-x-auto border-b border-border pb-2">
            <div
                v-for="point in points"
                :key="point.key"
                class="flex w-8 shrink-0 flex-col items-center gap-1 sm:w-auto sm:min-w-0 sm:flex-1"
            >
                <div class="flex h-36 w-full items-end justify-center gap-0.5">
                    <div
                        class="w-2 rounded-t bg-sky-500"
                        :title="`${point.visitors} ${visitorsLabel.toLowerCase()}`"
                        :style="{ height: barHeight(point.visitors) }"
                    />
                    <div
                        class="w-2 rounded-t bg-amber-500"
                        :title="`${point.checkouts} ${checkoutsLabel.toLowerCase()}`"
                        :style="{ height: barHeight(point.checkouts) }"
                    />
                    <div
                        class="w-2 rounded-t bg-emerald-600"
                        :title="`${point.paid} ${paidLabel.toLowerCase()}`"
                        :style="{ height: barHeight(point.paid) }"
                    />
                </div>
                <span class="truncate text-[10px] font-medium text-muted-foreground">{{ point.label }}</span>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-sky-500" /> {{ visitorsLabel }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-500" /> {{ checkoutsLabel }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-600" /> {{ paidLabel }}</span>
        </div>
        <p v-if="! points.length" class="py-10 text-center text-sm text-muted-foreground">{{ emptyMessage }}</p>
    </div>
</template>
