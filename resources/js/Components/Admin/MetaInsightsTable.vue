<script setup>
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

defineProps({
    rows: { type: Array, default: () => [] },
    showMarketer: { type: Boolean, default: false },
    emptyColspan: { type: Number, default: 13 },
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatInt = (value) => Number(value || 0).toLocaleString('en-IN');

const formatPercent = (value) =>
    value == null ? '—' : `${Number(value).toFixed(2)}%`;

const formatRate = (value) =>
    value == null ? '—' : formatMoney(value);
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="whitespace-nowrap">Date</TableHead>
                    <TableHead v-if="showMarketer" class="whitespace-nowrap">Marketer</TableHead>
                    <TableHead class="min-w-[200px]">Ad</TableHead>
                    <TableHead class="min-w-[140px]">Campaign</TableHead>
                    <TableHead class="min-w-[120px]">Ad set</TableHead>
                    <TableHead class="min-w-[120px]">App</TableHead>
                    <TableHead class="whitespace-nowrap">Delivery</TableHead>
                    <TableHead class="text-right whitespace-nowrap">Impr.</TableHead>
                    <TableHead class="text-right whitespace-nowrap">Clicks</TableHead>
                    <TableHead class="text-right whitespace-nowrap">Reach</TableHead>
                    <TableHead class="text-right whitespace-nowrap">CTR</TableHead>
                    <TableHead class="text-right whitespace-nowrap">CPC</TableHead>
                    <TableHead class="text-right whitespace-nowrap">CPM</TableHead>
                    <TableHead class="text-right whitespace-nowrap">Spend</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in rows" :key="row.id">
                    <TableCell class="tabular-nums whitespace-nowrap">{{ row.spend_date }}</TableCell>
                    <TableCell v-if="showMarketer">
                        <template v-if="row.marketer_name">
                            <p class="font-medium">{{ row.marketer_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ row.marketer_code }}</p>
                        </template>
                        <span v-else class="text-amber-700">Unmatched</span>
                    </TableCell>
                    <TableCell>
                        <p class="max-w-xs truncate font-medium" :title="row.ad_name">{{ row.ad_name || '—' }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="row.pipe?.theme">{{ row.pipe.theme }}</span>
                            <span v-if="row.pipe?.cause"> · {{ row.pipe.cause }}</span>
                        </p>
                    </TableCell>
                    <TableCell class="max-w-[180px] truncate text-sm" :title="row.campaign_name">
                        {{ row.campaign_name || '—' }}
                    </TableCell>
                    <TableCell class="max-w-[160px] truncate text-sm" :title="row.adset_name">
                        {{ row.adset_name || '—' }}
                    </TableCell>
                    <TableCell class="text-sm">
                        <p>{{ row.account_label }}</p>
                        <p class="font-mono text-xs text-muted-foreground">{{ row.app_id }}</p>
                    </TableCell>
                    <TableCell class="whitespace-nowrap text-sm">
                        <span
                            v-if="row.delivery_active"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                        >
                            Active
                        </span>
                        <span
                            v-else-if="row.ad_effective_status"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ row.ad_effective_status }}
                        </span>
                        <span v-else class="text-xs text-muted-foreground">—</span>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatInt(row.impressions) }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatInt(row.clicks) }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatInt(row.reach) }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatPercent(row.ctr) }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatRate(row.cpc) }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatRate(row.cpm) }}</TableCell>
                    <TableCell class="text-right tabular-nums font-medium">{{ formatMoney(row.spend_amount) }}</TableCell>
                </TableRow>
                <TableRow v-if="! rows.length">
                    <TableCell :colspan="emptyColspan" class="py-10 text-center text-muted-foreground">
                        No Insights rows for this filter. Sync from live Meta or widen the date range.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
