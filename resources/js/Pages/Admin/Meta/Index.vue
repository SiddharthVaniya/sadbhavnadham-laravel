<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaTabs from '@/Components/Admin/MetaTabs.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SyncFromLiveMetaButton from '@/Components/Admin/SyncFromLiveMetaButton.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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
    accountOptions: { type: Array, default: () => [] },
    marketers: { type: Array, default: () => [] },
    rows: { type: Object, required: true },
    unmatchedCount: { type: Number, default: 0 },
    lastSyncedAt: { type: String, default: null },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ?? '',
    meta_ad_account_id: props.filters.meta_ad_account_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    campaign: props.filters.campaign ?? '',
    adset: props.filters.adset ?? '',
    match: props.filters.match ?? 'all',
    cause: props.filters.cause ?? '',
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

const applyFilters = () => {
    router.get('/admin/meta', { ...filterForm }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.q = '';
    filterForm.user_id = '';
    filterForm.meta_ad_account_id = '';
    filterForm.from_date = '';
    filterForm.to_date = '';
    filterForm.campaign = '';
    filterForm.adset = '';
    filterForm.match = 'all';
    filterForm.cause = '';
    applyFilters();
};

const spendRows = computed(() => props.rows?.data ?? []);
</script>

<template>
    <Head title="Meta ads spend" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta ads spend"
            subtitle="Live Insights rows with smart filters on ad names like Marketer | Date | Brand | Theme | Cause."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="meta"
                    :from="filterForm.from_date || null"
                    :to="filterForm.to_date || null"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <MetaTabs current="spend" />

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
            <span v-if="unmatchedCount" class="ml-2 text-amber-700">· {{ unmatchedCount }} unmatched in this filter</span>
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Smart filters</CardTitle>
                <CardDescription>
                    Search matches marketer names inside pipe-separated ad/campaign strings.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-3 xl:grid-cols-4" @submit.prevent="applyFilters">
                    <Input v-model="filterForm.q" placeholder="Search ad / campaign / ad set" />
                    <select v-model="filterForm.user_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">All marketers</option>
                        <option v-for="m in marketers" :key="m.id" :value="String(m.id)">{{ m.name }} ({{ m.code }})</option>
                    </select>
                    <select v-model="filterForm.meta_ad_account_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">All Meta accounts</option>
                        <option v-for="a in accountOptions" :key="a.id" :value="String(a.id)">{{ a.label }}</option>
                    </select>
                    <Input v-model="filterForm.from_date" type="date" />
                    <Input v-model="filterForm.to_date" type="date" />
                    <Input v-model="filterForm.campaign" placeholder="Campaign contains" />
                    <Input v-model="filterForm.adset" placeholder="Ad set contains" />
                    <Input v-model="filterForm.cause" placeholder="Cause / theme keyword" />
                    <select v-model="filterForm.match" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="all">Matched + unmatched</option>
                        <option value="matched">Matched only</option>
                        <option value="unmatched">Unmatched only</option>
                    </select>
                    <div class="flex gap-2 md:col-span-2">
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Insights spend</CardTitle>
                <CardDescription>Daily ad-level rows from Meta. Matched spend overwrites Marketers → Today for that marketer.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Ad</TableHead>
                            <TableHead>Campaign</TableHead>
                            <TableHead>Account</TableHead>
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
                            <TableCell class="text-sm">{{ row.account_label }}</TableCell>
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
