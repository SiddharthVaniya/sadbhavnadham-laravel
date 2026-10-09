<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { useClientSort } from '@/composables/useClientSort';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

const props = defineProps({
    packages: { type: Object, required: true },
    abilities: {
        type: Object,
        default: () => ({
            can_view: true,
            can_create: false,
            can_edit: false,
            can_delete: false,
            can_copy_links: false,
        }),
    },
});

const page = usePage();
const isMarketerPortal = computed(() => page.props.portal?.label === 'Marketer');

const columns = computed(() => {
    const base = [
        { key: 'id', label: 'Pkg ID', sortable: true },
        { key: 'cause_title', label: 'Cause', sortable: true },
        { key: 'title', label: 'Package', sortable: true },
        { key: 'amount', label: 'Amount', sortable: true },
    ];

    if (! isMarketerPortal.value) {
        base.push({ key: 'sort_order', label: 'Order', sortable: true });
    }

    base.push({ key: 'is_active', label: 'Active', sortable: true });

    if (! isMarketerPortal.value) {
        base.push({ key: 'actions', label: 'Actions', sortable: false, align: 'right' });
    }

    return base;
});

const packagesRows = computed(() => props.packages.data ?? []);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(packagesRows, { key: 'sort_order', dir: 'asc' });

const formatMoney = (n) => `₹ ${Number(n || 0).toLocaleString('en-IN')}`;

const togglePackage = async (row) => {
    if (! row.can_edit) {
        return;
    }

    await fetch(`/admin/causes/${row.cause_id}/packages/${row.id}/toggle-active`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    });
    router.reload({ only: ['packages'] });
};

const destroyPackage = (row) => {
    if (! row.can_delete) {
        return;
    }

    if (confirm('Delete this package?')) {
        router.delete(`/admin/causes/${row.cause_id}/packages/${row.id}`);
    }
};
</script>

<template>
    <Head title="Packages" />
    <AdminLayout>
        <template #header>Packages</template>
        <PageHeader title="Packages" :subtitle="`${packages.meta?.total ?? 0} package(s)`" />
        <DataTable :columns="columns" :rows="sortedRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-id="{ row }">
                <span class="font-mono text-sm font-semibold text-foreground">{{ row.id }}</span>
            </template>
            <template #cell-amount="{ row }">{{ formatMoney(row.amount) }}</template>
            <template #cell-is_active="{ row }">
                <button
                    v-if="!isMarketerPortal"
                    type="button"
                    class="text-xs font-medium"
                    :class="row.is_active ? 'text-emerald-700' : 'text-muted-foreground'"
                    :disabled="!row.can_edit"
                    @click="togglePackage(row)"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </button>
                <span
                    v-else
                    class="text-xs font-medium"
                    :class="row.is_active ? 'text-emerald-700' : 'text-muted-foreground'"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template v-if="!isMarketerPortal" #cell-actions="{ row }">
                <TableRowActions
                    :edit-href="row.can_edit ? `/admin/causes/${row.cause_id}/packages/${row.id}/edit` : ''"
                    :copy-url="row.can_copy_links ? row.share_url || '' : ''"
                    copy-label="Copy package link"
                    :show-edit="row.can_edit"
                    :show-delete="row.can_delete"
                    @delete="destroyPackage(row)"
                />
            </template>
            <template #footer>
                <Pagination :links="packages.links" :meta="packages.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
