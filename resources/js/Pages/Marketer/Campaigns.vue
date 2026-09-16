<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    Goal,
    IndianRupee,
    MousePointerClick,
    Percent,
} from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import MarketerFilters from '@/Components/Admin/MarketerFilters.vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { useClientSort } from '@/composables/useClientSort';

const props = defineProps({
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    profile: { type: Object, required: true },
    campaigns: { type: Array, default: () => [] },
    target: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'there');

const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');
const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const formatRate = (rate) => `${Number(rate || 0).toFixed(1)}%`;

const campaignKpis = computed(() => {
    const rows = props.campaigns || [];
    const clicks = rows.reduce((sum, row) => sum + Number(row.clicks || 0), 0);
    const conversions = rows.reduce((sum, row) => sum + Number(row.conversions || 0), 0);
    const revenue = rows.reduce((sum, row) => sum + Number(row.revenue || 0), 0);
    const conversionRate = clicks > 0 ? (conversions / clicks) * 100 : 0;

    return { clicks, conversions, revenue, conversionRate };
});

const targetPercent = computed(() => {
    if (props.target?.achieved_percent == null) {
        return '—';
    }

    return `${Number(props.target.achieved_percent).toFixed(1).replace(/\.0$/, '')}%`;
});

const rawCampaigns = computed(() => (props.campaigns || []).map((row) => ({
    ...row,
    utm_campaign: row.utm_campaign || 'Untitled',
    clicks: Number(row.clicks || 0),
    unique_visitors: Number(row.unique_visitors || 0),
    conversions: Number(row.conversions || 0),
    revenue: Number(row.revenue || 0),
    conversion_rate: Number(row.conversion_rate || 0),
})));

const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(rawCampaigns, { key: 'clicks', dir: 'desc' });

const campaignColumns = [
    { key: 'utm_campaign', label: 'Campaign', sortable: true },
    { key: 'clicks', label: 'Clicks', sortable: true, align: 'right' },
    { key: 'unique_visitors', label: 'Unique', sortable: true, align: 'right' },
    { key: 'conversions', label: 'Donations', sortable: true, align: 'right' },
    { key: 'revenue', label: 'Revenue', sortable: true, align: 'right' },
    { key: 'conversion_rate', label: 'Conv.', sortable: true, align: 'right' },
];

const campaignRows = computed(() =>
    sortedRows.value.map((row) => ({
        ...row,
        utm_campaign: row.utm_campaign || 'Untitled',
        clicks: formatNumber(row.clicks),
        unique_visitors: formatNumber(row.unique_visitors),
        conversions: formatNumber(row.conversions),
        revenue: formatMoney(row.revenue),
        conversion_rate: formatRate(row.conversion_rate),
    })),
);

const listQueryParams = (extra = {}) => {
    const params = {
        duration: props.duration,
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

const exportUrl = (format) => {
    const params = new URLSearchParams(listQueryParams({ format }));

    return `/marketer/campaigns/export?${params.toString()}`;
};
</script>

<template>
    <Head title="Marketer campaigns" />

    <AdminLayout>
        <template #header>Campaigns</template>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ profile.code ? `Code ${profile.code}` : 'Marketer' }} · {{ durationLabel }}
                </p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Campaigns, {{ userName }}
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {{ profile.code
                        ? `Clicks, donations, and revenue for ${profile.code}, grouped by campaign.`
                        : 'Assign a referral code to see campaign stats.' }}
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
            action="/marketer/campaigns"
            variant="campaigns"
            :duration="duration"
            :duration-options="durationOptions"
            :filters="filters"
            :filter-options="filterOptions"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MarketerStatCard
                label="Clicks"
                :value="formatNumber(campaignKpis.clicks)"
                :hint="durationLabel"
            >
                <template #icon>
                    <MousePointerClick class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Donations"
                :value="formatNumber(campaignKpis.conversions)"
                hint="Paid conversions"
            >
                <template #icon>
                    <Percent class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Revenue"
                :value="formatMoney(campaignKpis.revenue)"
                :hint="`${formatRate(campaignKpis.conversionRate)} conversion`"
            >
                <template #icon>
                    <IndianRupee class="size-4" />
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
                <CardTitle class="text-base font-semibold">
                    {{ profile.code ? `Campaigns · ${profile.code}` : 'Campaigns' }}
                </CardTitle>
                <CardDescription>
                    Clicks, donations, conversion, and revenue for {{ durationLabel }}.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <DataTable
                    :columns="campaignColumns"
                    :rows="campaignRows"
                    :sort-key="sortKey"
                    :sort-dir="sortDir"
                    empty-message="No campaign clicks in this period."
                    @sort="toggleSort"
                >
                    <template #cell-utm_campaign="{ row }">
                        <span class="font-medium">{{ row.utm_campaign }}</span>
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
