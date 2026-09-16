<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';

const props = defineProps({
    donors: { type: Object, required: true },
    stats: { type: Object, required: true },
    activeFilterCount: { type: Number, default: 0 },
    can_export: { type: Boolean, default: false },
    sourceOptions: { type: Array, default: () => [] },
    cityOptions: { type: Array, default: () => [] },
    stateOptions: { type: Array, default: () => [] },
    staffOptions: { type: Array, default: () => [] },
    urls: {
        type: Object,
        default: () => ({
            export: '/admin/donors-export',
            import: '/admin/donors-import',
            import_template: '/admin/donors-import/template',
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            sort: 'last_paid_at',
            dir: 'desc',
            repeat: false,
            search: '',
            city: '',
            state: '',
            source: '',
            from_date: '',
            to_date: '',
            min_paid: '',
            owner_user_id: '',
        }),
    },
});

const page = usePage();
const importInput = ref(null);
const importForm = useForm({
    file: null,
});

const form = reactive({
    sort: props.filters.sort ?? 'last_paid_at',
    dir: props.filters.dir ?? 'desc',
    repeat: Boolean(props.filters.repeat),
    search: props.filters.search ?? '',
    city: props.filters.city ?? '',
    state: props.filters.state ?? '',
    source: props.filters.source ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    min_paid: props.filters.min_paid ?? '',
    owner_user_id: props.filters.owner_user_id ?? '',
});

const columns = [
    { key: 'name', label: 'Donor', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'phone', label: 'Phone', sortable: true },
    { key: 'location', label: 'Location', sortable: true },
    { key: 'owner', label: 'Owner', sortable: false },
    { key: 'source', label: 'Source', sortable: true },
    { key: 'paid_donations', label: 'Paid', sortable: true },
    { key: 'paid_amount', label: 'Paid amount', sortable: true },
    { key: 'open_tasks_count', label: 'Open tasks', sortable: false },
    { key: 'total_attempts', label: 'Attempts', sortable: true },
    { key: 'last_paid_at', label: 'Last paid', sortable: true },
    { key: 'actions', label: '', sortable: false, align: 'right' },
];

const donorsRows = computed(() => props.donors.data ?? []);
const sortKey = computed(() => form.sort);
const sortDir = computed(() => form.dir);
const repeatFilterActive = computed(() => Boolean(form.repeat));

const listQueryParams = (extra = {}) => {
    const payload = { ...form, ...extra };
    const params = {};

    Object.entries(payload).forEach(([key, value]) => {
        if (key === 'repeat') {
            if (value) {
                params.repeat = 1;
            }

            return;
        }

        if (value !== '' && value !== null && value !== undefined && value !== false) {
            params[key] = String(value);
        }
    });

    return params;
};

const visitDonors = (extra = {}) => {
    router.get('/admin/donors', listQueryParams(extra), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const submit = () => visitDonors();

const resetFilters = () => {
    router.get('/admin/donors', { sort: 'last_paid_at', dir: 'desc' });
};

const toggleSort = (key) => {
    if (form.sort === key) {
        form.dir = form.dir === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = key;
        form.dir = key === 'last_paid_at' || key === 'paid_amount' ? 'desc' : 'asc';
    }

    visitDonors();
};

const toggleRepeatFilter = () => {
    form.repeat = ! form.repeat;
    visitDonors();
};

const currentListPath = computed(() => {
    const params = new URLSearchParams(listQueryParams({
        page: props.donors.meta?.current_page || 1,
    }));
    const query = params.toString();

    return query ? `/admin/donors?${query}` : '/admin/donors';
});

const donorHref = (row) => {
    const params = new URLSearchParams({ return: currentListPath.value });

    return `/admin/donors/${row.id}?${params.toString()}`;
};

const exportUrl = computed(() => {
    const params = new URLSearchParams(listQueryParams());
    const query = params.toString();

    return query ? `${props.urls.export}?${query}` : props.urls.export;
});

const openImportPicker = () => {
    importInput.value?.click();
};

const onImportSelected = (event) => {
    const file = event.target.files?.[0];

    if (! file) {
        return;
    }

    importForm.file = file;
    importForm.post(props.urls.import, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.reset('file');
            if (importInput.value) {
                importInput.value.value = '';
            }
        },
    });
};

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const initials = (name) => (name || '?').trim().charAt(0).toUpperCase();
</script>

<template>
    <Head title="Donors" />
    <AdminLayout>
        <template #header>Donors</template>
        <PageHeader
            title="Donors"
            :subtitle="`${stats.total_donors.toLocaleString()} donors · ${formatMoney(stats.total_amount)} paid total`"
        >
            <template v-if="can_export" #actions>
                <a :href="urls.import_template" class="admin-btn-secondary !py-2">
                    CSV template
                </a>
                <button
                    type="button"
                    class="admin-btn-secondary !py-2"
                    :disabled="importForm.processing"
                    @click="openImportPicker"
                >
                    {{ importForm.processing ? 'Importing…' : 'Import CSV' }}
                </button>
                <a :href="exportUrl" class="admin-btn-secondary !py-2" title="Export filtered donors as CSV">
                    Export CSV
                </a>
                <input
                    ref="importInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="hidden"
                    @change="onImportSelected"
                >
            </template>
        </PageHeader>
        <p v-if="page.props.flash?.status" class="mb-4 text-sm text-emerald-700">{{ page.props.flash.status }}</p>
        <p v-if="importForm.errors.file" class="mb-4 text-sm text-rose-600" role="alert">{{ importForm.errors.file }}</p>
        <div class="mb-4 flex flex-wrap gap-2.5">
            <StatCard
                compact
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[13rem]"
                label="Total donors"
                :value="stats.total_donors.toLocaleString()"
            />
            <StatCard
                compact
                tone="green"
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] sm:max-w-[15rem]"
                label="Paid amount"
                :value="formatMoney(stats.total_amount)"
            />
            <button
                type="button"
                class="min-w-[9.5rem] flex-1 basis-[9.5rem] rounded-lg text-left transition sm:max-w-[13rem] ring-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :class="repeatFilterActive ? 'ring-2 ring-orange-400' : 'hover:brightness-[0.98]'"
                :aria-pressed="repeatFilterActive"
                @click="toggleRepeatFilter"
            >
                <StatCard
                    compact
                    tone="orange"
                    label="Repeat donors"
                    :value="stats.repeat_donors.toLocaleString()"
                    :hint="repeatFilterActive ? 'Filtered · click to clear' : 'Click to filter'"
                />
            </button>
        </div>

        <div class="mb-4 rounded-xl border border-border bg-card p-4 shadow-none">
            <form class="grid gap-2 md:grid-cols-12" @submit.prevent="submit">
                <div class="md:col-span-4">
                    <label class="admin-label !mb-1 !text-xs">Search</label>
                    <input
                        v-model="form.search"
                        type="search"
                        class="admin-input !py-2"
                        placeholder="Name, email, phone, city…"
                    >
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">City</label>
                    <select v-model="form.city" class="admin-input !py-2">
                        <option value="">All cities</option>
                        <option v-for="city in cityOptions" :key="city" :value="city">{{ city }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">State</label>
                    <select v-model="form.state" class="admin-input !py-2">
                        <option value="">All states</option>
                        <option v-for="state in stateOptions" :key="state" :value="state">{{ state }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Source</label>
                    <select v-model="form.source" class="admin-input !py-2">
                        <option value="">All sources</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Owner</label>
                    <select v-model="form.owner_user_id" class="admin-input !py-2">
                        <option value="">All owners</option>
                        <option v-for="option in staffOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="admin-label !mb-1 !text-xs">Min paid ₹</label>
                    <input v-model="form.min_paid" type="number" min="0" step="1" class="admin-input !py-2" placeholder="e.g. 5000">
                </div>
                <div class="md:col-span-2">
                    <FormDatePicker v-model="form.from_date" label="Last paid from" placeholder="From date" />
                </div>
                <div class="md:col-span-2">
                    <FormDatePicker v-model="form.to_date" label="Last paid to" placeholder="To date" />
                </div>
                <div class="flex items-end gap-2 md:col-span-4">
                    <button type="submit" class="admin-btn-primary !py-2">Apply</button>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm text-foreground hover:bg-muted" @click="resetFilters">
                        Reset
                    </button>
                    <span v-if="activeFilterCount" class="text-xs text-muted-foreground">{{ activeFilterCount }} active filter(s)</span>
                </div>
            </form>
            <p v-if="repeatFilterActive" class="mt-3 text-sm text-orange-800">
                Showing donors with more than one paid donation.
                <button type="button" class="font-medium underline" @click="toggleRepeatFilter">Show all donors</button>
            </p>
        </div>

        <DataTable :columns="columns" :rows="donorsRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground">{{ initials(row.name) }}</div>
                    <span class="font-medium text-foreground">{{ row.name }}</span>
                </div>
            </template>
            <template #cell-source="{ row }">
                <div class="font-medium text-foreground">{{ row.source }}</div>
                <div v-if="row.utm_campaign" class="max-w-[140px] truncate text-xs text-muted-foreground" :title="row.utm_campaign">
                    {{ row.utm_campaign }}
                </div>
            </template>
            <template #cell-owner="{ row }">
                <span class="text-sm text-foreground">{{ row.owner?.name || '—' }}</span>
            </template>
            <template #cell-open_tasks_count="{ row }">
                <span :class="row.open_tasks_count > 0 ? 'font-medium text-amber-700' : 'text-muted-foreground'">
                    {{ row.open_tasks_count }}
                </span>
            </template>
            <template #cell-paid_amount="{ row }">{{ formatMoney(row.paid_amount) }}</template>
            <template #cell-last_paid_at="{ row }">{{ row.last_paid_at || '—' }}</template>
            <template #cell-actions="{ row }">
                <Link :href="donorHref(row)" class="text-sm font-medium hover:underline">View</Link>
            </template>
            <template #footer>
                <Pagination :links="donors.links" :meta="donors.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
