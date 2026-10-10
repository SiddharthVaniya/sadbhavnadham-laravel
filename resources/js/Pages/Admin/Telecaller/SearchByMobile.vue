<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MessageSquareText, Search } from '@lucide/vue';
import TelecallerConversationModal from '@/Components/Admin/TelecallerConversationModal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import { Button } from '@/Components/ui/button';

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
    donations: { type: Object, default: null },
    phone: { type: String, default: '' },
    normalizedPhone: { type: String, default: null },
    hasSearch: { type: Boolean, default: false },
    sort: { type: String, default: 'created_at_ts' },
    dir: { type: String, default: 'desc' },
});

const form = reactive({
    phone: props.phone,
    sort: props.sort,
    dir: props.dir,
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const digitsOnly = (value) => String(value || '').replace(/\D/g, '');

const canSubmit = computed(() => digitsOnly(form.phone).length >= 8);

const submit = () => {
    if (! canSubmit.value) {
        return;
    }

    router.get('/admin/donations/telecaller/search', {
        phone: digitsOnly(form.phone),
        sort: form.sort,
        dir: form.dir,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    form.phone = '';
    router.get('/admin/donations/telecaller/search');
};

const toggleSort = (key) => {
    if (form.sort === key) {
        form.dir = form.dir === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = key;
        form.dir = key === 'created_at_ts' ? 'desc' : 'asc';
    }

    if (props.hasSearch) {
        submit();
    }
};

const currentListPath = computed(() => {
    if (! props.hasSearch) {
        return '/admin/donations/telecaller/search';
    }

    const params = new URLSearchParams({
        phone: props.normalizedPhone || digitsOnly(props.phone),
        sort: form.sort,
        dir: form.dir,
        page: String(props.donations?.meta?.current_page || 1),
    });

    return `/admin/donations/telecaller/search?${params.toString()}`;
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
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at_ts', label: 'Date', sortable: true },
    { key: 'last_note', label: 'Notes', sortable: false },
    { key: 'actions', label: 'Action', sortable: false, align: 'right' },
];

const donationsList = computed(() => props.donations?.data ?? []);
const resultCount = computed(() => props.donations?.meta?.total ?? donationsList.value.length);
</script>

<template>
    <Head title="Search by mobile" />

    <AdminLayout>
        <template #header>Search by mobile</template>

        <PageHeader
            compact
            title="Look up donation"
            subtitle="Enter the donor's mobile number to see every donation attempt (paid, failed, pending)."
        />

        <div class="mb-4 rounded-xl border border-border bg-card p-4 shadow-none">
            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submit">
                <div class="min-w-0 flex-1">
                    <label class="admin-label !mb-1">Mobile number</label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        class="admin-input !py-2.5 text-lg tracking-wide"
                        placeholder="10-digit mobile (e.g. 9876543210)"
                        maxlength="15"
                    />
                    <p v-if="normalizedPhone && hasSearch" class="mt-1 text-xs text-muted-foreground">
                        Searching for: {{ normalizedPhone }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Button type="submit" class="admin-btn-primary !gap-2 !py-2.5" :disabled="!canSubmit">
                        <Search class="size-4" />
                        Search
                    </Button>
                    <Button v-if="hasSearch" type="button" variant="outline" class="!py-2.5" @click="clearSearch">
                        Clear
                    </Button>
                </div>
            </form>
        </div>

        <div v-if="hasSearch && donations" class="rounded-xl border border-border bg-card shadow-none">
            <div class="border-b border-border px-4 py-3 text-sm text-muted-foreground">
                <span class="font-medium text-foreground">{{ resultCount.toLocaleString() }}</span>
                donation(s) for this number
            </div>

            <div class="admin-responsive-table min-w-0 overflow-x-auto">
                <DataTable
                    :columns="columns"
                    :rows="donationsList"
                    :sort-key="form.sort"
                    :sort-dir="form.dir"
                    empty-message="No donations found for this mobile number."
                    @sort="toggleSort"
                >
                    <template #cell-payment_id="{ row }">
                        <div class="font-medium text-foreground">#{{ row.payment_id }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.provider }}</div>
                    </template>
                    <template #cell-donor_name="{ row }">
                        <div class="w-40 truncate font-medium" :title="row.donor_name">{{ row.donor_name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.donor_phone }}</div>
                    </template>
                    <template #cell-cause="{ row }">
                        <div class="w-40 truncate" :title="row.cause">{{ row.cause }}</div>
                    </template>
                    <template #cell-total_amount="{ row }">{{ formatMoney(row.total_amount) }}</template>
                    <template #cell-status="{ row }">
                        <div class="flex flex-col items-start gap-1">
                            <StatusBadge :status="row.status" />
                            <p
                                v-if="row.failure_label"
                                class="max-w-[10rem] truncate text-[11px] text-rose-700"
                                :title="row.failure_detail || row.failure_label"
                            >
                                {{ row.failure_label }}
                            </p>
                            <Link
                                v-if="row.later_paid && row.later_paid_url"
                                :href="row.later_paid_url"
                                class="text-[10px] font-semibold uppercase text-amber-800"
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
                            class="max-w-[10rem] truncate text-left text-sm"
                            @click="openTelecaller(row)"
                        >
                            {{ row.last_telecaller_note.message }}
                        </button>
                        <span v-else class="text-xs text-muted-foreground">—</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="inline-flex gap-1">
                            <Button as-child variant="ghost" size="icon-sm">
                                <Link :href="detailHref(row)" title="Details">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                            <Button variant="ghost" size="icon-sm" class="text-emerald-700" @click="openTelecaller(row)">
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

        <p v-else-if="hasSearch && !donations" class="text-center text-sm text-muted-foreground">
            Enter at least 8 digits to search.
        </p>

        <TelecallerConversationModal
            :open="telecallerOpen"
            :donation="telecallerRow"
            @close="telecallerOpen = false"
            @saved="onTelecallerSaved"
        />
    </AdminLayout>
</template>
