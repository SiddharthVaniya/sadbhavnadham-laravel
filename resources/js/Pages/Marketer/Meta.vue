<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaSpendAnalytics from '@/Components/Admin/MetaSpendAnalytics.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { Button } from '@/Components/ui/button';
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
    lastSyncedAt: { type: String, default: null },
});

const refreshing = ref(false);

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

const spendRows = computed(() => props.rows?.data ?? []);

const applyFilters = (form) => {
    router.get('/marketer/meta', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const refresh = () => {
    refreshing.value = true;
    router.post('/marketer/meta/refresh', {}, {
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Meta analytics" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta analytics"
            subtitle="Your matched Meta spend with charts by day, app, campaign, and ad set. Credentials are admin-only."
        >
            <template #actions>
                <Button type="button" :disabled="refreshing" @click="refresh">
                    {{ refreshing ? 'Refreshing…' : 'Refresh from Meta' }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
                <CardDescription>
                    Filter by app name, App ID, campaign, ad set, theme, and cause. Defaults to today (Asia/Kolkata).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <MetaSpendFilters
                    :filters="filters"
                    :options="filterOptions"
                    @apply="applyFilters"
                    @reset="applyFilters"
                />
            </CardContent>
        </Card>

        <MetaSpendAnalytics :analytics="analytics" />

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Your ads</CardTitle>
                <CardDescription>Only rows matched to your name in the ad title.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Ad</TableHead>
                            <TableHead>Campaign</TableHead>
                            <TableHead>Ad set</TableHead>
                            <TableHead>App</TableHead>
                            <TableHead class="text-right">Spend</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in spendRows" :key="row.id">
                            <TableCell class="tabular-nums whitespace-nowrap">{{ row.spend_date }}</TableCell>
                            <TableCell>
                                <p class="max-w-md truncate font-medium" :title="row.ad_name">{{ row.ad_name || '—' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="row.pipe?.theme">{{ row.pipe.theme }}</span>
                                    <span v-if="row.pipe?.cause"> · {{ row.pipe.cause }}</span>
                                </p>
                            </TableCell>
                            <TableCell class="max-w-[200px] truncate text-sm">{{ row.campaign_name || '—' }}</TableCell>
                            <TableCell class="max-w-[160px] truncate text-sm">{{ row.adset_name || '—' }}</TableCell>
                            <TableCell class="text-sm">
                                <p>{{ row.account_label }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ row.app_id }}</p>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatMoney(row.spend_amount) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="! spendRows.length">
                            <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                No Meta rows for you in this range. Ask admin to sync, or check that your ads start with your first name before |.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination v-if="rows?.links" class="mt-4" :links="rows.links" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
