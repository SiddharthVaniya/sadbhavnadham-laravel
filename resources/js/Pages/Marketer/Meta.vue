<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
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
    rows: { type: Object, required: true },
    lastSyncedAt: { type: String, default: null },
});

const form = reactive({
    q: props.filters.q ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
});

const refreshing = ref(false);

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

const spendRows = computed(() => props.rows?.data ?? []);

const apply = () => {
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
    <Head title="Meta spend" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Your Meta ads"
            subtitle="Read-only spend from Meta Insights matched to your name in the ad title. Credentials are admin-only."
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
                <CardDescription>Search your ads by name or campaign. Default range is today (Asia/Kolkata).</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-4" @submit.prevent="apply">
                    <Input v-model="form.q" placeholder="Search ad / campaign" />
                    <Input v-model="form.from_date" type="date" />
                    <Input v-model="form.to_date" type="date" />
                    <Button type="submit">Apply</Button>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Spend</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Ad</TableHead>
                            <TableHead>Campaign</TableHead>
                            <TableHead>Ad set</TableHead>
                            <TableHead class="text-right">Spend</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in spendRows" :key="row.id">
                            <TableCell class="tabular-nums whitespace-nowrap">{{ row.spend_date }}</TableCell>
                            <TableCell>
                                <p class="max-w-md truncate font-medium" :title="row.ad_name">{{ row.ad_name || '—' }}</p>
                                <p class="text-xs text-muted-foreground">{{ row.account_label }}</p>
                            </TableCell>
                            <TableCell class="max-w-[200px] truncate text-sm">{{ row.campaign_name || '—' }}</TableCell>
                            <TableCell class="max-w-[160px] truncate text-sm">{{ row.adset_name || '—' }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatMoney(row.spend_amount) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="! spendRows.length">
                            <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
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
