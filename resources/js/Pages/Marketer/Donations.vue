<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    HandCoins,
    HeartHandshake,
    IndianRupee,
} from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import MarketerFilters from '@/Components/Admin/MarketerFilters.vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    profile: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    donations: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'time' },
    dir: { type: String, default: 'desc' },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? 'there');

const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');
const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const donationCount = computed(() => Number(props.summary?.donations ?? 0));
const donationRevenue = computed(() => Number(props.summary?.revenue ?? 0));
const averageDonation = computed(() => Number(props.summary?.average_donation ?? 0));

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
        : (key === 'time' || key === 'amount' ? 'desc' : 'asc');

    router.get('/marketer/donations', listQueryParams({ sort: key, dir: nextDir }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const exportUrl = (format) => {
    const params = new URLSearchParams(listQueryParams({ format }));

    return `/marketer/donations/export?${params.toString()}`;
};

const donationColumns = [
    { key: 'campaign', label: 'Campaign', sortable: true },
    { key: 'medium', label: 'Medium', sortable: true },
    { key: 'ad', label: 'Ad', sortable: true },
    { key: 'cause', label: 'Cause', sortable: true },
    { key: 'title', label: 'Title', sortable: true },
    { key: 'pincode', label: 'Pincode', sortable: true },
    { key: 'city', label: 'City', sortable: true },
    { key: 'state', label: 'State', sortable: true },
    { key: 'ip_address', label: 'IP', sortable: true },
    { key: 'ip_location', label: 'IP location', sortable: true },
    { key: 'device', label: 'Device', sortable: true },
    { key: 'amount', label: 'Amount', sortable: true, align: 'right' },
    { key: 'time', label: 'Time', sortable: true },
];

const donationRows = computed(() => (props.donations?.data || []).map((row) => ({
    ...row,
    campaign: row.campaign || '—',
    medium: row.medium || '—',
    ad: row.ad || '—',
    cause: row.cause || '—',
    title: row.title || '—',
    pincode: row.pincode || '—',
    city: row.city || '—',
    state: row.state || '—',
    ip_address: row.ip_address || '—',
    ip_location: row.ip_location || '—',
    device: deviceLabel(row.device),
    amount: formatMoney(row.amount),
    time: row.time || '—',
})));
</script>

<template>
    <Head title="Marketer donations" />

    <AdminLayout>
        <template #header>Donations</template>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ profile.code ? `Code ${profile.code}` : 'Marketer' }} · {{ durationLabel }}
                </p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    Donations, {{ userName }}
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Paid orders linked to your tracking code. Filter by campaign, medium, ad, cause, title, location, IP, and device.
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
            action="/marketer/donations"
            variant="donation-list"
            :duration="duration"
            :duration-options="durationOptions"
            :filters="filters"
            :filter-options="filterOptions"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <MarketerStatCard
                label="Paid donations"
                :value="formatNumber(donationCount)"
                :hint="durationLabel"
            >
                <template #icon>
                    <HeartHandshake class="size-4" />
                </template>
            </MarketerStatCard>
            <MarketerStatCard
                label="Amount collected"
                :value="formatMoney(donationRevenue)"
                hint="Paid orders in this view"
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
        </div>

        <Card class="shadow-none">
            <CardHeader>
                <CardTitle class="text-base">
                    {{ profile.code ? `Attributed donations · ${profile.code}` : 'Attributed donations' }}
                </CardTitle>
                <CardDescription>
                    Paid orders linked to your tracking code for the selected filters.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <DataTable
                    :columns="donationColumns"
                    :rows="donationRows"
                    :sort-key="sort"
                    :sort-dir="dir"
                    empty-message="No attributed donations yet for this view."
                    @sort="toggleSort"
                >
                    <template #cell-cause="{ row }">
                        <span class="font-medium">{{ row.cause }}</span>
                    </template>
                    <template #cell-ip_address="{ row }">
                        <span class="font-mono text-[12px] tabular-nums">{{ row.ip_address }}</span>
                    </template>
                    <template #cell-ip_location="{ row }">
                        <span class="block max-w-[220px] truncate" :title="row.ip_location">{{ row.ip_location }}</span>
                    </template>
                    <template #footer>
                        <Pagination :links="donations.links || []" :meta="donations.meta" />
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
