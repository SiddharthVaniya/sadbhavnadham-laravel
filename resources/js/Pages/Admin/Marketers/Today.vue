<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MarketerBudgetTabs from '@/Components/Admin/MarketerBudgetTabs.vue';
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
    editingYesterday: { type: Boolean, default: false },
    spendDate: { type: String, required: true },
    spendDateLabel: { type: String, required: true },
    yearMonthLabel: { type: String, required: true },
    marketers: { type: Array, default: () => [] },
});

const blank = (marketer) => ({
    user_id: marketer.user_id,
    name: marketer.name,
    email: marketer.email,
    code: marketer.code,
    month_target_amount: marketer.month_target_amount,
    month_spend_amount: marketer.month_spend_amount,
    limit_amount: marketer.limit_amount ?? '',
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

const totalLimit = computed(() =>
    rows.reduce((sum, row) => sum + (Number(row.limit_amount) || 0), 0),
);

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const save = () => {
    form.spend_date = props.spendDate;
    form.marketers = rows.map((row) => ({
        user_id: row.user_id,
        limit_amount: row.limit_amount === '' || row.limit_amount === null
            ? null
            : Number(row.limit_amount),
        spend_amount: row.spend_amount === '' || row.spend_amount === null
            ? 0
            : Number(row.spend_amount),
    }));

    form.put('/admin/marketers/today', { preserveScroll: true });
};
</script>

<template>
    <Head title="Today spending limit" />
    <AdminLayout>
        <template #header>Marketers</template>

        <PageHeader
            :title="editingYesterday ? 'Yesterday spending limit' : 'Today spending limit'"
            :subtitle="editingYesterday
                ? `Correct yesterday’s limit and spend · ${spendDateLabel}`
                : `Main admin sets today’s ad spend cap · ${spendDateLabel} · month target is ${yearMonthLabel}`"
        >
            <template #actions>
                <Button v-if="editingYesterday" as-child variant="outline">
                    <Link href="/admin/marketers/today">Back to today</Link>
                </Button>
                <Button v-else as-child variant="outline">
                    <Link href="/admin/marketers/today?day=yesterday">Edit yesterday</Link>
                </Button>
                <Button type="button" :disabled="form.processing || ! rows.length" @click="save">
                    {{ form.processing ? 'Saving…' : (editingYesterday ? 'Save yesterday' : 'Save today’s limits') }}
                </Button>
            </template>
        </PageHeader>

        <MarketerBudgetTabs current="today" />

        <div class="mb-4 flex flex-wrap gap-2 text-sm">
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                {{ formatMoney(totalLimit) }} total daily limit
            </span>
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-muted-foreground">
                {{ spendDate }}
            </span>
        </div>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">{{ editingYesterday ? 'Yesterday' : 'Today' }}</CardTitle>
                <CardDescription>
                    {{ editingYesterday
                        ? 'These values are saved on yesterday’s date and counted in that month’s spend.'
                        : 'This month’s target is shown for reference. Use Edit yesterday if a day’s limit or spend was missed.' }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="form.errors.marketers" class="mb-3 text-sm text-destructive">{{ form.errors.marketers }}</p>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>This month target</TableHead>
                            <TableHead>This month spend</TableHead>
                            <TableHead class="w-[170px]">{{ editingYesterday ? 'Yesterday' : 'Today' }} limit (₹)</TableHead>
                            <TableHead class="w-[170px]">{{ editingYesterday ? 'Yesterday' : 'Today' }} spend (₹)</TableHead>
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
                            <TableCell class="tabular-nums">{{ formatMoney(row.month_target_amount) }}</TableCell>
                            <TableCell class="tabular-nums">{{ formatMoney(row.month_spend_amount) }}</TableCell>
                            <TableCell>
                                <Input
                                    v-model="row.limit_amount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="e.g. 2000"
                                    class="tabular-nums"
                                    :aria-invalid="Boolean(form.errors[`marketers.${index}.limit_amount`])"
                                />
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
                            <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                No users have a referral code yet. Add a code on Users first.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
