<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
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
    yearMonth: { type: String, required: true },
    yearMonthLabel: { type: String, required: true },
    marketers: { type: Array, default: () => [] },
});

const rows = reactive(
    props.marketers.map((marketer) => ({
        user_id: marketer.user_id,
        name: marketer.name,
        email: marketer.email,
        code: marketer.code,
        target_amount: marketer.target_amount ?? '',
        spend_amount: marketer.spend_amount ?? '',
    })),
);

watch(
    () => props.marketers,
    (next) => {
        rows.splice(
            0,
            rows.length,
            ...next.map((marketer) => ({
                user_id: marketer.user_id,
                name: marketer.name,
                email: marketer.email,
                code: marketer.code,
                target_amount: marketer.target_amount ?? '',
                spend_amount: marketer.spend_amount ?? '',
            })),
        );
    },
    { deep: true },
);

const form = useForm({
    marketers: [],
});

const totalTarget = computed(() =>
    rows.reduce((sum, row) => sum + (Number(row.target_amount) || 0), 0),
);
const totalSpend = computed(() =>
    rows.reduce((sum, row) => sum + (Number(row.spend_amount) || 0), 0),
);

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const save = () => {
    form.marketers = rows.map((row) => ({
        user_id: row.user_id,
        target_amount: row.target_amount === '' || row.target_amount === null
            ? null
            : Number(row.target_amount),
        spend_amount: row.spend_amount === '' || row.spend_amount === null
            ? 0
            : Number(row.spend_amount),
    }));

    form.put('/admin/marketers', { preserveScroll: true });
};
</script>

<template>
    <Head title="Marketers" />
    <AdminLayout>
        <template #header>Marketers</template>

        <PageHeader
            title="Marketers"
            :subtitle="`Edit this month target and ad spend · ${yearMonthLabel}`"
        >
            <template #actions>
                <Button type="button" :disabled="form.processing || ! rows.length" @click="save">
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap gap-2 text-sm">
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                {{ rows.length }} marketer{{ rows.length === 1 ? '' : 's' }}
            </span>
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                {{ formatMoney(totalTarget) }} total target
            </span>
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                {{ formatMoney(totalSpend) }} total spend
            </span>
            <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 font-mono text-muted-foreground">
                {{ yearMonth }}
            </span>
        </div>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">This month budgets</CardTitle>
                <CardDescription>
                    Anyone with a referral code appears here. Values apply to the current calendar month only.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="form.errors.marketers" class="mb-3 text-sm text-destructive">{{ form.errors.marketers }}</p>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead class="w-[180px]">This month target (₹)</TableHead>
                            <TableHead class="w-[180px]">This month spend (₹)</TableHead>
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
                            <TableCell>
                                <Input
                                    v-model="row.target_amount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="e.g. 50000"
                                    class="tabular-nums"
                                    :aria-invalid="Boolean(form.errors[`marketers.${index}.target_amount`])"
                                />
                                <p
                                    v-if="form.errors[`marketers.${index}.target_amount`]"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors[`marketers.${index}.target_amount`] }}
                                </p>
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="row.spend_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="e.g. 12000"
                                    class="tabular-nums"
                                    :aria-invalid="Boolean(form.errors[`marketers.${index}.spend_amount`])"
                                />
                                <p
                                    v-if="form.errors[`marketers.${index}.spend_amount`]"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors[`marketers.${index}.spend_amount`] }}
                                </p>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="! rows.length">
                            <TableCell colspan="4" class="py-10 text-center text-muted-foreground">
                                No users have a referral code yet. Add a code on Users first.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="rows.length" class="mt-4 flex justify-end">
                    <Button type="button" :disabled="form.processing" @click="save">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
