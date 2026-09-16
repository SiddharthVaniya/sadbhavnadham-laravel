<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import PaymentMethodPieChart from '@/Components/Admin/PaymentMethodPieChart.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';
import { mergeDurationOptions } from '@/utils/periodOptions';

const page = usePage();
const canManageDonations = computed(() => page.props.auth.permissions?.includes('manage donations') ?? false);

const props = defineProps({
    donations: { type: Object, required: true },
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    overviewDateLabel: { type: String, required: true },
    paidAmount: { type: Number, required: true },
    attemptVolume: { type: Number, required: true },
    failedCount: { type: Number, required: true },
    totalCount: { type: Number, required: true },
    statusCounts: { type: Object, default: () => ({}) },
    paymentMethodBreakdown: { type: Array, default: () => [] },
    activeFilterCount: { type: Number, default: 0 },
    providerOptions: { type: Array, default: () => [] },
    sourceOptions: { type: Array, default: () => [] },
    platformOptions: { type: Array, default: () => [] },
    campaignOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    causes: { type: Array, default: () => [] },
    packages: { type: Array, default: () => [] },
    sort: { type: String, default: 'created_at_ts' },
    dir: { type: String, default: 'desc' },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    duration: props.filters.from_date && props.filters.to_date ? 'custom' : props.duration,
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    status: props.filters.status ?? '',
    provider: props.filters.provider ?? '',
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

const statusTabs = computed(() => [
    { key: '', label: 'All', count: props.totalCount },
    { key: 'paid', label: 'Paid', count: props.statusCounts.paid ?? 0 },
    { key: 'pending', label: 'Pending', count: props.statusCounts.pending ?? 0 },
    { key: 'failed', label: 'Failed', count: props.statusCounts.failed ?? 0 },
]);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const isCustomRange = computed(() => form.duration === 'custom');
const periodOptions = computed(() => mergeDurationOptions(props.durationOptions));

const listQueryParams = (extra = {}) => {
    const payload = { ...form, ...extra };

    if (String(payload.search || '').trim() !== '' && payload.duration !== 'custom' && ! payload.from_date && ! payload.to_date) {
        payload.duration = 'all';
    }

    if (payload.duration !== 'custom') {
        delete payload.from_date;
        delete payload.to_date;
    }

    const params = {};
    Object.entries(payload).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params[key] = String(value);
        }
    });

    return params;
};

const seedCustomDates = () => {
    if (form.from_date || form.to_date) {
        return;
    }

    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    form.from_date = start.toISOString().slice(0, 10);
    form.to_date = now.toISOString().slice(0, 10);
};

const onDurationChange = () => {
    if (form.duration === 'custom') {
        seedCustomDates();
        return;
    }

    form.from_date = '';
    form.to_date = '';
    submit();
};

const submit = (extra = {}) => {
    const params = listQueryParams(extra);
    form.duration = params.duration ?? form.duration;

    router.get('/admin/donations', params, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    router.get('/admin/donations', { duration: 'today' });
};

const setStatus = (status) => {
    form.status = status;
    submit();
};

const toggleSort = (key) => {
    if (form.sort === key) {
        form.dir = form.dir === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = key;
        form.dir = key === 'created_at_ts' ? 'desc' : 'asc';
    }

    submit();
};

const currentListPath = computed(() => {
    const params = new URLSearchParams(listQueryParams({
        page: props.donations.meta?.current_page || 1,
    }));
    const query = params.toString();

    return query ? `/admin/donations?${query}` : '/admin/donations';
});

const detailHref = (row) => {
    const params = new URLSearchParams({ return: currentListPath.value });

    return `/admin/donations/${row.uuid}?${params.toString()}`;
};

const donationColumns = [
    { key: 'payment_id', label: 'Donation', sortable: true },
    { key: 'donor_name', label: 'Donor', sortable: true },
    { key: 'source', label: 'Source', sortable: true },
    { key: 'cause', label: 'Cause', sortable: true },
    { key: 'cause_title', label: 'Cause title', sortable: true },
    { key: 'total_amount', label: 'Amount', sortable: true, align: 'right' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'city', label: 'City', sortable: true },
    { key: 'created_at_ts', label: 'Created', sortable: true },
    { key: 'actions', label: 'Action', sortable: false, align: 'right' },
];

const donationsList = computed(() => props.donations.data ?? []);

const exportUrl = computed(() => {
    const params = new URLSearchParams(listQueryParams());
    const query = params.toString();

    return query ? `/admin/donations/export?${query}` : '/admin/donations/export';
});
</script>

<template>
    <Head title="Donations" />

    <AdminLayout>
        <template #header>Donations</template>

        <PageHeader compact :title="`Donations · ${overviewDateLabel}`" :subtitle="`${totalCount.toLocaleString()} transactions`">
            <template #actions>
                <a
                    :href="exportUrl"
                    class="admin-btn-secondary !py-2"
                    title="Export filtered donations as CSV"
                >
                    Export CSV
                </a>
                <select
                    v-model="form.duration"
                    class="admin-input !w-auto min-w-[180px] py-2"
                    @change="onDurationChange"
                >
                    <option v-for="(label, value) in periodOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-4 lg:col-span-2 shadow-none">
                <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Paid collection</div>
                <div class="mt-1 text-2xl font-semibold text-foreground">{{ formatMoney(paidAmount) }}</div>
                <p class="text-xs text-muted-foreground">Successful donations in current filter</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-muted px-2.5 py-1 text-foreground">Attempts: {{ formatMoney(attemptVolume) }}</span>
                    <span class="rounded-full bg-muted px-2.5 py-1 text-foreground">{{ totalCount }} transaction(s)</span>
                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">{{ failedCount }} failed</span>
                </div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="mb-2 text-sm font-semibold text-foreground">Payment methods</div>
                <PaymentMethodPieChart :items="paymentMethodBreakdown" />
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card shadow-none">
            <div class="flex flex-wrap gap-2 border-b border-border px-4 py-3">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key || 'all'"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="(form.status || '') === tab.key
                        ? 'bg-foreground text-background'
                        : 'bg-muted text-muted-foreground hover:bg-muted'"
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>

            <form class="grid gap-2 border-b border-border px-4 py-3 md:grid-cols-12" @submit.prevent="submit()">
                <div v-if="isCustomRange" class="md:col-span-2">
                    <FormDatePicker v-model="form.from_date" label="From" placeholder="From date" />
                </div>
                <div v-if="isCustomRange" class="md:col-span-2">
                    <FormDatePicker v-model="form.to_date" label="To" placeholder="To date" />
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Provider</label>
                    <select v-model="form.provider" class="admin-input !py-2">
                        <option value="">All providers</option>
                        <option v-for="option in providerOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
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
                    <input v-model="form.search" type="text" class="admin-input !py-2" placeholder="Search: donor, email, phone, payment id, receipt, UTM">
                </div>
                <div class="flex flex-wrap items-center gap-2 md:col-span-12">
                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                    <span v-if="activeFilterCount" class="text-xs text-muted-foreground">{{ activeFilterCount }} active filter(s)</span>
                    <Link
                        v-if="canManageDonations"
                        href="/admin/donations/create"
                        class="ml-auto rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                    >
                        Record offline
                    </Link>
                </div>
            </form>

            <div class="p-4">
                <DataTable
                    :columns="donationColumns"
                    :rows="donationsList"
                    :sort-key="form.sort"
                    :sort-dir="form.dir"
                    empty-message="No donations found for the current filters."
                    @sort="toggleSort"
                >
                    <template #cell-payment_id="{ row }">
                        <div class="font-medium text-foreground">#{{ row.payment_id }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.provider }}</div>
                    </template>
                    <template #cell-donor_name="{ row }">
                        <div class="font-medium">{{ row.donor_name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.donor_email }}</div>
                    </template>
                    <template #cell-source="{ row }">
                        <div class="font-medium text-foreground">{{ row.source }}</div>
                        <div v-if="row.utm_campaign" class="max-w-[160px] truncate text-xs text-muted-foreground" :title="row.utm_campaign">
                            {{ row.utm_campaign }}
                        </div>
                    </template>
                    <template #cell-cause_title="{ row }">
                        <div class="max-w-[280px] font-medium leading-snug text-foreground" :title="row.cause_title">
                            {{ row.cause_title || '—' }}
                        </div>
                    </template>
                    <template #cell-total_amount="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                    <template #cell-created_at_ts="{ row }">
                        <div>{{ row.created_date }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.created_time }}</div>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link :href="detailHref(row)" class="text-sm font-medium hover:underline">Details</Link>
                            <Link
                                v-if="canManageDonations && row.edit_url"
                                :href="row.edit_url"
                                class="text-sm font-medium hover:underline"
                            >
                                Edit
                            </Link>
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
