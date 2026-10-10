<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MessageSquareText } from '@lucide/vue';
import TelecallerConversationModal from '@/Components/Admin/TelecallerConversationModal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';
import { Button } from '@/Components/ui/button';
import { mergeDurationOptions } from '@/utils/periodOptions';

const telecallerOpen = ref(false);
const telecallerRow = ref(null);

const openTelecaller = (row) => {
    telecallerRow.value = row;
    telecallerOpen.value = true;
};

const onTelecallerSaved = (last) => {
    if (telecallerRow.value) {
        telecallerRow.value.last_telecaller_note = last;
    }
};

const props = defineProps({
    donations: { type: Object, required: true },
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    overviewDateLabel: { type: String, required: true },
    totalCount: { type: Number, required: true },
    totalAmount: { type: Number, required: true },
    sort: { type: String, default: 'created_at_ts' },
    dir: { type: String, default: 'desc' },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    duration: props.filters.from_date && props.filters.to_date ? 'custom' : props.duration,
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    search: props.filters.search ?? '',
    sort: props.sort ?? 'created_at_ts',
    dir: props.dir ?? 'desc',
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const isCustomRange = computed(() => form.duration === 'custom');
const periodOptions = computed(() => mergeDurationOptions(props.durationOptions));

const listQueryParams = (extra = {}) => {
    const payload = { ...form, ...extra };

    if (payload.from_date || payload.to_date) {
        payload.duration = 'custom';
    } else if (String(payload.search || '').trim() !== '' && payload.duration === 'today') {
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

    router.get('/admin/donations/telecaller', params, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    router.get('/admin/donations/telecaller', { duration: 'all' });
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

    return query ? `/admin/donations/telecaller?${query}` : '/admin/donations/telecaller';
});

const detailHref = (row) => {
    const params = new URLSearchParams({ return: currentListPath.value });

    return `/admin/donations/${row.uuid}?${params.toString()}`;
};

const columns = [
    { key: 'payment_id', label: 'Donation', sortable: true },
    { key: 'donor_name', label: 'Donor', sortable: true },
    { key: 'cause', label: 'Cause', sortable: true },
    { key: 'total_amount', label: 'Amount', sortable: true, align: 'right' },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'created_at_ts', label: 'Failed / date', sortable: true },
    { key: 'last_note', label: 'Notes', sortable: false },
    { key: 'actions', label: 'Action', sortable: false, align: 'right' },
];

const donationsList = computed(() => props.donations.data ?? []);

watch(() => props.duration, (value) => {
    if (! props.filters.from_date && ! props.filters.to_date) {
        form.duration = value;
    }
});
</script>

<template>
    <Head title="Failed donations" />

    <AdminLayout>
        <template #header>Failed donations</template>

        <PageHeader
            compact
            :title="`Failed donations · ${overviewDateLabel}`"
            :subtitle="`${totalCount.toLocaleString()} failed checkout(s) to follow up`"
        >
            <template #actions>
                <select
                    v-model="form.duration"
                    class="admin-input !w-auto min-w-[180px] py-2"
                    @change="onDurationChange"
                >
                    <option v-for="(label, value) in periodOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </template>
        </PageHeader>

        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50/50 p-4 shadow-none">
            <div class="text-xs font-medium uppercase tracking-wide text-rose-800">Failed checkouts</div>
            <div class="mt-1 text-2xl font-semibold text-rose-950">{{ totalCount.toLocaleString() }}</div>
            <p class="text-xs text-rose-800/80">
                {{ formatMoney(totalAmount) }} attempted · only failed payments are shown
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card shadow-none">
            <form class="grid gap-2 border-b border-border px-4 py-3 md:grid-cols-12" @submit.prevent="submit()">
                <div v-if="isCustomRange" class="md:col-span-2">
                    <FormDatePicker v-model="form.from_date" label="From" placeholder="From date" />
                </div>
                <div v-if="isCustomRange" class="md:col-span-2">
                    <FormDatePicker v-model="form.to_date" label="To" placeholder="To date" />
                </div>
                <div class="md:col-span-6">
                    <label class="admin-label !mb-1 !text-xs">Search</label>
                    <input
                        v-model="form.search"
                        type="search"
                        class="admin-input !py-2"
                        placeholder="Name, email, phone, payment id, receipt…"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-4">
                    <Button type="submit" class="admin-btn-primary !py-2">Search</Button>
                    <Button type="button" variant="outline" class="!py-2" @click="resetFilters">Reset</Button>
                </div>
            </form>

            <div class="admin-responsive-table min-w-0 overflow-x-auto">
                <DataTable
                    :columns="columns"
                    :rows="donationsList"
                    :sort-key="form.sort"
                    :sort-dir="form.dir"
                    empty-message="No failed donations in this period. Try All time or search by phone."
                    @sort="toggleSort"
                >
                    <template #cell-payment_id="{ row }">
                        <div class="font-medium text-foreground">#{{ row.payment_id }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.provider }}</div>
                    </template>
                    <template #cell-donor_name="{ row }">
                        <div class="w-44 truncate font-medium" :title="row.donor_name">{{ row.donor_name }}</div>
                        <div v-if="row.donor_phone" class="text-xs text-muted-foreground">{{ row.donor_phone }}</div>
                        <div v-else-if="row.donor_email" class="w-44 truncate text-xs text-muted-foreground">{{ row.donor_email }}</div>
                    </template>
                    <template #cell-cause="{ row }">
                        <div class="w-40 truncate font-medium" :title="row.cause">{{ row.cause }}</div>
                    </template>
                    <template #cell-total_amount="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    <template #cell-status="{ row }">
                        <div class="flex flex-col items-start gap-1">
                            <StatusBadge :status="row.status" />
                            <p
                                v-if="row.failure_label"
                                class="max-w-[10rem] truncate text-[11px] leading-tight text-rose-700"
                                :title="row.failure_detail || row.failure_label"
                            >
                                {{ row.failure_label }}
                            </p>
                            <Link
                                v-if="row.later_paid && row.later_paid_url"
                                :href="row.later_paid_url"
                                class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 hover:bg-amber-100"
                            >
                                Later paid
                            </Link>
                        </div>
                    </template>
                    <template #cell-created_at_ts="{ row }">
                        <div>{{ row.created_date }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.created_time }}</div>
                    </template>
                    <template #cell-last_note="{ row }">
                        <button
                            v-if="row.last_telecaller_note"
                            type="button"
                            class="block max-w-[10rem] truncate text-left"
                            @click="openTelecaller(row)"
                        >
                            <span class="block truncate text-sm font-medium">{{ row.last_telecaller_note.name || 'You' }}</span>
                            <span class="mt-0.5 block truncate text-[11px] text-muted-foreground">{{ row.last_telecaller_note.message }}</span>
                        </button>
                        <span v-else class="text-xs text-muted-foreground">No notes</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="inline-flex items-center justify-end gap-1">
                            <Button as-child variant="ghost" size="icon-sm" class="text-muted-foreground">
                                <Link :href="detailHref(row)" title="Details" aria-label="Details">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-emerald-700"
                                title="Log conversation"
                                aria-label="Log conversation"
                                @click="openTelecaller(row)"
                            >
                                <MessageSquareText class="size-4" />
                            </Button>
                        </div>
                    </template>
                    <template #footer>
                        <Pagination :links="donations.links" :meta="donations.meta" />
                    </template>
                </DataTable>
            </div>
        </div>

        <TelecallerConversationModal
            :open="telecallerOpen"
            :donation="telecallerRow"
            @close="telecallerOpen = false"
            @saved="onTelecallerSaved"
        />
    </AdminLayout>
</template>
