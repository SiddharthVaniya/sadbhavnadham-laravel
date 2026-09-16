<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    type: { type: String, required: true },
    duration: { type: String, required: true },
    reportTypes: { type: Object, required: true },
    durationOptions: { type: Object, required: true },
    causeMatchOptions: { type: Object, default: () => ({ '': 'All donation', match: 'Match cause', mismatch: 'Mismatch cause' }) },
    formats: { type: Array, default: () => ['csv', 'xlsx', 'pdf'] },
    causes: { type: Array, default: () => [] },
    filterOptions: { type: Object, default: () => ({ states: [], partners: [], sources: [] }) },
    filters: { type: Object, default: () => ({}) },
    report: { type: Object, required: true },
});

const selectedType = ref(props.type);
const selectedDuration = ref(
    props.filters.from_date && props.filters.to_date ? 'custom' : props.duration,
);
const fromDate = ref(props.filters.from_date || '');
const toDate = ref(props.filters.to_date || '');
const causeId = ref(props.filters.cause_id ? String(props.filters.cause_id) : '');
const state = ref(props.filters.state || '');
const source = ref(props.filters.source || '');
const causeMatch = ref(props.filters.cause_match || '');
const partnerUserId = ref(
    props.filters.partner_user_id !== null && props.filters.partner_user_id !== undefined && props.filters.partner_user_id !== ''
        ? String(props.filters.partner_user_id)
        : '',
);

const isCustomRange = computed(() => selectedDuration.value === 'custom');

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const buildQuery = () => {
    const query = {
        type: selectedType.value,
        duration: selectedDuration.value,
        cause_id: causeId.value || undefined,
        state: state.value || undefined,
        source: source.value || undefined,
        cause_match: causeMatch.value || undefined,
        partner_user_id: partnerUserId.value || undefined,
    };

    if (selectedDuration.value === 'custom') {
        query.from_date = fromDate.value || undefined;
        query.to_date = toDate.value || undefined;
    }

    return query;
};

const seedCustomDates = () => {
    if (fromDate.value || toDate.value) {
        return;
    }

    const now = new Date();
    const start = new Date(now.getFullYear(), 3, 1);
    if (now.getMonth() < 3) {
        start.setFullYear(now.getFullYear() - 1);
    }
    fromDate.value = start.toISOString().slice(0, 10);
    toDate.value = now.toISOString().slice(0, 10);
};

const onDurationChange = () => {
    if (selectedDuration.value === 'custom') {
        seedCustomDates();
        return;
    }

    fromDate.value = '';
    toDate.value = '';
    applyFilters();
};

const applyFilters = () => {
    router.get('/admin/reports', buildQuery(), {
        preserveState: true,
        replace: true,
    });
};

const clearExtraFilters = () => {
    causeId.value = '';
    state.value = '';
    source.value = '';
    causeMatch.value = '';
    partnerUserId.value = '';
    applyFilters();
};

const exportUrl = (format) => {
    const params = new URLSearchParams();

    Object.entries({ ...buildQuery(), format }).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    return `/admin/reports/export?${params.toString()}`;
};

const previewColumns = computed(() => {
    const headers = props.report.headers || [];

    return headers.map((label, index) => ({
        key: `col_${index}`,
        label,
        sortable: false,
        align: index > 0 ? 'right' : 'left',
    }));
});

const previewRows = computed(() => {
    const type = props.report.type;

    if (type === 'monthly_by_cause') {
        return (props.report.rows || []).map((row) => {
            const item = { col_0: row.label };

            (row.values || []).forEach((value, index) => {
                item[`col_${index + 1}`] = formatMoney(value);
            });

            item[`col_${(row.values || []).length + 1}`] = formatMoney(row.total);

            return item;
        });
    }

    if (type === 'cause_summary') {
        return (props.report.rows || []).map((row) => ({
            col_0: row.cause,
            col_1: row.donation_count,
            col_2: formatMoney(row.total_amount),
            col_3: formatMoney(row.average_amount),
        }));
    }

    if (type === 'package_summary') {
        return (props.report.rows || []).map((row) => ({
            col_0: row.package,
            col_1: row.cause,
            col_2: row.donation_count,
            col_3: formatMoney(row.total_amount),
            col_4: formatMoney(row.average_amount),
        }));
    }

    if (type === 'monthly_summary') {
        return (props.report.rows || []).map((row) => ({
            col_0: row.month,
            col_1: row.donation_count,
            col_2: formatMoney(row.total_amount),
            col_3: formatMoney(row.average_amount),
        }));
    }

    if (type === 'daily_summary') {
        return (props.report.rows || []).map((row) => ({
            col_0: row.day,
            col_1: row.donation_count,
            col_2: formatMoney(row.total_amount),
            col_3: formatMoney(row.average_amount),
        }));
    }

    return (props.report.rows || []).map((row) => ({
        col_0: row.paid_at,
        col_1: row.order_uuid,
        col_2: row.donor_name,
        col_3: row.donor_email,
        col_4: row.donor_phone,
        col_5: row.state,
        col_6: row.partner_name,
        col_7: row.sid_code,
        col_8: row.cause,
        col_9: row.item_title,
        col_10: formatMoney(row.amount),
        col_11: row.payment_provider,
        col_12: row.receipt_number,
    }));
});

const formatLabel = (format) => ({
    csv: 'CSV',
    xlsx: 'Excel',
    pdf: 'PDF',
}[format] || format.toUpperCase());
</script>

<template>
    <Head title="Reports" />

    <AdminLayout>
        <template #header>Reports</template>

        <PageHeader
            :title="report.title"
            :subtitle="`Paid donations · ${report.periodLabel}`"
        >
            <template #actions>
                <a
                    v-for="format in formats"
                    :key="format"
                    :href="exportUrl(format)"
                    class="admin-btn-secondary !py-2"
                >
                    Export {{ formatLabel(format) }}
                </a>
            </template>
        </PageHeader>

        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-none">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="admin-label">Report type</label>
                    <select v-model="selectedType" class="admin-input" @change="applyFilters">
                        <option v-for="(label, value) in reportTypes" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">Period (day / month / custom)</label>
                    <select v-model="selectedDuration" class="admin-input" @change="onDurationChange">
                        <option v-for="(label, value) in durationOptions" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div v-if="isCustomRange">
                    <label class="admin-label">From date</label>
                    <input v-model="fromDate" type="date" class="admin-input" @change="applyFilters">
                </div>

                <div v-if="isCustomRange">
                    <label class="admin-label">To date</label>
                    <input v-model="toDate" type="date" class="admin-input" @change="applyFilters">
                </div>

                <div>
                    <label class="admin-label">Cause</label>
                    <select v-model="causeId" class="admin-input" @change="applyFilters">
                        <option value="">All causes</option>
                        <option v-for="cause in causes" :key="cause.id" :value="String(cause.id)">
                            {{ cause.title }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">State</label>
                    <select v-model="state" class="admin-input" @change="applyFilters">
                        <option value="">All states</option>
                        <option
                            v-for="option in (filterOptions.states || [])"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">Source</label>
                    <select v-model="source" class="admin-input" @change="applyFilters">
                        <option value="">All sources</option>
                        <option
                            v-for="option in (filterOptions.sources || [])"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">Cause match</label>
                    <select v-model="causeMatch" class="admin-input" @change="applyFilters">
                        <option
                            v-for="(label, value) in causeMatchOptions"
                            :key="value || 'all-cause-match'"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="admin-label">SID code (partner)</label>
                    <select v-model="partnerUserId" class="admin-input" @change="applyFilters">
                        <option value="">All donation</option>
                        <option value="organic">Direct / organic — organic traffic only</option>
                        <option value="all_partners">All partner donation</option>
                        <option
                            v-for="partner in (filterOptions.partners || [])"
                            :key="partner.id"
                            :value="String(partner.id)"
                        >
                            {{ partner.name }} ({{ partner.code }})
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" class="admin-btn-secondary !py-2" @click="applyFilters">
                    Apply filters
                </button>
                <button type="button" class="admin-btn-ghost !py-2" @click="clearExtraFilters">
                    Clear cause / state / source / match / SID
                </button>
                <p class="text-sm text-muted-foreground">
                    Paid donations only · filter by period, state, source, cause match (landing vs donated), and partner SID.
                </p>
            </div>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Grand total</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ formatMoney(report.grandTotal) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Rows</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ previewRows.length }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <p class="text-sm text-muted-foreground">Generated</p>
                <p class="mt-1 text-base font-medium text-foreground">{{ report.generatedAt }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <DataTable
                :columns="previewColumns"
                :rows="previewRows"
                empty-message="No paid donations found for this report."
            />
        </div>
    </AdminLayout>
</template>
