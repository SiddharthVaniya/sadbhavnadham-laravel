<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import AnalyticsTrendChart from '@/Components/Admin/AnalyticsTrendChart.vue';
import { mergeDurationOptions } from '@/utils/periodOptions';

const props = defineProps({
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    updatedAt: { type: String, default: '' },
    summary: { type: Object, required: true },
    comparison: { type: Object, default: () => ({ available: false, metrics: [] }) },
    funnel: { type: Array, default: () => [] },
    dailyTrend: { type: Array, default: () => [] },
    topCauses: { type: Array, default: () => [] },
    topPackages: { type: Array, default: () => [] },
    referrers: { type: Array, default: () => [] },
    utmSources: { type: Array, default: () => [] },
    paidByChannel: { type: Array, default: () => [] },
    paidByUtm: { type: Array, default: () => [] },
    paidByEmployee: { type: Array, default: () => [] },
    paidByPartner: { type: Array, default: () => [] },
    paidByMetaAd: { type: Array, default: () => [] },
    paidByReferrer: { type: Array, default: () => [] },
    devices: { type: Array, default: () => [] },
    failedPayments: { type: Array, default: () => [] },
    abandonedCheckouts: { type: Object, default: () => ({ count: 0, amount: 0, orders: [] }) },
    subscriptions: { type: Object, default: () => ({}) },
    donors: { type: Object, default: () => ({}) },
    hourlyActivity: { type: Array, default: () => [] },
    locations: { type: Object, default: () => ({ countries: [], regions: [], cities: [] }) },
});

const selectedDuration = ref(
    props.filters.from_date && props.filters.to_date ? 'custom' : props.duration,
);
const fromDate = ref(props.filters.from_date || '');
const toDate = ref(props.filters.to_date || '');
const indiaFocus = ref(Boolean(props.filters.india_focus));

const isCustomRange = computed(() => selectedDuration.value === 'custom');
const periodOptions = computed(() => mergeDurationOptions(props.durationOptions));

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const formatRate = (rate) => `${Number(rate || 0).toFixed(1)}%`;

const funnelMax = computed(() => Math.max(...props.funnel.map((step) => step.count), 1));
const hourlyMax = computed(() => Math.max(...props.hourlyActivity.map((row) => Math.max(row.visits, row.paid)), 1));

const revenueHint = computed(() => {
    const tracked = formatMoney(props.summary.tracked_revenue);
    const orders = formatMoney(props.summary.orders_revenue);

    if (Math.round(props.summary.tracked_revenue) === Math.round(props.summary.orders_revenue)) {
        return `${props.summary.donations_failed} failed payments`;
    }

    return `Tracked ${tracked} · Orders ${orders}`;
});

const causeColumns = [
    { key: 'cause', label: 'Cause', sortable: false },
    { key: 'views', label: 'Views', sortable: false },
    { key: 'unique_visitors', label: 'Unique', sortable: false },
    { key: 'checkouts', label: 'Checkouts', sortable: false },
    { key: 'paid', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
    { key: 'conversion_rate', label: 'Conversion', sortable: false, align: 'right' },
];

const packageColumns = [
    { key: 'cause', label: 'Cause', sortable: false },
    { key: 'package', label: 'Package', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const channelColumns = [
    { key: 'channel_label', label: 'Channel', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const paidUtmColumns = [
    { key: 'source', label: 'Source', sortable: false },
    { key: 'medium', label: 'Medium', sortable: false },
    { key: 'campaign', label: 'Campaign', sortable: false },
    { key: 'content', label: 'Employee', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const paidEmployeeColumns = [
    { key: 'content', label: 'Employee', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const paidPartnerColumns = [
    { key: 'name', label: 'Marketer', sortable: false },
    { key: 'code', label: 'Code', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const paidMetaAdColumns = [
    { key: 'campaign', label: 'Campaign', sortable: false },
    { key: 'campaign_id', label: 'Campaign ID', sortable: false },
    { key: 'adset_id', label: 'Ad set ID', sortable: false },
    { key: 'ad_id', label: 'Ad ID', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const paidReferrerColumns = [
    { key: 'referrer', label: 'Referrer', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const buildQuery = () => {
    const query = {
        duration: selectedDuration.value,
        india_focus: indiaFocus.value ? 1 : undefined,
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
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
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
    router.get('/admin/analytics', buildQuery(), {
        preserveState: true,
        replace: true,
    });
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    Object.entries(buildQuery()).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    return `/admin/analytics/export?${params.toString()}`;
});

const formatChange = (metric) => {
    const prefix = metric.change > 0 ? '+' : '';

    return `${prefix}${metric.change_percent}%`;
};
</script>

<template>
    <Head title="Analytics" />

    <AdminLayout>
        <template #header>Analytics</template>

        <PageHeader
            :title="`Analytics · ${durationLabel}`"
            subtitle="Visitors, checkout funnel, donations, and campaign attribution"
        >
            <template #actions>
                <label class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-foreground">
                    <input v-model="indiaFocus" type="checkbox" class="rounded" @change="applyFilters">
                    India focus
                </label>
                <template v-if="isCustomRange">
                    <input v-model="fromDate" type="date" class="admin-input !w-auto py-2" @change="applyFilters">
                    <input v-model="toDate" type="date" class="admin-input !w-auto py-2" @change="applyFilters">
                </template>
                <select
                    v-model="selectedDuration"
                    class="admin-input !w-auto min-w-[180px] py-2"
                    @change="onDurationChange"
                >
                    <option v-for="(label, value) in periodOptions" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>
                <a :href="exportUrl" class="rounded-lg border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-muted">
                    Export CSV
                </a>
            </template>
        </PageHeader>

        <p v-if="updatedAt" class="mb-4 text-xs text-muted-foreground">Updated {{ updatedAt }}</p>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Unique visitors" :value="summary.unique_visitors.toLocaleString()" :hint="`${summary.countries_reached} countries · ${summary.cause_views.toLocaleString()} cause views`" />
            <StatCard tone="orange" label="Checkouts started" :value="summary.checkouts_started.toLocaleString()" :hint="`${formatRate(summary.cause_to_checkout_rate)} of unique cause visitors`" />
            <StatCard tone="green" label="Paid donations" :value="summary.donations_paid.toLocaleString()" :hint="`${formatRate(summary.visitor_to_paid_rate)} visitor conversion · Avg ${formatMoney(summary.average_donation)}`" />
            <StatCard label="Order revenue" :value="formatMoney(summary.orders_revenue)" :hint="revenueHint" />
        </div>

        <div v-if="comparison.available" class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="metric in comparison.metrics"
                :key="metric.key"
                class="rounded-xl border border-border bg-card px-4 py-3 shadow-none"
            >
                <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ metric.label }}</p>
                <p class="mt-1 text-lg font-semibold text-foreground">{{ Number(metric.current).toLocaleString() }}</p>
                <p class="text-xs" :class="metric.change >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                    {{ formatChange(metric) }} vs previous period
                </p>
            </div>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5 lg:col-span-2 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Traffic & conversion trend</h3>
                <AnalyticsTrendChart :points="dailyTrend" />
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Conversion funnel</h3>
                <ul class="space-y-4">
                    <li v-for="step in funnel" :key="step.label">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-foreground">{{ step.label }}</span>
                            <span class="font-medium text-foreground">{{ step.count.toLocaleString() }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-foreground"
                                :style="{ width: `${Math.max(4, Math.round((step.count / funnelMax) * 100))}%` }"
                            />
                        </div>
                    </li>
                </ul>

                <dl class="mt-6 space-y-3 border-t border-border pt-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Checkout → paid</dt>
                        <dd class="font-medium">{{ formatRate(summary.checkout_to_paid_rate) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Payment success</dt>
                        <dd class="font-medium">{{ formatRate(summary.payment_success_rate) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Abandoned checkouts</dt>
                        <dd class="font-medium">{{ abandonedCheckouts.count }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="mb-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5 xl:col-span-2 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Performance by cause</h3>
                <DataTable :columns="causeColumns" :rows="topCauses" empty-message="No cause analytics yet.">
                    <template #cell-cause="{ row }">
                        <Link :href="row.donations_url" class="font-medium text-sky-700 hover:underline">{{ row.cause }}</Link>
                    </template>
                    <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
                    <template #cell-conversion_rate="{ row }">{{ formatRate(row.conversion_rate) }}</template>
                </DataTable>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-none">
                    <h3 class="mb-4 text-sm font-semibold text-foreground">Top referrers</h3>
                    <ul class="space-y-3 text-sm">
                        <li v-for="(row, index) in referrers" :key="index" class="flex items-center justify-between gap-4">
                            <span class="truncate text-foreground">{{ row.referrer }}</span>
                            <span class="font-medium text-foreground">{{ row.hits }}</span>
                        </li>
                        <li v-if="! referrers.length" class="text-muted-foreground">No referrer data yet.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-none">
                    <h3 class="mb-4 text-sm font-semibold text-foreground">Devices</h3>
                    <ul class="space-y-3 text-sm">
                        <li v-for="(row, index) in devices" :key="index" class="flex items-center justify-between gap-4">
                            <span class="text-foreground">{{ row.device }}</span>
                            <span class="font-medium text-foreground">{{ row.visitors }}</span>
                        </li>
                        <li v-if="! devices.length" class="text-muted-foreground">No device data yet.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Paid by channel</h3>
                <DataTable :columns="channelColumns" :rows="paidByChannel" empty-message="No paid channel data yet.">
                    <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
                </DataTable>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Paid by employee</h3>
                <p class="mb-3 text-xs text-muted-foreground">From utm_content on donation links.</p>
                <DataTable :columns="paidEmployeeColumns" :rows="paidByEmployee" empty-message="No employee UTM data yet. Add utm_content=name to links.">
                    <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
                </DataTable>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-none">
            <h3 class="mb-1 text-sm font-semibold text-foreground">Paid by marketer (exact)</h3>
            <p class="mb-4 text-xs text-muted-foreground">
                Matched on the <code class="font-mono">sid</code> referral code, so each donation belongs to exactly one marketer.
            </p>
            <DataTable
                :columns="paidPartnerColumns"
                :rows="paidByPartner"
                empty-message="No sid-tagged donations yet. Ask marketers to copy their URL parameters from Partner attribution."
            >
                <template #cell-code="{ row }">
                    <span class="font-mono text-xs">{{ row.code }}</span>
                </template>
                <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
            </DataTable>
        </div>

        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-none">
            <h3 class="mb-1 text-sm font-semibold text-foreground">Paid by Meta ad (exact)</h3>
            <p class="mb-4 text-xs text-muted-foreground">
                Campaign, ad set and ad IDs come straight from Meta's dynamic URL tokens.
            </p>
            <DataTable
                :columns="paidMetaAdColumns"
                :rows="paidByMetaAd"
                empty-message="No Meta ad IDs captured yet."
            >
                <template #cell-campaign_id="{ row }">
                    <span class="font-mono text-xs">{{ row.campaign_id }}</span>
                </template>
                <template #cell-adset_id="{ row }">
                    <span class="font-mono text-xs">{{ row.adset_id }}</span>
                </template>
                <template #cell-ad_id="{ row }">
                    <span class="font-mono text-xs">{{ row.ad_id }}</span>
                </template>
                <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
            </DataTable>
        </div>

        <div class="mb-6 rounded-xl border border-border bg-card p-5 shadow-none">
            <h3 class="mb-4 text-sm font-semibold text-foreground">Paid by UTM</h3>
            <DataTable :columns="paidUtmColumns" :rows="paidByUtm" empty-message="No paid UTM data yet. Add utm_source to donation links.">
                <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
            </DataTable>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Top packages</h3>
                <DataTable :columns="packageColumns" :rows="topPackages" empty-message="No package analytics yet.">
                    <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
                </DataTable>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Paid by referrer</h3>
                <DataTable :columns="paidReferrerColumns" :rows="paidByReferrer" empty-message="No paid referrer data yet.">
                    <template #cell-revenue="{ row }">{{ formatMoney(row.revenue) }}</template>
                </DataTable>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">UTM campaigns (hits)</h3>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in utmSources" :key="index">
                        <div class="font-medium text-foreground">{{ row.source }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.medium }} · {{ row.campaign }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.hits }} hits</div>
                    </li>
                    <li v-if="! utmSources.length" class="text-muted-foreground">Add utm_source to donation links to track campaigns.</li>
                </ul>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Subscriptions</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Checkout started</dt><dd class="font-medium">{{ subscriptions.checkouts_started || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Created</dt><dd class="font-medium">{{ subscriptions.subscriptions_created || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Live now</dt><dd class="font-medium">{{ subscriptions.live_subscriptions || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Cancelled in period</dt><dd class="font-medium">{{ subscriptions.cancelled_in_period || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Recurring revenue</dt><dd class="font-medium">{{ formatMoney(subscriptions.recurring_revenue) }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Failed payments</h3>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in failedPayments" :key="index" class="flex items-center justify-between gap-4">
                        <span class="text-foreground">{{ row.cause }}</span>
                        <span class="font-medium text-foreground">{{ row.failures }} · {{ formatMoney(row.lost_amount) }}</span>
                    </li>
                    <li v-if="! failedPayments.length" class="text-muted-foreground">No failed payments in this period.</li>
                </ul>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-1 text-sm font-semibold text-foreground">Abandoned checkouts</h3>
                <p class="mb-4 text-xs text-muted-foreground">{{ abandonedCheckouts.count }} attempts · {{ formatMoney(abandonedCheckouts.amount) }}</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in abandonedCheckouts.orders" :key="index" class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-foreground">{{ row.donor_name }}</div>
                            <div class="truncate text-xs text-muted-foreground">{{ row.cause }} · {{ row.created_at }}</div>
                        </div>
                        <Link :href="row.detail_url" class="shrink-0 font-medium text-sky-700 hover:underline">{{ formatMoney(row.amount) }}</Link>
                    </li>
                    <li v-if="! abandonedCheckouts.orders.length" class="text-muted-foreground">No abandoned checkouts in this period.</li>
                </ul>
            </div>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Donor activity</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Donors in period</dt><dd class="font-medium">{{ donors.donors_in_period || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">New donors</dt><dd class="font-medium">{{ donors.new_donors || 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Repeat donors</dt><dd class="font-medium">{{ donors.repeat_donors || 0 }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 lg:col-span-2 shadow-none">
                <h3 class="mb-1 text-sm font-semibold text-foreground">Activity by hour</h3>
                <p class="mb-4 text-xs text-muted-foreground">Visits and paid donations across the day</p>
                <div class="flex h-28 items-end gap-1">
                    <div
                        v-for="row in hourlyActivity"
                        :key="row.hour"
                        class="flex min-w-0 flex-1 flex-col items-center gap-1"
                    >
                        <div class="flex h-20 w-full items-end justify-center gap-0.5">
                            <div class="w-1.5 rounded-t bg-sky-400" :style="{ height: `${Math.max(4, Math.round((row.visits / hourlyMax) * 100))}%` }" :title="`${row.visits} visits`" />
                            <div class="w-1.5 rounded-t bg-emerald-600" :style="{ height: `${Math.max(4, Math.round((row.paid / hourlyMax) * 100))}%` }" :title="`${row.paid} paid`" />
                        </div>
                        <span v-if="row.hour % 3 === 0" class="text-[9px] text-muted-foreground">{{ row.label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-none">
                <h3 class="mb-1 text-sm font-semibold text-foreground">Top countries</h3>
                <p class="mb-4 text-xs text-muted-foreground">Unique visitors by country</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in locations.countries" :key="index" class="flex items-center justify-between gap-4">
                        <span class="min-w-0 truncate text-foreground">
                            <span v-if="row.code" class="mr-1 font-medium text-muted-foreground">{{ row.code }}</span>
                            {{ row.label }}
                        </span>
                        <span class="shrink-0 font-medium text-foreground">{{ row.visitors }}</span>
                    </li>
                    <li v-if="! locations.countries.length" class="text-muted-foreground">No country data yet.</li>
                </ul>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-none">
                <h3 class="mb-1 text-sm font-semibold text-foreground">Top states / regions</h3>
                <p class="mb-4 text-xs text-muted-foreground">Unique visitors by state or region</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in locations.regions" :key="index" class="flex items-center justify-between gap-4">
                        <span class="min-w-0 truncate text-foreground">{{ row.label }}</span>
                        <span class="shrink-0 font-medium text-foreground">{{ row.visitors }}</span>
                    </li>
                    <li v-if="! locations.regions.length" class="text-muted-foreground">No region data yet.</li>
                </ul>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-none">
                <h3 class="mb-1 text-sm font-semibold text-foreground">Top cities</h3>
                <p class="mb-4 text-xs text-muted-foreground">Unique visitors by city</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="(row, index) in locations.cities" :key="index" class="flex items-center justify-between gap-4">
                        <span class="min-w-0 truncate text-foreground">{{ row.label }}</span>
                        <span class="shrink-0 font-medium text-foreground">{{ row.visitors }}</span>
                    </li>
                    <li v-if="! locations.cities.length" class="text-muted-foreground">No city data yet.</li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
