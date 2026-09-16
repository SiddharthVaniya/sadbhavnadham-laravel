<script setup>
import { computed, toRef } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { useClientSort } from '@/composables/useClientSort';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

const props = defineProps({ roles: { type: Object, required: true } });

const columns = [
    { key: 'name', label: 'Role', sortable: true },
    { key: 'permissions_count', label: 'Permissions', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const rolesRows = computed(() => (props.roles.data ?? []).map((r) => ({
    ...r,
    permissions_count: r.permissions?.length ?? 0,
})));

const rolesRef = computed(() => rolesRows.value);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(rolesRef, { key: 'name', dir: 'asc' });

const destroy = (id, name) => {
    if (name === 'super_admin') return;
    if (confirm('Delete this role?')) router.delete(`/admin/roles/${id}`);
};
</script>

<template>
    <Head title="Roles" />
    <AdminLayout>
        <template #header>Roles</template>
        <PageHeader title="Roles">
            <template #actions>
                <Link href="/admin/roles/create" class="admin-btn-primary">Add role</Link>
            </template>
        </PageHeader>
        <DataTable :columns="columns" :rows="sortedRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-actions="{ row }">
                <TableRowActions
                    :edit-href="`/admin/roles/${row.id}/edit`"
                    :show-delete="row.name !== 'super_admin'"
                    @delete="destroy(row.id, row.name)"
                />
            </template>
            <template #footer>
                <Pagination :links="roles.links" :meta="roles.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
