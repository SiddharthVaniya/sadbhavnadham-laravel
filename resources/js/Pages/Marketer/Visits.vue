<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Goal,
    HeartHandshake,
    MousePointerClick,
    Repeat,
} from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import MarketerFilters from '@/Components/Admin/MarketerFilters.vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    profile: { type: Object, required: true },
    visits: { type: Object, required: true },
    target: { type: Object, default: () => ({}) },
    resultBreakdown: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'id' },
    dir: { type: String, default: 'desc' },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'there');

const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');
const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const sliceCount = (name) => Number(
    (props.resultBreakdown || []).find((item) => item.name === name)?.count || 0,
);

const visitTotal = computed(() => Number(props.visits?.meta?.total ?? 0));
const donatedCount = computed(() => sliceCount('Donated'));
const clicksOnlyCount = computed(() => sliceCount('Clicks only'));
const targetPercent = computed(() => {
    if (props.target?.achieved_percent == null) {
        return '—';
    }

    return `${Number(props.target.achieved_percent).toFixed(1).replace(/\.0$/, '')}%`;
});
const formatExtraParams = (params) => {
    if (! params || typeof params !== 'object' || Array.isArray(params)) {
        return '—';
    }

    const parts = Object.entries(params)
        .filter(([, value]) => value != null && String(value) !== '')
        .map(([key, value]) => `${key}=${value}`);

    return parts.length ? parts.join(', ') : '—';
};

const dash = (value) => {
    if (value == null || String(value).trim() === '') {
        return '—';
    }

    return String(value);
};

const deviceLabel = (value) => {
    const labels = {
        mobile: 'Mobile',
        tablet: 'Tablet',
        desktop: 'PC',
        unknown: 'Unknown',
    };

    if (value == null || String(value).trim() === '') {
        return 'Unknown';
    }

    return labels[value] || value;
};

const listQueryParams = (extra = {}) => {
    const params = {
        duration: props.duration,
        sort: props.sort,
        dir: props.dir,
        ...props.filters,
        ...extra,
    };

    if (params.duration !== 'custom') {
        delete params.from_date;
        delete params.to_date;
    }

    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
};

const toggleSort = (key) => {
    const nextDir = props.sort === key
        ? (props.dir === 'asc' ? 'desc' : 'asc')
        : (key === 'created_at' || key === 'converted_amount' || key === 'id' ? 'desc' : 'asc');

    router.get('/marketer/visits', listQueryParams({ sort: key, dir: nextDir }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const exportUrl = (format) => {
    const params = new URLSearchParams(listQueryParams({ format }));

    return `/marketer/visits/export?${params.toString()}`;
};

const visitColumns = [
    { key: 'created_at', label: 'When', sortable: true },
    { key: 'converted', label: 'Converted', sortable: true },
    { key: 'converted_amount', label: 'Donation', sortable: true, align: 'right' },
    { key: 'converted_at', label: 'Converted at', sortable: true },
    { key: 'ip_address', label: 'IP', sortable: true },
    { key: 'device_type', label: 'Device', sortable: true },
    { key: 'is_unique', label: 'Unique', sortable: true },
    { key: 'utm_source', label: 'Source', sortable: true },
    { key: 'utm_medium', label: 'Medium', sortable: true },
    { key: 'utm_campaign', label: 'Campaign', sortable: true },
    { key: 'utm_content', label: 'Ad', sortable: true },
    { key: 'sid', label: 'Referral (sid)', sortable: true },
    { key: 'utm_term', label: 'Ad set ID', sortable: true },
    { key: 'aid', label: 'Ad ID', sortable: false },
    { key: 'utm_id', label: 'UTM ID', sortable: true },
    { key: 'page_path', label: 'Page', sortable: true },
    { key: 'landing_url', label: 'Landing URL', sortable: true },
    { key: 'referrer', label: 'Referrer', sortable: true },
    { key: 'fbclid', label: 'FB click ID', sortable: true },
    { key: 'amt', label: 'Amt param', sortable: true },
    { key: 'ptype', label: 'Payment type', sortable: true },
    { key: 'extra_params', label: 'Extra params', sortable: false },
    { key: 'user_agent', label: 'User agent', sortable: true },
    { key: 'visitor_id', label: 'Visitor ID', sortable: true },
];

const visitRows = computed(() =>
    (props.visits.data || []).map((row) => ({
        ...row,
        created_at: dash(row.created_at),
        converted_at: dash(row.converted_at),
        ip_address: dash(row.ip_address),
        device_type: deviceLabel(row.device_type),
        utm_source: dash(row.utm_source),
        utm_medium: dash(row.utm_medium),
        utm_campaign: dash(row.utm_campaign),
        utm_content: dash(row.utm_content),
        sid: dash(row.sid),
        utm_term: dash(row.utm_term),
        aid: dash(row.aid),
        utm_id: dash(row.utm_id),
        page_path: dash(row.page_path),
        landing_url: dash(row.landing_url),
        referrer: dash(row.referrer),
        fbclid: dash(row.fbclid),
        amt: dash(row.amt),
        ptype: dash(row.ptype),
        extra_params: formatExtraParams(row.extra_params),
        user_agent: dash(row.user_agent),
        visitor_id: dash(row.visitor_id),
        converted_amount: row.converted && row.converted_amount != null
            ? formatMoney(row.converted_amount)
            : '—',
    })),
);
</script>

<template>
    <Head title="Marketer clicks" />

    <AdminLayout>
        <template #header>Clicks</template>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ profile.code ? `Code ${profile.code}` : 'Marketer' }} · {{ durationLabel }}
                </p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Clicks, {{ userName }}
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {{ profile.code
                        ? `Every tracked visit attributed to ${profile.code}.`
                        : 'Assign a referral code to see click history.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a :href="exportUrl('csv')" class="admin-btn-secondary !py-2">Export CSV</a>
                <a :href="exportUrl('xlsx')" class="admin-btn-secondary !py-2">Export Excel</a>
                <div class="inline-flex h-9 items-center rounded-full border border-border bg-card px-3.5 text-sm font-medium text-foreground">
                    {{ durationLabel }}
                </div>
            </div>
        </div>

        <MarketerFilters
            action="/marketer/visits"
            :duration="duration"
            :duration-options="durationOptions"
            :filters="filters"
            :filter-options="filterOptions"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MarketerStatCard
                label="Tracked clicks"
                :value="formatNumber(visitTotal)"
                :hint="durationLabel"
            >
                <template #icon>
                    <MousePointerClick class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Donated"
                :value="formatNumber(donatedCount)"
                hint="Clicks that converted"
            >
                <template #icon>
                    <HeartHandshake class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Clicks only"
                :value="formatNumber(clicksOnlyCount)"
                hint="Did not convert"
            >
                <template #icon>
                    <Repeat class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Target collected"
                :value="targetPercent"
                :hint="target.goal ? `${formatMoney(target.achieved)} of ${formatMoney(target.goal)}` : 'No rupee target yet'"
            >
                <template #icon>
                    <Goal class="size-4" />
                </template>
            </MarketerStatCard>
        </div>

        <Card class="shadow-none">
            <CardHeader>
                <CardTitle class="text-base font-semibold">Click history</CardTitle>
                <CardDescription>
                    Every tracked visit with conversion, donation amount, IP, and full tracking details
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <DataTable
                    :columns="visitColumns"
                    :rows="visitRows"
                    :sort-key="sort"
                    :sort-dir="dir"
                    empty-message="No tracked clicks in this period."
                    @sort="toggleSort"
                >
                    <template #cell-converted="{ row }">
                        <Badge
                            :variant="row.converted ? 'default' : 'outline'"
                            :class="row.converted
                                ? 'border-transparent bg-emerald-600 text-white hover:bg-emerald-600'
                                : 'text-muted-foreground'"
                        >
                            {{ row.converted ? 'Converted' : 'Not converted' }}
                        </Badge>
                    </template>
                    <template #cell-converted_amount="{ row }">
                        <span class="tabular-nums font-medium" :class="row.converted ? 'text-foreground' : 'text-muted-foreground'">
                            {{ row.converted_amount }}
                        </span>
                    </template>
                    <template #cell-ip_address="{ row }">
                        <span class="font-mono text-[12px] tabular-nums">{{ row.ip_address }}</span>
                    </template>
                    <template #cell-is_unique="{ row }">
                        {{ row.is_unique ? 'Yes' : 'No' }}
                    </template>
                    <template #cell-landing_url="{ row }">
                        <span class="block max-w-[260px] truncate" :title="row.landing_url">{{ row.landing_url }}</span>
                    </template>
                    <template #cell-referrer="{ row }">
                        <span class="block max-w-[220px] truncate" :title="row.referrer">{{ row.referrer }}</span>
                    </template>
                    <template #cell-user_agent="{ row }">
                        <span class="block max-w-[280px] truncate" :title="row.user_agent">{{ row.user_agent }}</span>
                    </template>
                    <template #cell-extra_params="{ row }">
                        <span class="block max-w-[240px] truncate" :title="row.extra_params">{{ row.extra_params }}</span>
                    </template>
                    <template #cell-fbclid="{ row }">
                        <span class="block max-w-[160px] truncate font-mono text-[12px]" :title="row.fbclid">{{ row.fbclid }}</span>
                    </template>
                    <template #cell-visitor_id="{ row }">
                        <span class="block max-w-[140px] truncate font-mono text-[12px]" :title="row.visitor_id">{{ row.visitor_id }}</span>
                    </template>
                    <template #footer>
                        <Pagination :links="visits.links || []" :meta="visits.meta" />
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
