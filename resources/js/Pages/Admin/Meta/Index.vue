<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaTabs from '@/Components/Admin/MetaTabs.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaSpendAnalytics from '@/Components/Admin/MetaSpendAnalytics.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SyncFromLiveMetaButton from '@/Components/Admin/SyncFromLiveMetaButton.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    analytics: { type: Object, required: true },
    rows: { type: Object, required: true },
    unmatchedCount: { type: Number, default: 0 },
    lastSyncedAt: { type: String, default: null },
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

const applyFilters = (form) => {
    router.get('/admin/meta', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const spendRows = computed(() => props.rows?.data ?? []);
</script>

<template>
    <Head title="Meta ads spend" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta ads spend"
            subtitle="Insights analytics, dropdown filters (app name / app ID / campaign / theme), and ad-level spend."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="meta"
                    :from="filters.from_date || null"
                    :to="filters.to_date || null"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <MetaTabs current="spend" />

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
            <span v-if="unmatchedCount" class="ml-2 text-amber-700">· {{ unmatchedCount }} unmatched in this filter</span>
        </p>

        <MetaSpendAnalytics :analytics="analytics" />

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
                <CardDescription>
                    All filters are dropdowns where possible. App name = Meta account label; App ID groups accounts under the same Meta app.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <MetaSpendFilters
                    :filters="filters"
                    :options="filterOptions"
                    show-marketer
                    show-match
                    @apply="applyFilters"
                    @reset="applyFilters"
                />
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Insights spend</CardTitle>
                <CardDescription>Daily ad-level rows. Matched spend overwrites Marketers → Today.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Ad</TableHead>
                            <TableHead>Campaign</TableHead>
                            <TableHead>App</TableHead>
                            <TableHead class="text-right">Spend</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in spendRows" :key="row.id">
                            <TableCell class="tabular-nums whitespace-nowrap">{{ row.spend_date }}</TableCell>
                            <TableCell>
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
                            <TableCell class="max-w-[180px] truncate text-sm" :title="row.campaign_name">{{ row.campaign_name || '—' }}</TableCell>
                            <TableCell class="text-sm">
                                <p>{{ row.account_label }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ row.app_id }}</p>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatMoney(row.spend_amount) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="! spendRows.length">
                            <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                No Insights rows for this filter. Sync from live Meta or widen the date range.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination v-if="rows?.links" class="mt-4" :links="rows.links" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
