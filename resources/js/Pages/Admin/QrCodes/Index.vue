<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import StatCard from '@/Components/Admin/StatCard.vue';

const props = defineProps({
    qrCodes: { type: Object, required: true },
    stats: { type: Object, required: true },
    statusTabs: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    can_create: { type: Boolean, default: false },
    can_sync: { type: Boolean, default: false },
});

const form = reactive({
    status: props.filters.status ?? 'active',
    search: props.filters.search ?? '',
});

const columns = [
    { key: 'name', label: 'Name', sortable: false },
    { key: 'razorpay_qr_code_id', label: 'Razorpay ID', sortable: false },
    { key: 'usage_label', label: 'Usage', sortable: false },
    { key: 'payment_amount', label: 'Amount', sortable: false, align: 'right' },
    { key: 'status_label', label: 'Status', sortable: false },
    { key: 'cause_title', label: 'Cause', sortable: false },
    { key: 'payments_count_received', label: 'Payments', sortable: false, align: 'right' },
    { key: 'payments_amount_received', label: 'Collected', sortable: false, align: 'right' },
    { key: 'created_at', label: 'Created', sortable: false },
    { key: 'actions', label: 'Action', sortable: false, align: 'right' },
];

const rows = computed(() => props.qrCodes.data ?? []);

const formatMoney = (amount) => {
    if (amount === null || amount === undefined) {
        return 'Any';
    }

    return `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const statusTone = (status) => {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700';
    }

    return 'bg-muted text-muted-foreground';
};

const submit = (extra = {}) => {
    router.get('/admin/qr-codes', { ...form, ...extra }, {
        preserveState: true,
        replace: true,
    });
};

const setStatus = (status) => {
    form.status = status;
    submit();
};

const syncAll = () => {
    router.post('/admin/qr-codes/sync', {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="QR Codes" />
    <AdminLayout>
        <template #header>QR Codes</template>

        <PageHeader title="QR Codes" subtitle="Razorpay UPI QR codes for counter and offline collection">
            <template #actions>
                <button
                    v-if="can_sync"
                    type="button"
                    class="admin-btn-secondary !py-2"
                    @click="syncAll"
                >
                    Sync from Razorpay
                </button>
                <Link
                    v-if="can_create"
                    href="/admin/qr-codes/create"
                    class="admin-btn-primary !py-2"
                >
                    Create QR
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <StatCard label="Active" :value="String(stats.active ?? 0)" />
            <StatCard label="Closed" :value="String(stats.closed ?? 0)" />
            <StatCard label="All" :value="String(stats.all ?? 0)" />
        </div>

        <div class="rounded-xl border border-border bg-card shadow-none">
            <div class="flex flex-wrap gap-2 border-b border-border px-4 py-3">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="form.status === tab.key
                        ? 'bg-foreground text-background'
                        : 'bg-muted text-muted-foreground hover:bg-muted'"
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>

            <form class="flex flex-wrap gap-2 border-b border-border px-4 py-3" @submit.prevent="submit()">
                <input
                    v-model="form.search"
                    type="search"
                    class="admin-input min-w-[16rem] flex-1 !py-2"
                    placeholder="Search name or Razorpay id"
                >
                <button type="submit" class="admin-btn-primary !py-2">Search</button>
            </form>

            <div class="p-4">
                <DataTable
                    :columns="columns"
                    :rows="rows"
                    empty-message="No QR codes yet. Create one or sync from Razorpay."
                >
                    <template #cell-name="{ row }">
                        <div class="font-medium text-foreground">{{ row.name }}</div>
                        <div v-if="row.description" class="max-w-[220px] truncate text-xs text-muted-foreground">
                            {{ row.description }}
                        </div>
                    </template>
                    <template #cell-razorpay_qr_code_id="{ row }">
                        <span class="font-mono text-xs">{{ row.razorpay_qr_code_id }}</span>
                    </template>
                    <template #cell-payment_amount="{ row }">
                        {{ row.fixed_amount ? formatMoney(row.payment_amount) : 'Any' }}
                    </template>
                    <template #cell-status_label="{ row }">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusTone(row.status)">
                            {{ row.status_label }}
                        </span>
                    </template>
                    <template #cell-cause_title="{ row }">
                        <div v-if="row.cause_title" class="text-sm">
                            <div class="font-medium">{{ row.cause_title }}</div>
                            <div v-if="row.package_title" class="text-xs text-muted-foreground">{{ row.package_title }}</div>
                        </div>
                        <span v-else class="text-muted-foreground">—</span>
                    </template>
                    <template #cell-payments_amount_received="{ row }">
                        {{ formatMoney(row.payments_amount_received) }}
                    </template>
                    <template #cell-actions="{ row }">
                        <Link :href="`/admin/qr-codes/${row.uuid}`" class="text-sm font-medium hover:underline">
                            Details
                        </Link>
                    </template>
                    <template #footer>
                        <Pagination :links="qrCodes.links" :meta="qrCodes.meta" />
                    </template>
                </DataTable>
            </div>
        </div>
    </AdminLayout>
</template>
