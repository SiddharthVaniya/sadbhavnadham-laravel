<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';
import { Badge } from '@/Components/ui/badge';

const props = defineProps({ departments: { type: Object, required: true } });

const columns = [
    { key: 'name', label: 'Department', sortable: false },
    { key: 'users_count', label: 'Users', sortable: false },
    { key: 'sort_order', label: 'Order', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const rows = computed(() => (props.departments.data ?? []).map((department) => ({
    ...department,
    status: department.is_active ? 'Active' : 'Inactive',
})));

const destroy = (id) => {
    if (confirm('Delete this department? It must have no assigned users.')) {
        router.delete(`/admin/departments/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Departments" />
    <AdminLayout>
        <template #header>Departments</template>
        <PageHeader title="Departments" subtitle="Organize staff by department for reporting and user management.">
            <template #actions>
                <Link href="/admin/departments/create" class="admin-btn-primary">Add department</Link>
            </template>
        </PageHeader>

        <DataTable :columns="columns" :rows="rows">
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p v-if="row.description" class="text-xs text-muted-foreground">{{ row.description }}</p>
                </div>
            </template>
            <template #cell-status="{ row }">
                <Badge :variant="row.is_active ? 'secondary' : 'outline'">
                    {{ row.status }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <TableRowActions
                    :edit-href="`/admin/departments/${row.id}/edit`"
                    @delete="destroy(row.id)"
                />
            </template>
            <template #footer>
                <Pagination :links="departments.links" :meta="departments.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
