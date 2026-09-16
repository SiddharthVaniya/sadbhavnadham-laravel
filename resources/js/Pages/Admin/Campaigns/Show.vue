<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import { mergeDurationOptions } from '@/utils/periodOptions';

const props = defineProps({
    campaign: { type: Object, required: true },
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    durationLabel: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    activeSubscribers: { type: Array, default: () => [] },
    recentDonations: { type: Array, default: () => [] },
    multiCampaignDonors: { type: Array, default: () => [] },
});

const selectedDuration = ref(
    props.filters.from_date && props.filters.to_date ? 'custom' : props.duration,
);
const fromDate = ref(props.filters.from_date || '');
const toDate = ref(props.filters.to_date || '');

const isCustomRange = computed(() => selectedDuration.value === 'custom');
const periodOptions = computed(() => mergeDurationOptions(props.durationOptions));

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const subscriberColumns = [
    { key: 'donor_name', label: 'Donor', sortable: false },
    { key: 'donor_email', label: 'Email', sortable: false },
    { key: 'amount', label: 'Monthly amount', sortable: false, align: 'right' },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'started_at', label: 'Started', sortable: false },
    { key: 'actions', label: '', sortable: false, align: 'right' },
];

const donationColumns = [
    { key: 'donor_name', label: 'Donor', sortable: false },
    { key: 'title', label: 'Title', sortable: false },
    { key: 'type', label: 'Type', sortable: false },
    { key: 'paid_at', label: 'Paid at', sortable: false },
    { key: 'amount', label: 'Amount', sortable: false, align: 'right' },
    { key: 'actions', label: '', sortable: false, align: 'right' },
];

const buildQuery = () => {
    const query = {
        duration: selectedDuration.value,
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
    router.get(props.campaign.stats_url ?? `/admin/campaigns/${props.campaign.id}`, buildQuery(), {
        preserveState: true,
        replace: true,
    });
};

const recentDonationRows = computed(() => props.recentDonations.map((row) => ({
    ...row,
    type: row.is_recurring ? 'Recurring' : 'One-time',
})));

const subscriberRows = computed(() => props.activeSubscribers.map((row) => ({
    ...row,
    amount: formatMoney(row.amount),
})));

const linkCopied = ref(false);

const copyShareLink = async () => {
    const url = props.campaign.share_url;

    if (! url) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            window.prompt('Copy this campaign link:', url);
            return;
        }

        linkCopied.value = true;
        window.setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    } catch {
        window.prompt('Copy this campaign link:', url);
    }
};
</script>

<template>
    <Head :title="`${campaign.name} · Campaign stats`" />
    <AdminLayout>
        <template #header>Campaign stats</template>
        <PageHeader :title="campaign.name" :subtitle="`${campaign.cause?.title ?? 'Campaign'} · ${durationLabel}`">
            <template #actions>
                <button
                    v-if="campaign.share_url"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    @click="copyShareLink"
                >
                    {{ linkCopied ? 'Link copied' : 'Copy link' }}
                </button>
                <a
                    v-if="campaign.public_url"
                    :href="campaign.public_url"
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    target="_blank"
                    rel="noopener"
                >
                    Open landing page
                </a>
                <Link :href="`/admin/campaigns/${campaign.id}/edit`" class="rounded-lg border border-border px-3 py-2 text-sm">Edit</Link>
                <Link href="/admin/campaigns" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4 shadow-none">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Duration</label>
                <select v-model="selectedDuration" class="rounded-lg border border-border px-3 py-2 text-sm" @change="onDurationChange">
                    <option v-for="(label, key) in periodOptions" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div v-if="isCustomRange">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">From</label>
                <input v-model="fromDate" type="date" class="rounded-lg border border-border px-3 py-2 text-sm">
            </div>
            <div v-if="isCustomRange">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">To</label>
                <input v-model="toDate" type="date" class="rounded-lg border border-border px-3 py-2 text-sm">
            </div>
            <button type="button" class="admin-btn-primary" @click="applyFilters">Apply</button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Total revenue" :value="formatMoney(summary.revenue)" :hint="`${summary.paid_count} paid donations`" tone="green" />
            <StatCard label="One-time" :value="formatMoney(summary.one_time_revenue)" :hint="`${summary.one_time_count} donations`" />
            <StatCard label="Recurring collected" :value="formatMoney(summary.recurring_revenue)" :hint="`${summary.recurring_paid_count} billing payments`" tone="purple" />
            <StatCard label="Page views" :value="summary.page_views" hint="Campaign landing page visits" tone="orange" />
        </div>

        <div
            v-if="summary.goal_amount"
            class="mt-4 rounded-xl border border-border bg-card p-5 shadow-none"
        >
            <div class="mb-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Campaign goal</p>
                    <p class="mt-1 text-sm text-foreground">
                        {{ formatMoney(summary.revenue) }} raised of {{ formatMoney(summary.goal_amount) }}
                    </p>
                </div>
                <p class="text-lg font-semibold text-foreground">{{ summary.goal_progress_percent ?? 0 }}%</p>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-emerald-500"
                    :style="{ width: `${Math.min(100, Number(summary.goal_progress_percent || 0))}%` }"
                />
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <StatCard label="Subscriptions started" :value="summary.subscriptions_started" />
            <StatCard label="Active recurring now" :value="summary.active_subscriptions" tone="green" />
            <StatCard label="Cancelled / ended" :value="summary.cancelled_subscriptions" tone="rose" />
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-6 shadow-none">
                <h3 class="mb-1 text-sm font-semibold">Active recurring donors</h3>
                <p class="mb-4 text-xs text-muted-foreground">Donors with a live monthly mandate from this campaign.</p>
                <DataTable
                    :columns="subscriberColumns"
                    :rows="subscriberRows"
                    empty-message="No active recurring donors for this campaign yet."
                >
                    <template #cell-actions="{ row }">
                        <Link :href="row.subscription_url" class="text-sm font-medium hover:underline">View</Link>
                    </template>
                </DataTable>
            </div>

            <div class="rounded-xl border border-border bg-card p-6 shadow-none">
                <h3 class="mb-1 text-sm font-semibold">Recent paid donations</h3>
                <p class="mb-4 text-xs text-muted-foreground">Latest successful payments attributed to this campaign.</p>
                <DataTable
                    :columns="donationColumns"
                    :rows="recentDonationRows"
                    empty-message="No paid donations for this campaign in the selected period."
                >
                    <template #cell-amount="{ row }">
                        <span>{{ formatMoney(row.amount) }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <Link v-if="row.donation_url" :href="row.donation_url" class="text-sm font-medium hover:underline">View</Link>
                    </template>
                </DataTable>
            </div>
        </div>

        <div v-if="multiCampaignDonors.length" class="mt-8 rounded-xl border border-border bg-card p-6 shadow-none">
            <h3 class="mb-1 text-sm font-semibold">Donors active in multiple campaigns</h3>
            <p class="mb-4 text-xs text-muted-foreground">Same donor with live recurring mandates across more than one campaign.</p>
            <div class="space-y-4">
                <div
                    v-for="(donor, index) in multiCampaignDonors"
                    :key="`${donor.donor_email}-${index}`"
                    class="rounded-lg border border-border bg-muted/50 p-4"
                >
                    <div class="font-medium text-foreground">{{ donor.donor_name }}</div>
                    <div class="text-sm text-muted-foreground">{{ donor.donor_email }} · {{ donor.donor_phone }}</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            v-for="campaignRow in donor.campaigns"
                            :key="campaignRow.id"
                            :href="campaignRow.stats_url"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-foreground hover:border-border"
                        >
                            <span>{{ campaignRow.name }}</span>
                            <span class="text-muted-foreground">{{ formatMoney(campaignRow.amount) }}/mo</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
