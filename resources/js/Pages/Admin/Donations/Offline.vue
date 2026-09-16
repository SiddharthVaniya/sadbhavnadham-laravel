<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import { useClientSort } from '@/composables/useClientSort';

const props = defineProps({
    donations: { type: Object, required: true },
    causes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canManageReceipts = computed(() => page.props.auth.permissions?.includes('manage receipts') ?? false);
const canManageDonations = computed(() => page.props.auth.permissions?.includes('manage donations') ?? false);

const form = reactive({
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    search: props.filters.search ?? '',
    cause_id: props.filters.cause_id ?? '',
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const receiptTone = (label) => {
    if (label === 'Sent') {
        return 'text-emerald-700 bg-emerald-50';
    }

    if (label === 'Failed') {
        return 'text-rose-700 bg-rose-50';
    }

    return 'text-amber-700 bg-amber-50';
};

const submit = () => {
    router.get('/admin/donations/offline', { ...form }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    router.get('/admin/donations/offline');
};

const resendReceipt = (url) => {
    router.post(url, {}, { preserveScroll: true });
};

const columns = [
    { key: 'receipt_number', label: 'Receipt #', sortable: true },
    { key: 'donor_name', label: 'Donor', sortable: true },
    { key: 'cause', label: 'Cause', sortable: true },
    { key: 'cause_title', label: 'Cause title', sortable: true },
    { key: 'total_amount', label: 'Amount', sortable: true, align: 'right' },
    { key: 'receipt_email_label', label: 'Receipt email', sortable: true },
    { key: 'created_at_ts', label: 'Date', sortable: true },
    { key: 'actions', label: 'Action', sortable: false, align: 'right' },
];

const donationsList = computed(() => props.donations.data ?? []);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(donationsList, { key: 'created_at_ts', dir: 'desc' });

const exportUrl = computed(() => {
    const params = new URLSearchParams({ provider: 'offline', duration: 'all' });
    Object.entries(form).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.set(key, String(value));
        }
    });

    return `/admin/donations/export?${params.toString()}`;
});
</script>

<template>
    <Head title="Offline Donations" />

    <AdminLayout>
        <template #header>Offline donations</template>

        <PageHeader
            title="Offline donations history"
            subtitle="Manual / offline payment records"
        >
            <template #actions>
                <a :href="exportUrl" class="admin-btn-secondary !py-2">Export CSV</a>
                <Link
                    v-if="canManageDonations"
                    href="/admin/donations/create"
                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    Record offline donation
                </Link>
            </template>
        </PageHeader>

        <div class="rounded-xl border border-border bg-card shadow-none">
            <form class="grid gap-3 border-b border-border p-4 md:grid-cols-6" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">From date</label>
                    <input v-model="form.from_date" type="date" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">To date</label>
                    <input v-model="form.to_date" type="date" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Search donor</label>
                    <input
                        v-model="form.search"
                        type="text"
                        class="w-full rounded-lg border border-border px-3 py-2 text-sm"
                        placeholder="Name, email or phone…"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Cause</label>
                    <select v-model="form.cause_id" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                        <option value="">All causes</option>
                        <option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.title }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 rounded-lg bg-foreground px-3 py-2 text-sm font-medium text-background hover:bg-foreground/90">Search</button>
                    <button type="button" class="flex-1 rounded-lg border border-border px-3 py-2 text-sm text-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                </div>
            </form>

            <div class="p-4">
                <DataTable :columns="columns" :rows="sortedRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
                    <template #cell-donor_name="{ row }">
                        <div class="font-medium">{{ row.donor_name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.donor_email }}</div>
                    </template>
                    <template #cell-cause_title="{ row }">
                        <div class="max-w-[280px] font-medium leading-snug text-foreground" :title="row.cause_title">
                            {{ row.cause_title || '—' }}
                        </div>
                    </template>
                    <template #cell-total_amount="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    <template #cell-receipt_email_label="{ row }">
                        <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium" :class="receiptTone(row.receipt_email_label)">{{ row.receipt_email_label }}</span>
                    </template>
                    <template #cell-created_at_ts="{ row }">
                        <div>{{ row.created_date }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.created_time }}</div>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Link
                                v-if="canManageDonations"
                                :href="row.edit_url"
                                class="text-sm font-medium hover:underline"
                            >
                                Edit
                            </Link>
                            <template v-if="canManageReceipts">
                                <a :href="row.receipt_preview_url" target="_blank" class="text-xs font-medium hover:underline">Preview</a>
                                <button type="button" class="text-xs font-medium hover:underline" @click="resendReceipt(row.receipt_resend_url)">Resend</button>
                            </template>
                            <Link :href="`/admin/donations/${row.uuid}`" class="text-sm font-medium hover:underline">View</Link>
                        </div>
                    </template>
                    <template #footer>
                        <Pagination :links="donations.links" :meta="donations.meta" />
                    </template>
                </DataTable>
            </div>
        </div>
    </AdminLayout>
</template>
