<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SubscriptionStatusBadge from '@/Components/Admin/SubscriptionStatusBadge.vue';

const props = defineProps({
    subscriptions: { type: Object, required: true },
    stats: { type: Object, required: true },
    statusTabs: { type: Array, default: () => [] },
    causes: { type: Array, default: () => [] },
    packages: { type: Array, default: () => [] },
    sourceOptions: { type: Array, default: () => [] },
    platformOptions: { type: Array, default: () => [] },
    campaignOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    activeFilterCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'created_at_ts' },
    dir: { type: String, default: 'desc' },
});

const form = reactive({
    status: props.filters.status ?? 'live',
    cause_id: props.filters.cause_id ?? '',
    package_id: props.filters.package_id ?? '',
    cause_title: props.filters.cause_title ?? '',
    search: props.filters.search ?? '',
    source: props.filters.source ?? '',
    platform: props.filters.platform ?? '',
    utm_campaign: props.filters.utm_campaign ?? '',
    utm_content: props.filters.utm_content ?? '',
    sort: props.sort ?? 'created_at_ts',
    dir: props.dir ?? 'desc',
});

const filteredPackages = computed(() => {
    if (!form.cause_id) {
        return props.packages;
    }

    return props.packages.filter((pkg) => String(pkg.cause_id) === String(form.cause_id));
});

const filteredCauseTitles = computed(() => {
    const seen = new Set();

    return filteredPackages.value.filter((pkg) => {
        const title = String(pkg.title || '').trim();
        if (!title || seen.has(title.toLowerCase())) {
            return false;
        }
        seen.add(title.toLowerCase());

        return true;
    });
});

watch(() => form.cause_id, () => {
    if (form.package_id && !filteredPackages.value.some((pkg) => String(pkg.id) === String(form.package_id))) {
        form.package_id = '';
    }

    if (form.cause_title && !filteredCauseTitles.value.some((pkg) => String(pkg.title) === String(form.cause_title))) {
        form.cause_title = '';
    }
});

const columns = [
    { key: 'donor_name', label: 'Subscriber', sortable: true },
    { key: 'source', label: 'Source', sortable: false },
    { key: 'cause', label: 'Cause / Package', sortable: true },
    { key: 'total_amount', label: 'Amount', sortable: true, align: 'right' },
    { key: 'frequency_label', label: 'Frequency', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'billing_cycle_count', label: 'Cycles', sortable: true, align: 'right' },
    { key: 'next_charge_at', label: 'Next charge', sortable: true },
    { key: 'created_at_ts', label: 'Created', sortable: true },
    { key: 'actions', label: '', sortable: false, align: 'right' },
];

const rows = computed(() => props.subscriptions.data ?? []);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;

const listQueryParams = (extra = {}) => {
    const payload = { ...form, ...extra };
    const params = {};

    Object.entries(payload).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params[key] = String(value);
        }
    });

    return params;
};

const submit = (extra = {}) => {
    router.get('/admin/subscriptions', listQueryParams(extra), {
        preserveState: true,
        replace: true,
    });
};

const setStatus = (status) => {
    form.status = status;
    submit();
};

const resetFilters = () => {
    router.get('/admin/subscriptions', { status: 'live' });
};

const toggleSort = (key) => {
    if (form.sort === key) {
        form.dir = form.dir === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = key;
        form.dir = key === 'created_at_ts' || key === 'next_charge_at' || key === 'total_amount' || key === 'billing_cycle_count'
            ? 'desc'
            : 'asc';
    }

    submit();
};

const exportUrl = computed(() => {
    const params = new URLSearchParams(listQueryParams());
    const query = params.toString();

    return query ? `/admin/subscriptions/export?${query}` : '/admin/subscriptions/export';
});
</script>

<template>
    <Head title="Subscriptions" />
    <AdminLayout>
        <template #header>Subscriptions</template>

        <PageHeader
            compact
            title="Monthly subscriptions"
            :subtitle="`${stats.filtered_count.toLocaleString()} shown · ${stats.live.toLocaleString()} live`"
        >
            <template #actions>
                <a :href="exportUrl" class="admin-btn-secondary !py-2">Export CSV</a>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard tone="green" label="Live subscriptions" :value="stats.live" />
            <StatCard label="Active" :value="stats.active" />
            <StatCard tone="orange" label="Halted" :value="stats.halted" />
            <StatCard label="Monthly value (live)" :value="formatMoney(stats.monthly_value)" />
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

            <form class="grid gap-2 border-b border-border px-4 py-3 md:grid-cols-12" @submit.prevent="submit()">
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Cause</label>
                    <select v-model="form.cause_id" class="admin-input !py-2">
                        <option value="">All causes</option>
                        <option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.title }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Package</label>
                    <select v-model="form.package_id" class="admin-input !py-2">
                        <option value="">All packages</option>
                        <option v-for="pkg in filteredPackages" :key="pkg.id" :value="pkg.id">{{ pkg.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Cause title</label>
                    <select v-model="form.cause_title" class="admin-input !py-2" @change="submit()">
                        <option value="">All cause titles</option>
                        <option v-for="pkg in filteredCauseTitles" :key="`title-${pkg.id}`" :value="pkg.title">{{ pkg.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Source</label>
                    <select v-model="form.source" class="admin-input !py-2">
                        <option value="">All sources</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Platform</label>
                    <select v-model="form.platform" class="admin-input !py-2" :disabled="form.source !== '' && form.source !== 'meta'">
                        <option value="">All platforms</option>
                        <option v-for="option in platformOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p class="mt-1 text-[11px] leading-snug text-muted-foreground">
                        Facebook / Instagram when source is Meta.
                    </p>
                </div>
                <div class="md:col-span-3">
                    <label class="admin-label !mb-1 !text-xs">Campaign (utm_campaign)</label>
                    <select v-model="form.utm_campaign" class="admin-input !py-2">
                        <option value="">All campaigns</option>
                        <option v-for="campaign in campaignOptions" :key="campaign" :value="campaign">{{ campaign }}</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="admin-label !mb-1 !text-xs">Employee (utm_content)</label>
                    <select v-model="form.utm_content" class="admin-input !py-2">
                        <option value="">All employees</option>
                        <option v-for="employee in employeeOptions" :key="employee" :value="employee">{{ employee }}</option>
                    </select>
                </div>
                <div class="flex items-end md:col-span-2">
                    <button type="submit" class="admin-btn-primary w-full !py-2">Apply</button>
                </div>
                <div class="md:col-span-6">
                    <label class="admin-label !mb-1 !text-xs">Search</label>
                    <input
                        v-model="form.search"
                        type="text"
                        class="admin-input !py-2"
                        placeholder="Search: donor, email, phone, Razorpay id, UTM"
                    >
                </div>
                <div class="flex flex-wrap items-center gap-2 md:col-span-12">
                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="resetFilters">
                        Reset
                    </button>
                    <span v-if="activeFilterCount" class="text-xs text-muted-foreground">{{ activeFilterCount }} active filter(s)</span>
                </div>
            </form>

            <div class="p-4">
                <DataTable
                    :columns="columns"
                    :rows="rows"
                    :sort-key="form.sort"
                    :sort-dir="form.dir"
                    empty-message="No subscriptions found for the current filters."
                    @sort="toggleSort"
                >
                    <template #cell-donor_name="{ row }">
                        <div class="font-medium text-foreground">{{ row.donor_name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.donor_email }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.donor_phone }}</div>
                    </template>
                    <template #cell-source="{ row }">
                        <div class="font-medium text-foreground">{{ row.source }}</div>
                        <div v-if="row.utm_campaign" class="max-w-[160px] truncate text-xs text-muted-foreground" :title="row.utm_campaign">
                            {{ row.utm_campaign }}
                        </div>
                    </template>
                    <template #cell-cause="{ row }">
                        <div class="font-medium">{{ row.cause }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.package }}</div>
                    </template>
                    <template #cell-total_amount="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    <template #cell-status="{ row }">
                        <SubscriptionStatusBadge :status="row.status" :label="row.status_label" />
                    </template>
                    <template #cell-billing_cycle_count="{ row }">{{ row.billing_cycle_count }}</template>
                    <template #cell-next_charge_at="{ row }">{{ row.next_charge_at || '—' }}</template>
                    <template #cell-created_at_ts="{ row }">{{ row.created_at }}</template>
                    <template #cell-actions="{ row }">
                        <Link :href="`/admin/subscriptions/${row.uuid}`" class="text-sm font-medium hover:underline">Details</Link>
                    </template>
                    <template #footer>
                        <Pagination :links="subscriptions.links" :meta="subscriptions.meta" />
                    </template>
                </DataTable>
            </div>
        </div>
    </AdminLayout>
</template>
