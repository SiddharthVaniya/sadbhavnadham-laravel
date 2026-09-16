<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import FormSelect from '@/Components/Admin/FormSelect.vue';
import FormInput from '@/Components/Admin/FormInput.vue';

const props = defineProps({
    orders: { type: Object, required: true },
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    stats: { type: Object, required: true },
    activeFilterCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    nudgeUrl: { type: String, required: true },
    canNudge: { type: Boolean, default: false },
});

const form = reactive({
    duration: props.duration,
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
    nudge: props.filters.nudge ?? '',
});

const selected = ref([]);

const nudgeForm = useForm({
    order_ids: [],
});

const columns = [
    { key: 'select', label: '', sortable: false },
    { key: 'donor_name', label: 'Donor', sortable: false },
    { key: 'cause', label: 'Cause', sortable: false },
    { key: 'total_amount', label: 'Amount', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'nudge_label', label: 'Nudge', sortable: false },
    { key: 'created', label: 'Checkout', sortable: false },
    { key: 'actions', label: '', sortable: false, align: 'right' },
];

const rows = computed(() => props.orders.data ?? []);
const selectableIds = computed(() => rows.value.filter((row) => row.can_nudge).map((row) => row.id));
const allSelected = computed(() => selectableIds.value.length > 0 && selectableIds.value.every((id) => selected.value.includes(id)));

const durationChoices = computed(() => Object.entries(props.durationOptions).map(([value, label]) => ({ value, label })));

const listQueryParams = (extra = {}) => {
    const payload = { ...form, ...extra };
    const params = {};

    Object.entries(payload).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params[key] = String(value);
        }
    });

    return params;
};

const submit = (extra = {}) => {
    router.get('/admin/donations/recovery', listQueryParams(extra), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    router.get('/admin/donations/recovery', { duration: 'last_7_days' });
};

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...selectableIds.value];
};

const toggleRow = (id) => {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((rowId) => rowId !== id);
    } else {
        selected.value = [...selected.value, id];
    }
};

const nudgeSelected = () => {
    if (! props.canNudge || selected.value.length === 0) {
        return;
    }

    nudgeForm.order_ids = [...selected.value];
    nudgeForm.post(props.nudgeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            nudgeForm.reset();
        },
    });
};

const nudgeOne = (id) => {
    if (! props.canNudge) {
        return;
    }

    nudgeForm.order_ids = [id];
    nudgeForm.post(props.nudgeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = selected.value.filter((rowId) => rowId !== id);
            nudgeForm.reset();
        },
    });
};

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
</script>

<template>
    <Head title="Checkout recovery" />
    <AdminLayout>
        <template #header>Checkout recovery</template>

        <PageHeader
            title="Checkout recovery"
            :subtitle="`${stats.total_count.toLocaleString()} abandoned checkouts · ${formatMoney(stats.total_amount)}`"
        >
            <template #actions>
                <button
                    v-if="canNudge"
                    type="button"
                    class="admin-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="selected.length === 0 || nudgeForm.processing"
                    @click="nudgeSelected"
                >
                    Nudge selected ({{ selected.length }})
                </button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap gap-2.5">
            <StatCard
                compact
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[13rem]"
                label="Abandoned"
                :value="stats.total_count.toLocaleString()"
            />
            <StatCard
                compact
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[13rem]"
                label="Pending"
                :value="stats.pending_count.toLocaleString()"
            />
            <StatCard
                compact
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[13rem]"
                label="Failed"
                :value="stats.failed_count.toLocaleString()"
            />
            <StatCard
                compact
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[13rem]"
                label="Awaiting nudge"
                :value="stats.ready_count.toLocaleString()"
            />
        </div>

        <div class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 shadow-none">
            <FormSelect
                v-model="form.duration"
                label="Duration"
                :options="durationChoices"
                @update:model-value="submit()"
            />
            <FormSelect
                v-model="form.status"
                label="Status"
                :options="[
                    { value: '', label: 'Pending + failed' },
                    { value: 'pending', label: 'Pending only' },
                    { value: 'failed', label: 'Failed only' },
                ]"
                @update:model-value="submit()"
            />
            <FormSelect
                v-model="form.nudge"
                label="Nudge state"
                :options="[
                    { value: '', label: 'Any' },
                    { value: 'ready', label: 'Not sent yet' },
                    { value: 'sent', label: 'Already sent' },
                    { value: 'no_phone', label: 'Missing phone' },
                ]"
                @update:model-value="submit()"
            />
            <FormInput
                v-model="form.search"
                label="Search"
                placeholder="Name, phone, email, order"
                @keyup.enter="submit()"
            />
        </div>

        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                Shows unpaid checkouts older than 5 minutes. Pending checkouts are marked failed when nudged so payment links can be created.
            </p>
            <button
                v-if="activeFilterCount > 0"
                type="button"
                class="shrink-0 text-sm font-medium text-muted-foreground hover:text-foreground"
                @click="resetFilters"
            >
                Reset filters
            </button>
        </div>

        <DataTable :columns="columns" :rows="rows">
            <template #head-select>
                <input
                    type="checkbox"
                    class="rounded border-border"
                    :checked="allSelected"
                    :disabled="! canNudge || selectableIds.length === 0"
                    aria-label="Select all nudgeable checkouts"
                    @change="toggleAll"
                >
            </template>
            <template #cell-select="{ row }">
                <input
                    type="checkbox"
                    class="rounded border-border"
                    :checked="selected.includes(row.id)"
                    :disabled="! canNudge || ! row.can_nudge"
                    :aria-label="`Select ${row.donor_name}`"
                    @change="toggleRow(row.id)"
                >
            </template>
            <template #cell-donor_name="{ row }">
                <div class="min-w-0">
                    <div class="truncate font-medium text-foreground">{{ row.donor_name }}</div>
                    <div class="truncate text-xs text-muted-foreground">{{ row.donor_phone || 'No phone' }}</div>
                </div>
            </template>
            <template #cell-total_amount="{ row }">
                {{ formatMoney(row.total_amount) }}
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :status="row.status" />
            </template>
            <template #cell-nudge_label="{ row }">
                <span class="text-xs font-medium text-muted-foreground">{{ row.nudge_label }}</span>
                <div v-if="row.payment_link_sent_at" class="text-[11px] text-muted-foreground">{{ row.payment_link_sent_at }}</div>
            </template>
            <template #cell-created="{ row }">
                <div>{{ row.created_date }}</div>
                <div class="text-[11px] text-muted-foreground">{{ row.created_time }}</div>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <button
                        v-if="canNudge && row.can_nudge"
                        type="button"
                        class="text-xs font-medium text-foreground hover:text-foreground"
                        :disabled="nudgeForm.processing"
                        @click="nudgeOne(row.id)"
                    >
                        Nudge
                    </button>
                    <Link :href="row.show_url" class="text-xs font-medium text-muted-foreground hover:text-foreground">
                        View
                    </Link>
                </div>
            </template>
        </DataTable>

        <Pagination class="mt-4" :links="orders.links" :meta="orders.meta" />
    </AdminLayout>
</template>
