<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
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
    spendDate: { type: String, required: true },
    spendDateLabel: { type: String, required: true },
    maxDate: { type: String, required: true },
    yearMonthLabel: { type: String, required: true },
    marketers: { type: Array, default: () => [] },
});

const blank = (marketer) => ({
    user_id: marketer.user_id,
    name: marketer.name,
    email: marketer.email,
    code: marketer.code,
    month_spend_amount: marketer.month_spend_amount,
    remaining_limit_amount: marketer.remaining_limit_amount,
    saved_spend_amount: marketer.spend_amount ?? 0,
    spend_amount: marketer.spend_amount ?? '',
});

const rows = reactive(props.marketers.map(blank));

watch(
    () => props.marketers,
    (next) => {
        rows.splice(0, rows.length, ...next.map(blank));
    },
    { deep: true },
);

const form = useForm({
    spend_date: props.spendDate,
    marketers: [],
});

const isToday = computed(() => props.spendDate === props.maxDate);

const syncFrom = computed(() => {
    const parts = props.spendDate.split('-').map(Number);
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    date.setDate(date.getDate() - 1);
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
});

const monthSpend = (row) => {
    const savedMonth = Number(row.month_spend_amount) || 0;
    const savedDay = Number(row.saved_spend_amount) || 0;
    const currentDay = row.spend_amount === '' || row.spend_amount === null ? 0 : Number(row.spend_amount);

    return savedMonth - savedDay + currentDay;
};

const remainingLimit = (row) => {
    if (row.remaining_limit_amount === null || row.remaining_limit_amount === undefined) {
        return null;
    }

    const savedDay = Number(row.saved_spend_amount) || 0;
    const currentDay = row.spend_amount === '' || row.spend_amount === null ? 0 : Number(row.spend_amount);

    return Number(row.remaining_limit_amount) - (currentDay - savedDay);
};

const totalRemaining = computed(() =>
    rows.reduce((sum, row) => sum + (remainingLimit(row) || 0), 0),
);

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const save = () => {
    form.spend_date = props.spendDate;
    form.marketers = rows.map((row) => ({
        user_id: row.user_id,
        spend_amount: row.spend_amount === '' || row.spend_amount === null
            ? 0
            : Number(row.spend_amount),
    }));

    form.put('/admin/marketers/today', { preserveScroll: true });
};

const openDate = (value) => {
    if (! value || value === props.spendDate) {
        return;
    }

    router.get('/admin/marketers/today', { date: value }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Today spending limit" />
    <AdminLayout>
        <template #header>Marketers</template>

        <PageHeader
            title="Today spending"
            :subtitle="`Record spend for ${spendDateLabel}. Remaining limit uses the ${yearMonthLabel} monthly spending limit.`"
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="today"
                    :from="syncFrom"
                    :to="spendDate"
                    :date="spendDate"
                />
                <Button type="button" :disabled="form.processing || ! rows.length" @click="save">
                    {{ form.processing ? 'Saving…' : (isToday ? 'Save today' : `Save ${spendDateLabel}`) }}
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-full max-w-xs">
                <FormDatePicker
                    :model-value="spendDate"
                    label="Date"
                    placeholder="Select a date"
                    :max="maxDate"
                    @update:model-value="openDate"
                />
            </div>
            <span class="mb-2 rounded-md border border-border bg-muted/40 px-2.5 py-1 text-sm tabular-nums">
                {{ formatMoney(totalRemaining) }} remaining limit
            </span>
        </div>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">{{ spendDateLabel }}</CardTitle>
                <CardDescription>
                    Pick a date to edit that day’s spend. Month spend is the total of daily spend. Remaining limit is the monthly spending limit minus that total. Set the limit on This month.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="form.errors.marketers" class="mb-3 text-sm text-destructive">{{ form.errors.marketers }}</p>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>Month spend</TableHead>
                            <TableHead>Remaining limit</TableHead>
                            <TableHead class="w-[170px]">Spend (₹)</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rows" :key="row.user_id">
                            <TableCell>
                                <p class="font-medium">{{ row.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ row.email }}</p>
                            </TableCell>
                            <TableCell>
                                <span class="font-mono text-sm text-muted-foreground">{{ row.code }}</span>
                            </TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(monthSpend(row)) }}</TableCell>
                            <TableCell
                                class="tabular-nums font-medium"
                                :class="remainingLimit(row) !== null && remainingLimit(row) < 0 ? 'text-destructive' : ''"
                            >
                                {{ remainingLimit(row) === null ? '—' : formatMoney(remainingLimit(row)) }}
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="row.spend_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="e.g. 800"
                                    class="tabular-nums"
                                    :aria-invalid="Boolean(form.errors[`marketers.${index}.spend_amount`])"
                                />
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="! rows.length">
                            <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                                No users have a referral code yet. Add a code on Users first.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
