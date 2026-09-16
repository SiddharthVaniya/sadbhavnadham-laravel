<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    code: { type: String, default: null },
    partner_user_id: { type: Number, default: null },
    can_view_all: { type: Boolean, default: false },
    has_own_code: { type: Boolean, default: false },
    own_code: { type: String, default: null },
    own_share_url: { type: String, default: null },
    meta_ad_parameters: { type: String, default: null },
    meta_ad_url: { type: String, default: null },
    duration: { type: Object, required: true },
    durationOptions: { type: Object, required: true },
    statusOptions: { type: Object, required: true },
    matchOptions: { type: Object, required: true },
    summary: { type: Object, required: true },
    leaderboard: { type: Array, default: () => [] },
    donations: { type: Object, required: true },
    filter_options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    active_filter_count: { type: Number, default: 0 },
});

const form = reactive({
    duration: props.filters.duration || props.duration.key || 'this_month',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    partner_user_id: props.filters.partner_user_id ? String(props.filters.partner_user_id) : '',
    code: props.filters.code || '',
    content_search: props.filters.content_search || '',
    cause_id: props.filters.cause_id ? String(props.filters.cause_id) : '',
    status: props.filters.status || '',
    utm_campaign: props.filters.utm_campaign || '',
    utm_source: props.filters.utm_source || '',
    match: props.filters.match || '',
    search: props.filters.search || '',
});

watch(
    () => props.filters,
    (next) => {
        form.duration = next.duration || 'this_month';
        form.from_date = next.from_date || '';
        form.to_date = next.to_date || '';
        form.partner_user_id = next.partner_user_id ? String(next.partner_user_id) : '';
        form.code = next.code || '';
        form.content_search = next.content_search || '';
        form.cause_id = next.cause_id ? String(next.cause_id) : '';
        form.status = next.status || '';
        form.utm_campaign = next.utm_campaign || '';
        form.utm_source = next.utm_source || '';
        form.match = next.match || '';
        form.search = next.search || '';
    },
    { deep: true },
);

const isCustomRange = computed(() => form.duration === 'custom');

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const buildQuery = (overrides = {}) => {
    const payload = {
        duration: overrides.duration ?? form.duration,
        from_date: overrides.from_date ?? form.from_date,
        to_date: overrides.to_date ?? form.to_date,
        partner_user_id: overrides.partner_user_id ?? form.partner_user_id,
        code: overrides.code !== undefined ? overrides.code : form.code,
        content_search: overrides.content_search ?? form.content_search,
        cause_id: overrides.cause_id ?? form.cause_id,
        status: overrides.status ?? form.status,
        utm_campaign: overrides.utm_campaign ?? form.utm_campaign,
        utm_source: overrides.utm_source ?? form.utm_source,
        match: overrides.match ?? form.match,
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
    router.get('/admin/referrals', buildQuery(overrides), {
        preserveState: true,
        replace: true,
    });
};

const onDurationChange = () => {
    if (form.duration === 'custom' && ! form.from_date && ! form.to_date) {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        form.from_date = start.toISOString().slice(0, 10);
        form.to_date = now.toISOString().slice(0, 10);
    }

    applyFilters();
};

const onPartnerChange = () => {
    if (form.partner_user_id) {
        const partner = (props.filter_options.partners || []).find(
            (row) => String(row.id) === String(form.partner_user_id),
        );
        form.code = partner?.code || '';
    }

    applyFilters();
};

const resetFilters = () => {
    form.duration = 'this_month';
    form.from_date = '';
    form.to_date = '';
    form.partner_user_id = '';
    form.code = '';
    form.content_search = '';
    form.cause_id = '';
    form.status = '';
    form.utm_campaign = '';
    form.utm_source = '';
    form.match = '';
    form.search = '';
    applyFilters({
        duration: 'this_month',
        from_date: '',
        to_date: '',
        partner_user_id: '',
        code: '',
        content_search: '',
        cause_id: '',
        status: '',
        utm_campaign: '',
        utm_source: '',
        match: '',
        search: '',
    });
};

const selectPartnerCode = (code) => {
    form.partner_user_id = '';
    form.code = code;
    applyFilters({ partner_user_id: '', code });
};

const clearCode = () => {
    form.partner_user_id = '';
    form.code = '';
    applyFilters({ partner_user_id: '', code: '' });
};

const title = computed(() => {
    if (props.code) {
        return `Partner attribution · ${props.code}`;
    }

    return props.can_view_all ? 'Partner attribution' : 'My attribution';
});

const subtitle = computed(() => {
    if (! props.has_own_code && ! props.can_view_all) {
        return 'Ask an admin to assign your tracking code on your user profile.';
    }

    if (props.code) {
        return `Performance for tracking code ${props.code} · ${props.duration.label}`;
    }

    return `Measure donations attributed to partner share links · ${props.duration.label}`;
});

const donationColumns = [
    { key: 'donor_name', label: 'Donor', sortable: false },
    { key: 'cause', label: 'Cause', sortable: false },
    { key: 'utm_content', label: 'Code', sortable: false },
    { key: 'total_amount', label: 'Amount', sortable: false, align: 'right' },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'created_date', label: 'Date', sortable: false },
];

const leaderboardColumns = [
    { key: 'code', label: 'Code', sortable: false },
    { key: 'name', label: 'Partner', sortable: false },
    { key: 'paid_orders', label: 'Paid', sortable: false, align: 'right' },
    { key: 'revenue', label: 'Revenue', sortable: false, align: 'right' },
];

const donationRows = computed(() =>
    (props.donations.data || []).map((row) => ({
        ...row,
        total_amount: formatMoney(row.total_amount),
    })),
);

const leaderboardRows = computed(() =>
    (props.leaderboard || []).map((row) => ({
        ...row,
        name: row.name || (row.is_partner ? 'Partner' : '—'),
        revenue: formatMoney(row.revenue),
    })),
);

const dynamicTokenExample = '{{campaign.id}}';

const copiedKey = ref(null);

const copyText = async (key, value) => {
    if (! value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
    } catch {
        const field = document.createElement('textarea');
        field.value = value;
        document.body.appendChild(field);
        field.select();
        document.execCommand('copy');
        document.body.removeChild(field);
    }

    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) {
            copiedKey.value = null;
        }
    }, 2000);
};
</script>

<template>
    <Head :title="title" />
    <AdminLayout>
        <template #header>Partner attribution</template>
        <PageHeader :title="title" :subtitle="subtitle">
            <template #actions>
                <Button
                    v-if="can_view_all && code"
                    type="button"
                    variant="outline"
                    @click="clearCode"
                >
                    Clear partner filter
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="! has_own_code && ! can_view_all"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Your account has no tracking code yet. Ask an admin to set one under
            <Link href="/admin/users" class="underline">Users</Link>, then share your tracked donate links.
        </div>

        <div v-else class="space-y-6">
            <form
                class="rounded-xl border border-border bg-card p-4 shadow-none"
                @submit.prevent="applyFilters()"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-foreground">Advanced filters</h2>
                    <div class="flex items-center gap-2">
                        <span v-if="active_filter_count" class="text-xs text-muted-foreground">
                            {{ active_filter_count }} active
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                        <button type="submit" class="admin-btn-primary !py-1.5">Apply</button>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="admin-label !mb-1 !text-xs">Period</label>
                        <select v-model="form.duration" class="admin-input !py-2" @change="onDurationChange">
                            <option
                                v-for="(label, key) in durationOptions"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="isCustomRange">
                        <label class="admin-label !mb-1 !text-xs">From</label>
                        <input v-model="form.from_date" type="date" class="admin-input !py-2">
                    </div>
                    <div v-if="isCustomRange">
                        <label class="admin-label !mb-1 !text-xs">To</label>
                        <input v-model="form.to_date" type="date" class="admin-input !py-2">
                    </div>
                    <div>
                        <label class="admin-label !mb-1 !text-xs">Status</label>
                        <select v-model="form.status" class="admin-input !py-2">
                            <option
                                v-for="(label, key) in statusOptions"
                                :key="key || 'all'"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="can_view_all">
                        <label class="admin-label !mb-1 !text-xs">Partner (user)</label>
                        <select v-model="form.partner_user_id" class="admin-input !py-2" @change="onPartnerChange">
                            <option value="">All partners</option>
                            <option
                                v-for="partner in filter_options.partners || []"
                                :key="partner.id"
                                :value="String(partner.id)"
                            >
                                {{ partner.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="can_view_all">
                        <label class="admin-label !mb-1 !text-xs">Tracking code</label>
                        <select v-model="form.code" class="admin-input !py-2">
                            <option value="">All codes</option>
                            <option
                                v-for="trackingCode in filter_options.tracking_codes || []"
                                :key="trackingCode"
                                :value="trackingCode"
                            >
                                {{ trackingCode }}
                            </option>
                        </select>
                    </div>
                    <div v-if="can_view_all">
                        <label class="admin-label !mb-1 !text-xs">Code contains</label>
                        <input
                            v-model="form.content_search"
                            type="text"
                            class="admin-input !py-2"
                            placeholder="Search tracking code text"
                        >
                    </div>
                    <div v-if="can_view_all">
                        <label class="admin-label !mb-1 !text-xs">Match type</label>
                        <select v-model="form.match" class="admin-input !py-2">
                            <option
                                v-for="(label, key) in matchOptions"
                                :key="key || 'all-match'"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="admin-label !mb-1 !text-xs">Cause</label>
                        <select v-model="form.cause_id" class="admin-input !py-2">
                            <option value="">All causes</option>
                            <option
                                v-for="cause in filter_options.causes || []"
                                :key="cause.id"
                                :value="String(cause.id)"
                            >
                                {{ cause.title }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label !mb-1 !text-xs">Campaign (utm_campaign)</label>
                        <select v-model="form.utm_campaign" class="admin-input !py-2">
                            <option value="">All campaigns</option>
                            <option
                                v-for="campaign in filter_options.campaigns || []"
                                :key="campaign"
                                :value="campaign"
                            >
                                {{ campaign }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label !mb-1 !text-xs">Source (utm_source)</label>
                        <select v-model="form.utm_source" class="admin-input !py-2">
                            <option value="">All sources</option>
                            <option
                                v-for="source in filter_options.sources || []"
                                :key="source"
                                :value="source"
                            >
                                {{ source }}
                            </option>
                        </select>
                    </div>
                    <div class="md:col-span-2 xl:col-span-1">
                        <label class="admin-label !mb-1 !text-xs">Search donor / payment</label>
                        <input
                            v-model="form.search"
                            type="text"
                            class="admin-input !py-2"
                            placeholder="Name, email, phone, receipt…"
                        >
                    </div>
                </div>
            </form>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Paid donations</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.paid_orders }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Paid amount</CardDescription>
                        <CardTitle class="text-2xl">{{ formatMoney(summary.revenue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Pending checkouts</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.pending_orders }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Pending amount</CardDescription>
                        <CardTitle class="text-2xl">{{ formatMoney(summary.pending_amount) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Failed payments</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.failed_orders || 0 }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Failed amount</CardDescription>
                        <CardTitle class="text-2xl">{{ formatMoney(summary.failed_amount || 0) }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card v-if="own_code">
                <CardHeader>
                    <CardTitle class="text-base">Your tracking links</CardTitle>
                    <CardDescription>
                        Your partner code is <code class="rounded bg-muted px-1 font-mono">{{ own_code }}</code>.
                        Copy cause or package links from admin while logged in — the code is added automatically.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-foreground">Share link (WhatsApp, bio, email)</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="copyText('share', own_share_url)"
                            >
                                {{ copiedKey === 'share' ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                        <code class="block break-all rounded bg-muted px-2 py-1.5 text-xs">{{ own_share_url }}</code>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-foreground">Meta Ads Manager — URL parameters</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="copyText('meta-params', meta_ad_parameters)"
                            >
                                {{ copiedKey === 'meta-params' ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                        <code class="block break-all rounded bg-muted px-2 py-1.5 text-xs">{{ meta_ad_parameters }}</code>
                        <p class="text-xs text-muted-foreground">
                            Paste this into the <span class="font-medium">URL parameters</span> field of every ad
                            (Ad level → Tracking). Set the website URL to the cause page you want, and leave the
                            <code class="font-mono">{{ dynamicTokenExample }}</code> tokens exactly as they are — Meta
                            fills them with the real campaign, ad set and ad IDs on each click.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-foreground">Full ad destination URL</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="copyText('meta-url', meta_ad_url)"
                            >
                                {{ copiedKey === 'meta-url' ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                        <code class="block break-all rounded bg-muted px-2 py-1.5 text-xs">{{ meta_ad_url }}</code>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="can_view_all">
                <CardHeader>
                    <CardTitle class="text-base">Partner performance</CardTitle>
                    <CardDescription>
                        Paid donations by tracking code for the current filters. Click a row to filter.
                        Partner name shows when the code matches a registered user.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="leaderboardColumns"
                        :rows="leaderboardRows"
                        empty-message="No attributed donations in this period."
                    >
                        <template #cell-code="{ row }">
                            <button
                                type="button"
                                class="max-w-[28rem] truncate text-left font-mono text-sm text-primary underline-offset-2 hover:underline"
                                :title="row.code"
                                @click="selectPartnerCode(row.code)"
                            >
                                {{ row.code }}
                            </button>
                        </template>
                        <template #cell-name="{ row }">
                            <span :class="row.is_partner ? 'font-medium text-foreground' : 'text-muted-foreground'">
                                {{ row.name }}
                            </span>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ code ? `Attributed donations · ${code}` : 'Attributed donations' }}
                    </CardTitle>
                    <CardDescription>
                        Orders linked to tracking codes for the selected filters.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="donationColumns"
                        :rows="donationRows"
                        empty-message="No attributed donations yet for this view."
                    >
                        <template #cell-utm_content="{ row }">
                            <span class="font-mono text-sm">{{ row.utm_content || '—' }}</span>
                        </template>
                        <template #cell-donor_name="{ row }">
                            <Link
                                v-if="row.id"
                                :href="`/admin/donations/${row.id}`"
                                class="font-medium text-foreground hover:underline"
                            >
                                {{ row.donor_name }}
                            </Link>
                            <span v-else>{{ row.donor_name }}</span>
                        </template>
                        <template #footer>
                            <Pagination :links="donations.links" :meta="donations.meta" />
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
