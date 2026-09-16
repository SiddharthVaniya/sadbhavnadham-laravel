<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Goal,
    HandCoins,
    HeartHandshake,
    IndianRupee,
} from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MarketerFilters from '@/Components/Admin/MarketerFilters.vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import RupeeTargetChart from '@/Components/Admin/RupeeTargetChart.vue';
import DashboardMonthlyChart from '@/Components/Admin/DashboardMonthlyChart.vue';
import MarketerLineChart from '@/Components/Admin/MarketerLineChart.vue';
import TopCampaignsChart from '@/Components/Admin/TopCampaignsChart.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    profile: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    target: { type: Object, default: () => ({}) },
    dailyTrend: { type: Object, default: () => ({ granularity: 'day', points: [] }) },
    campaignRevenueTrend: { type: Object, default: () => ({ labels: [], series: [] }) },
    topCampaigns: { type: Array, default: () => [] },
    donations: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'there');

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
});

const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');
const formatMoney = (amount) => `₹ ${formatNumber(amount)}`;
const donationCount = computed(() => Number(props.summary?.donations ?? 0));
const collectedAmount = computed(() => Number(props.summary?.revenue ?? 0));
const averageDonation = computed(() => Number(props.summary?.average_donation ?? 0));
const targetPercent = computed(() => {
    if (props.target?.achieved_percent == null) {
        return '—';
    }

    return `${Number(props.target.achieved_percent).toFixed(1).replace(/\.0$/, '')}%`;
});
const targetHint = computed(() => {
    if (! props.target?.goal) {
        return 'Ask an admin to set your rupee target.';
    }

    return `${formatMoney(props.target.achieved)} of ${formatMoney(props.target.goal)} lifetime`;
});

const recentDonations = computed(() => (props.donations?.data || []).slice(0, 12));

const initialsFor = (row) => {
    const source = String(row.cause || row.campaign || row.title || 'DN').trim();
    const parts = source.split(/\s+/).filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }

    return source.slice(0, 2).toUpperCase();
};

const dailyTrendPoints = computed(() => props.dailyTrend?.points || []);
const dailyTrendGranularity = computed(() => props.dailyTrend?.granularity || 'day');
const dailyCollectionDescription = computed(() => (
    dailyTrendGranularity.value === 'hour'
        ? `Paid amount by hour · ${props.durationLabel}`
        : `Paid amount by day · ${props.durationLabel}`
));

const campaignTrendLabels = computed(() => props.campaignRevenueTrend?.labels || []);
const campaignTrendSeries = computed(() => props.campaignRevenueTrend?.series || []);
const campaignTrendGranularity = computed(() => props.campaignRevenueTrend?.granularity || 'day');
const campaignTrendDescription = computed(() => (
    campaignTrendGranularity.value === 'hour'
        ? 'Hourly paid revenue for your top campaigns today. Hover a campaign to highlight its line.'
        : 'Daily paid revenue for your top campaigns. Hover a campaign to highlight its line.'
));

const deviceLabel = (value) => {
    const labels = {
        mobile: 'Mobile',
        tablet: 'Tablet',
        desktop: 'PC',
        unknown: 'Unknown',
    };

    return labels[value] || value || 'Unknown';
};

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

    return `/marketer/export?${params.toString()}`;
};
</script>

<template>
    <Head title="Marketer performance" />

    <AdminLayout>
        <template #header>Performance</template>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ profile.code ? `Code ${profile.code}` : 'Marketer' }} · {{ durationLabel }}
                </p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ greeting }}, {{ userName }}
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {{ profile.code
                        ? `Orders linked to your tracking code. Donor identity is hidden.`
                        : 'Ask an admin to assign your referral code so tracking can start.' }}
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
            action="/marketer"
            variant="donations"
            :duration="duration"
            :duration-options="durationOptions"
            :filters="filters"
            :filter-options="filterOptions"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MarketerStatCard
                label="Paid donations"
                :value="formatNumber(donationCount)"
                :hint="`${durationLabel}`"
            >
                <template #icon>
                    <HeartHandshake class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Paid amount"
                :value="formatMoney(collectedAmount)"
                hint="Attributed collections"
            >
                <template #icon>
                    <IndianRupee class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Average donation"
                :value="formatMoney(averageDonation)"
                hint="Per paid order"
            >
                <template #icon>
                    <HandCoins class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Target collected"
                :value="targetPercent"
                :hint="targetHint"
            >
                <template #icon>
                    <Goal class="size-4" />
                </template>
            </MarketerStatCard>
        </div>

        <div class="mb-6 grid gap-4 xl:grid-cols-12">
            <Card class="shadow-none xl:col-span-8">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-semibold">Campaign revenue trend</CardTitle>
                    <CardDescription>{{ campaignTrendDescription }}</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <MarketerLineChart
                        :labels="campaignTrendLabels"
                        :series="campaignTrendSeries"
                        :granularity="campaignTrendGranularity"
                        empty-message="No campaign revenue for this period yet."
                    />
                </CardContent>
            </Card>

            <Card class="flex h-full flex-col shadow-none xl:col-span-4">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <CardTitle class="text-base font-semibold">Recent donations</CardTitle>
                            <CardDescription>
                                {{ donationCount }} paid order{{ donationCount === 1 ? '' : 's' }} · {{ durationLabel }}
                            </CardDescription>
                        </div>
                        <Link
                            href="/marketer/donations"
                            class="shrink-0 text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            View all
                        </Link>
                    </div>
                </CardHeader>
                <CardContent class="flex min-h-0 flex-1 flex-col">
                    <ul class="space-y-3">
                        <li
                            v-for="(row, index) in recentDonations"
                            :key="`${row.time}-${index}`"
                            class="flex items-center gap-3"
                        >
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold tracking-wide text-foreground">
                                {{ initialsFor(row) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ row.cause || row.campaign || 'Donation' }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ row.campaign || row.title }} · {{ deviceLabel(row.device) }} · {{ row.time }}
                                </p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold tabular-nums text-foreground">
                                +{{ formatMoney(row.amount) }}
                            </p>
                        </li>
                        <li v-if="! recentDonations.length" class="py-10 text-center text-sm text-muted-foreground">
                            No attributed donations yet for this view.
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <div class="mb-6 grid items-stretch gap-4 lg:grid-cols-3">
            <Card class="flex min-h-[340px] flex-col overflow-visible shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-semibold">Daily collection</CardTitle>
                    <CardDescription>{{ dailyCollectionDescription }}</CardDescription>
                </CardHeader>
                <CardContent class="flex min-h-0 flex-1 flex-col overflow-visible pt-0">
                    <DashboardMonthlyChart
                        class="min-h-0 flex-1"
                        :months="dailyTrendPoints"
                        :granularity="dailyTrendGranularity"
                        amount-only
                        empty-message="No collection for this period yet."
                    />
                </CardContent>
            </Card>

            <Card class="flex min-h-[340px] flex-col shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-semibold">Rupee target</CardTitle>
                    <CardDescription v-if="target.goal">
                        Goal {{ formatMoney(target.goal) }}
                    </CardDescription>
                    <CardDescription v-else>Lifetime progress until a goal is set</CardDescription>
                </CardHeader>
                <CardContent class="flex min-h-0 flex-1 flex-col pt-0">
                    <RupeeTargetChart class="min-h-0 flex-1" :target="target" />
                </CardContent>
            </Card>

            <Card class="flex min-h-[340px] flex-col shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base font-semibold">Top campaigns</CardTitle>
                    <CardDescription>Revenue share · {{ durationLabel }}</CardDescription>
                </CardHeader>
                <CardContent class="flex min-h-0 flex-1 flex-col pt-0">
                    <TopCampaignsChart class="min-h-0 flex-1" :items="topCampaigns" />
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
