<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MarketerBudgetTabs from '@/Components/Admin/MarketerBudgetTabs.vue';
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
    marketers: { type: Array, default: () => [] },
    rows: { type: Object, required: true },
});

const form = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    year_month: props.filters.year_month ?? '',
    archive: props.filters.archive ?? 'all',
});

const formatMoney = (amount) => {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    return `₹ ${Number(amount).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
};

const apply = () => {
    router.get('/admin/marketers/history', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const reset = () => {
    form.q = '';
    form.user_id = '';
    form.from_date = '';
    form.to_date = '';
    form.year_month = '';
    form.archive = 'all';
    apply();
};

const isoDate = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
};

const syncRange = computed(() => {
    if (form.from_date && form.to_date) {
        return { from: form.from_date, to: form.to_date };
    }

    if (form.from_date) {
        return { from: form.from_date, to: form.from_date };
    }

    if (form.to_date) {
        return { from: form.to_date, to: form.to_date };
    }

    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 6);

    return { from: isoDate(from), to: isoDate(to) };
});
</script>

<template>
    <Head title="Spending history" />
    <AdminLayout>
        <template #header>Marketers</template>

        <PageHeader
            title="Spending history"
            subtitle="Every saved day, with that month’s target, spending limit, spend, and remaining limit."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="history"
                    :from="syncRange.from"
                    :to="syncRange.to"
                    :extra="{ ...form }"
                />
            </template>
        </PageHeader>

        <MarketerBudgetTabs current="history" />

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
                <CardDescription>Search by name, email, or code. Narrow by marketer, month, dates, or archive.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="apply">
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">Search</span>
                        <Input v-model="form.q" placeholder="Name, email, or code" />
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">Marketer</span>
                        <select v-model="form.user_id" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All marketers</option>
                            <option v-for="marketer in marketers" :key="marketer.id" :value="String(marketer.id)">
                                {{ marketer.name }} ({{ marketer.code }})
                            </option>
                        </select>
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">Month</span>
                        <Input v-model="form.year_month" type="month" />
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">From</span>
                        <Input v-model="form.from_date" type="date" />
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">To</span>
                        <Input v-model="form.to_date" type="date" />
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-muted-foreground">Archive</span>
                        <select v-model="form.archive" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="all">All days</option>
                            <option value="today">Today only</option>
                            <option value="archived">Archived only</option>
                        </select>
                    </label>
                    <div class="flex items-end gap-2 md:col-span-3 xl:col-span-6">
                        <Button type="submit">Apply filters</Button>
                        <Button type="button" variant="outline" @click="reset">Reset</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardContent class="pt-6">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Month target</TableHead>
                            <TableHead>Monthly spending limit</TableHead>
                            <TableHead>Month spend</TableHead>
                            <TableHead>Remaining limit</TableHead>
                            <TableHead>Daily spend</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in rows.data" :key="row.id">
                            <TableCell class="whitespace-nowrap">{{ row.spend_date_label }}</TableCell>
                            <TableCell>
                                <p class="font-medium">{{ row.name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ row.code }}</p>
                            </TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(row.month_target_amount) }}</TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(row.month_limit_amount) }}</TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(row.month_spend_amount) }}</TableCell>
                            <TableCell
                                class="tabular-nums font-medium"
                                :class="row.remaining_limit_amount !== null && row.remaining_limit_amount < 0 ? 'text-destructive' : ''"
                            >
                                {{ formatMoney(row.remaining_limit_amount) }}
                            </TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(row.spend_amount) }}</TableCell>
                            <TableCell>
                                <span
                                    class="rounded-md px-2 py-0.5 text-xs"
                                    :class="row.archive === 'archived'
                                        ? 'bg-slate-200 text-slate-800'
                                        : 'bg-emerald-100 text-emerald-900'"
                                >
                                    {{ row.archive === 'archived' ? 'Archived' : 'Today' }}
                                </span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="! rows.data.length">
                            <TableCell colspan="8" class="py-10 text-center text-muted-foreground">
                                No spending days match these filters.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination class="mt-4" :links="rows.links" :meta="rows.meta" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
