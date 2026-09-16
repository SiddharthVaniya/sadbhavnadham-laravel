<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/20/solid';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { useClientSort } from '@/composables/useClientSort';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

const props = defineProps({
    causes: { type: Object, required: true },
    stats: { type: Object, required: true },
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

const columns = [
    { key: 'sort_order', label: 'Order', sortable: true },
    { key: 'title', label: 'Title', sortable: true },
    { key: 'slug', label: 'Slug', sortable: true },
    { key: 'is_active', label: 'Status', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const causesRows = computed(() => props.causes.data ?? []);
const { sortedRows, sortKey, sortDir, toggleSort } = useClientSort(causesRows, { key: 'sort_order', dir: 'asc' });

const orderedCauses = computed(() => sortedRows.value);

const toggle = async (cause) => {
    if (! props.abilities.can_edit) {
        return;
    }

    await fetch(`/admin/causes/${cause.id}/toggle-active`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    });
    router.reload({ only: ['causes', 'stats'] });
};

const reorder = (cause, direction) => {
    if (! props.abilities.can_edit) {
        return;
    }

    router.patch(`/admin/causes/${cause.id}/reorder`, { direction }, { preserveScroll: true });
};

const destroy = (id) => {
    if (! props.abilities.can_delete) {
        return;
    }

    if (confirm('Delete this cause?')) {
        router.delete(`/admin/causes/${id}`);
    }
};
</script>

<template>
    <Head title="Causes" />
    <AdminLayout>
        <template #header>Causes</template>
        <PageHeader
            title="Causes"
            :subtitle="`${stats.total} total · ${stats.active} active`"
        >
            <template v-if="abilities.can_create" #actions>
                <Link href="/admin/causes/create" class="admin-btn-primary">Add cause</Link>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="orderedCauses"
            :sort-key="sortKey"
            :sort-dir="sortDir"
            empty-message="No causes yet."
            @sort="toggleSort"
        >
            <template #cell-sort_order="{ row }">
                <div class="flex items-center gap-1">
                    <span class="min-w-[2ch] font-mono text-sm text-foreground">{{ row.sort_order }}</span>
                    <div v-if="abilities.can_edit" class="flex flex-col">
                        <button type="button" class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground" title="Move up" @click="reorder(row, 'up')">
                            <ChevronUpIcon class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground" title="Move down" @click="reorder(row, 'down')">
                            <ChevronDownIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </template>
            <template #cell-title="{ row }">
                <span class="font-medium text-foreground">{{ row.title }}</span>
            </template>
            <template #cell-slug="{ row }">
                <span class="text-muted-foreground">{{ row.slug }}</span>
            </template>
            <template #cell-is_active="{ row }">
                <button
                    v-if="abilities.can_edit"
                    type="button"
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                    @click="toggle(row)"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </button>
                <span
                    v-else
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <TableRowActions
                    v-if="row.can_edit || row.can_delete || row.can_copy_links"
                    :edit-href="row.can_edit ? `/admin/causes/${row.id}/edit` : ''"
                    :copy-url="row.can_copy_links ? row.share_url : ''"
                    copy-label="Copy donate link"
                    :show-edit="row.can_edit"
                    :show-delete="row.can_delete"
                    @delete="destroy(row.id)"
                />
            </template>
            <template #footer>
                <Pagination :links="causes.links" :meta="causes.meta" />
            </template>
        </DataTable>
    </AdminLayout>
</template>
