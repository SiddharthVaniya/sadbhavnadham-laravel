<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { useClientSort } from '@/composables/useClientSort';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

const props = defineProps({ permissions: { type: Object, required: true } });

const columns = [
    { key: 'name', label: 'Permission', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const permissionsRows = computed(() => props.permissions.data ?? []);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(permissionsRows, { key: 'name', dir: 'asc' });

const destroy = (id) => {
    if (confirm('Delete this permission?')) router.delete(`/admin/permissions/${id}`);
};
</script>

<template>
    <Head title="Permissions" />
    <AdminLayout>
        <template #header>Permissions</template>
        <PageHeader title="Permissions" :subtitle="`${permissions.meta?.total ?? 0} permission(s)`">
            <template #actions>
                <Link href="/admin/permissions/create" class="admin-btn-primary">Add permission</Link>
            </template>
        </PageHeader>
        <DataTable :columns="columns" :rows="sortedRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-actions="{ row }">
                <TableRowActions :edit-href="`/admin/permissions/${row.id}/edit`" @delete="destroy(row.id)" />
            </template>
            <template #footer>
                <Pagination :links="permissions.links" :meta="permissions.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
