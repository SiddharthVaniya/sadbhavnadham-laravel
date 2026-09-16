<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    can_view_all: { type: Boolean, default: false },
    duration: { type: Object, required: true },
    summary: { type: Object, required: true },
    generated_at: { type: String, default: '' },
    partners: { type: Array, default: () => [] },
    filter_options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    durationOptions: { type: Object, required: true },
    active_filter_count: { type: Number, default: 0 },
});

const form = reactive({
    duration: props.filters.duration || props.duration.key || 'today',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    state: props.filters.state || '',
    partner_user_id: props.filters.partner_user_id ? String(props.filters.partner_user_id) : '',
    code: props.filters.code || '',
    search: props.filters.search || '',
});

watch(
    () => props.filters,
    (next) => {
        form.duration = next.duration || 'today';
        form.from_date = next.from_date || '';
        form.to_date = next.to_date || '';
        form.state = next.state || '';
        form.partner_user_id = next.partner_user_id ? String(next.partner_user_id) : '';
        form.code = next.code || '';
        form.search = next.search || '';
    },
    { deep: true },
);

const isCustomRange = computed(() => form.duration === 'custom');

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const buildQuery = (overrides = {}) => {
    const payload = {
        duration: overrides.duration ?? form.duration,
        from_date: overrides.from_date ?? form.from_date,
        to_date: overrides.to_date ?? form.to_date,
        state: overrides.state ?? form.state,
        partner_user_id: overrides.partner_user_id ?? form.partner_user_id,
        code: overrides.code !== undefined ? overrides.code : form.code,
        search: overrides.search ?? form.search,
    };

    if (payload.duration !== 'custom') {
        delete payload.from_date;
        delete payload.to_date;
    }

    Object.keys(payload).forEach((key) => {
        if (payload[key] === '' || payload[key] === null || payload[key] === undefined) {
            delete payload[key];
        }
    });

    return payload;
};

const applyFilters = (overrides = {}) => {
    router.get('/admin/partner-reports', buildQuery(overrides), {
        preserveState: true,
        replace: true,
    });
};

const onDurationChange = () => {
    if (form.duration === 'custom' && !form.from_date && !form.to_date) {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        form.from_date = start.toISOString().slice(0, 10);
        form.to_date = now.toISOString().slice(0, 10);
        return;
    }

    if (form.duration !== 'custom') {
        form.from_date = '';
        form.to_date = '';
    }

    applyFilters();
};

const onPartnerChange = () => {
    if (form.partner_user_id) {
        const partner = (props.filter_options.partners || []).find(
            (row) => String(row.id) === String(form.partner_user_id),
        );
        form.code = partner?.code || '';
    } else {
        form.code = '';
    }

    applyFilters();
};

const resetFilters = () => {
    form.duration = 'today';
    form.from_date = '';
    form.to_date = '';
    form.state = '';
    form.partner_user_id = '';
    form.code = '';
    form.search = '';
    applyFilters({
        duration: 'today',
        from_date: '',
        to_date: '',
        state: '',
        partner_user_id: '',
        code: '',
        search: '',
    });
};

const previewColumns = [
    { key: 'col_0', label: 'Partner', sortable: false },
    { key: 'col_1', label: 'SID / Referral code', sortable: false },
    { key: 'col_2', label: 'Orders', sortable: false, align: 'right' },
    { key: 'col_3', label: 'Collected', sortable: false, align: 'right' },
    { key: 'col_4', label: 'Details', sortable: false, align: 'right' },
];

const previewRows = computed(() =>
    (props.partners || []).map((row) => ({
        col_0: row.name,
        col_1: row.code,
        col_2: row.paid_orders,
        col_3: formatMoney(row.revenue),
        col_4: row.referrals_href,
        referrals_href: row.referrals_href,
    })),
);
</script>

<template>
    <Head title="Partner Reports" />

    <AdminLayout>
        <template #header>Partner Reports</template>

        <PageHeader
            title="Partner referrals performance"
            :subtitle="`Paid donations · ${duration.label}`"
        >
            <template #actions>
                <Link href="/admin/referrals" class="admin-btn-secondary !py-2">
                    Donation-level attribution
                </Link>
                <button
                    v-if="active_filter_count > 0"
                    type="button"
                    class="admin-btn-secondary !py-2"
                    @click="resetFilters"
                >
                    Reset filters
                </button>
            </template>
        </PageHeader>

        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-none">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="admin-label">Period</label>
                    <select v-model="form.duration" class="admin-input" @change="onDurationChange">
                        <option
                            v-for="(label, value) in durationOptions"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div v-if="isCustomRange">
                    <label class="admin-label">From date</label>
                    <input
                        v-model="form.from_date"
                        type="date"
                        class="admin-input"
                        @change="applyFilters()"
                    >
                </div>

                <div v-if="isCustomRange">
                    <label class="admin-label">To date</label>
                    <input
                        v-model="form.to_date"
                        type="date"
                        class="admin-input"
                        @change="applyFilters()"
                    >
                </div>

                <div>
                    <label class="admin-label">State</label>
                    <select v-model="form.state" class="admin-input" @change="applyFilters()">
                        <option value="">All states</option>
                        <option
                            v-for="state in filter_options.states || []"
                            :key="state"
                            :value="state"
                        >
                            {{ state }}
                        </option>
                    </select>
                </div>

                <div v-if="can_view_all">
                    <label class="admin-label">SID code (partner name)</label>
                    <select
                        v-model="form.partner_user_id"
                        class="admin-input"
                        @change="onPartnerChange"
                    >
                        <option value="">All partners</option>
                        <option
                            v-for="partner in filter_options.partners || []"
                            :key="partner.id"
                            :value="String(partner.id)"
                        >
                            {{ partner.name }} ({{ partner.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">Search partner / SID</label>
                    <input
                        v-model="form.search"
                        type="search"
                        class="admin-input"
                        placeholder="Name, email, or code"
                        @keydown.enter.prevent="applyFilters()"
                    >
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" class="admin-btn-primary !py-2" @click="applyFilters()">
                    Apply filters
                </button>
                <p class="text-sm text-muted-foreground">
                    Partner reports include paid donations only, grouped by partner SID / referral code.
                    Choose custom range to pick your own start and end dates.
                </p>
            </div>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Grand total</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ formatMoney(summary.total_revenue) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Rows</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ previewRows.length }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Generated</p>
                <p class="mt-1 text-base font-medium text-foreground">
                    {{ generated_at }}
                </p>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-2 text-sm text-muted-foreground">
            <span>{{ summary.total_orders }} order{{ summary.total_orders === 1 ? '' : 's' }}</span>
            <span>·</span>
            <span>{{ summary.active_partners }} active partner{{ summary.active_partners === 1 ? '' : 's' }}</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <DataTable
                :columns="previewColumns"
                :rows="previewRows"
                empty-message="No partners found for this report."
            >
                <template #cell-col_1="{ row }">
                    <span class="font-mono text-sm text-muted-foreground">{{ row.col_1 }}</span>
                </template>
                <template #cell-col_2="{ row }">
                    <span class="tabular-nums">{{ row.col_2 }}</span>
                </template>
                <template #cell-col_3="{ row }">
                    <span class="font-medium tabular-nums">{{ row.col_3 }}</span>
                </template>
                <template #cell-col_4="{ row }">
                    <Link
                        :href="row.referrals_href"
                        class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline"
                    >
                        Details
                    </Link>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
