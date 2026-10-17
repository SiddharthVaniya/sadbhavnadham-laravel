<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { TrendingUp } from '@lucide/vue';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    partners: { type: Array, default: () => [] },
    amountColumnLabel: { type: String, required: true },
    showBudgetColumns: { type: Boolean, default: false },
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const formatRoas = (roas) => {
    if (roas === null || roas === undefined || roas === '') {
        return '—';
    }

    const value = Number(roas);

    if (! Number.isFinite(value)) {
        return '—';
    }

    const formatted = value.toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    return `${formatted}×`;
};

const partnerRows = computed(() =>
    (props.partners || []).filter((row) => ! row.is_organic && ! row.is_total_donation),
);

const organicRow = computed(() => (props.partners || []).find((row) => row.is_organic) ?? null);

const totalRow = computed(() => (props.partners || []).find((row) => row.is_total_donation) ?? null);

const columnCount = computed(() => (props.showBudgetColumns ? 8 : 5));

const dashCell = '—';
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-border bg-card">
        <Table>
            <TableHeader>
                <TableRow class="border-b border-border bg-muted/50 hover:bg-muted/50">
                    <TableHead class="h-10 font-semibold text-foreground">Partner</TableHead>
                    <TableHead class="h-10 font-semibold text-foreground">Referral code</TableHead>
                    <TableHead class="h-10 font-semibold text-foreground">Orders</TableHead>
                    <TableHead class="h-10 text-right font-semibold text-foreground">
                        {{ amountColumnLabel }}
                    </TableHead>
                    <TableHead
                        v-if="showBudgetColumns"
                        class="h-10 text-right font-semibold text-foreground"
                    >
                        Target
                    </TableHead>
                    <TableHead
                        v-if="showBudgetColumns"
                        class="h-10 text-right font-semibold text-foreground"
                    >
                        Spend
                    </TableHead>
                    <TableHead
                        v-if="showBudgetColumns"
                        class="h-10 text-right font-semibold text-foreground"
                    >
                        ROAS
                    </TableHead>
                    <TableHead class="h-10 w-[100px] text-right font-semibold text-foreground">
                        Actions
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="partner in partnerRows"
                    :key="partner.user_id"
                    class="border-b border-border/60 transition-colors hover:bg-muted/20"
                >
                    <TableCell class="py-3 font-medium">{{ partner.name }}</TableCell>
                    <TableCell class="py-3">
                        <span class="font-mono text-xs text-muted-foreground">{{ partner.code }}</span>
                    </TableCell>
                    <TableCell class="py-3 tabular-nums">{{ partner.paid_orders }}</TableCell>
                    <TableCell class="py-3 text-right font-medium tabular-nums">
                        {{ formatMoney(partner.revenue) }}
                    </TableCell>
                    <TableCell
                        v-if="showBudgetColumns"
                        class="py-3 text-right tabular-nums text-muted-foreground"
                    >
                        {{ partner.target_amount == null ? dashCell : formatMoney(partner.target_amount) }}
                    </TableCell>
                    <TableCell
                        v-if="showBudgetColumns"
                        class="py-3 text-right tabular-nums text-muted-foreground"
                    >
                        {{ formatMoney(partner.spend_amount) }}
                    </TableCell>
                    <TableCell
                        v-if="showBudgetColumns"
                        class="py-3 text-right tabular-nums font-medium"
                    >
                        {{ formatRoas(partner.roas) }}
                    </TableCell>
                    <TableCell class="py-3 text-right">
                        <Button as-child size="sm" variant="ghost" class="h-8 gap-1.5 text-blue-600 hover:text-blue-700">
                            <Link :href="partner.referrals_href">
                                <TrendingUp class="size-3.5" />
                                Details
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>

                <TableRow v-if="organicRow" class="border-t border-border bg-muted/30 hover:bg-muted/30">
                    <TableCell class="py-3 font-medium text-muted-foreground">
                        {{ organicRow.name }}
                    </TableCell>
                    <TableCell class="py-3 text-muted-foreground">{{ organicRow.code }}</TableCell>
                    <TableCell class="py-3 tabular-nums text-muted-foreground">{{ organicRow.paid_orders }}</TableCell>
                    <TableCell class="py-3 text-right font-medium tabular-nums text-muted-foreground">
                        {{ formatMoney(organicRow.revenue) }}
                    </TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell class="py-3 text-right">
                        <Button as-child size="sm" variant="ghost" class="h-8 gap-1.5 text-blue-600 hover:text-blue-700">
                            <Link :href="organicRow.referrals_href">
                                <TrendingUp class="size-3.5" />
                                Details
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>

                <TableRow
                    v-if="totalRow"
                    class="border-t-2 border-primary/25 bg-primary/5 hover:bg-primary/5"
                >
                    <TableCell class="py-3.5 text-base font-bold tracking-tight text-foreground">
                        {{ totalRow.name }}
                    </TableCell>
                    <TableCell class="py-3.5 text-muted-foreground">{{ totalRow.code }}</TableCell>
                    <TableCell class="py-3.5 text-base font-bold tabular-nums">
                        {{ totalRow.paid_orders }}
                    </TableCell>
                    <TableCell class="py-3.5 text-right text-base font-bold tabular-nums text-primary">
                        {{ formatMoney(totalRow.revenue) }}
                    </TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3.5 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3.5 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell v-if="showBudgetColumns" class="py-3.5 text-right text-muted-foreground">{{ dashCell }}</TableCell>
                    <TableCell class="py-3.5 text-right">
                        <Button as-child size="sm" variant="outline" class="h-8 gap-1.5 border-primary/30 bg-background/80">
                            <Link :href="totalRow.referrals_href">
                                <TrendingUp class="size-3.5" />
                                View all
                            </Link>
                        </Button>
                    </TableCell>
                </TableRow>

                <TableRow v-if="! partners.length">
                    <TableCell :colspan="columnCount" class="py-10 text-center text-muted-foreground">
                        No staff members have referral codes yet.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
