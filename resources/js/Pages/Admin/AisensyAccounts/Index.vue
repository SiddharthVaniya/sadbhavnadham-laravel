<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';
import { useClientSort } from '@/composables/useClientSort';

const props = defineProps({ accounts: { type: Object, required: true } });

const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'country_code', label: 'Country', sortable: true },
    { key: 'is_active', label: 'Status', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const accountsRows = computed(() => props.accounts.data ?? []);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(accountsRows, { key: 'name', dir: 'asc' });

const destroy = (id) => {
    if (confirm('Delete this AiSensy account?')) router.delete(`/admin/aisensy-accounts/${id}`);
};

const toggle = async (account) => {
    await fetch(`/admin/aisensy-accounts/${account.id}/toggle-active`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' },
    });
    router.reload({ only: ['accounts'] });
};
</script>

<template>
    <Head title="AiSensy Accounts" />
    <AdminLayout>
        <template #header>AiSensy</template>
        <PageHeader title="AiSensy accounts" :subtitle="`${accounts.meta?.total ?? 0} account(s)`">
            <template #actions>
                <Link href="/admin/aisensy-accounts/create" class="admin-btn-primary">Add account</Link>
            </template>
        </PageHeader>
        <DataTable :columns="columns" :rows="sortedRows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-is_active="{ row }">
                <button type="button" class="text-xs font-medium" :class="row.is_active ? 'text-emerald-700' : 'text-muted-foreground'" @click="toggle(row)">{{ row.is_active ? 'Active' : 'Inactive' }}</button>
            </template>
            <template #cell-actions="{ row }">
                <TableRowActions
                    :edit-href="`/admin/aisensy-accounts/${row.id}/edit`"
                    @delete="destroy(row.id)"
                />
            </template>
            <template #footer>
                <Pagination :links="accounts.links" :meta="accounts.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
