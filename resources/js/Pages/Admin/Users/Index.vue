<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { RotateCcw, TrendingUp } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

const props = defineProps({
    users: { type: Object, required: true },
    stats: { type: Object, required: true },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role: '',
            department_id: '',
            status: 'active',
            sort: 'name',
            dir: 'asc',
        }),
    },
    roleOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    activeFilterCount: { type: Number, default: 0 },
});

const form = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    department_id: props.filters.department_id ?? '',
    status: props.filters.status ?? 'active',
    sort: props.filters.sort ?? 'name',
    dir: props.filters.dir ?? 'asc',
});

const pendingDeleteId = ref(null);

const columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'department_label', label: 'Department', sortable: false },
    { key: 'referral_code', label: 'Referral', sortable: false },
    { key: 'donation_target', label: '₹ Target', sortable: false },
    { key: 'roles', label: 'Roles', sortable: false },
    { key: 'created_at_label', label: 'Joined', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const statusTabs = [
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
    { value: 'all', label: 'All' },
];

const rows = computed(() => props.users.data ?? []);
const sortKey = computed(() => form.sort);
const sortDir = computed(() => form.dir);

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

const visitUsers = (extra = {}) => {
    router.get('/admin/users', listQueryParams(extra), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const submitFilters = () => visitUsers();

const resetFilters = () => {
    router.get('/admin/users', { status: form.status, sort: 'name', dir: 'asc' });
};

const setStatus = (status) => {
    form.status = status;
    visitUsers();
};

const toggleSort = (key) => {
    const serverKey = key === 'created_at_label' ? 'created_at' : key;

    if (form.sort === serverKey) {
        form.dir = form.dir === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = serverKey;
        form.dir = serverKey === 'created_at' ? 'desc' : 'asc';
    }

    visitUsers();
};

const roleVariant = (role) => {
    if (role === 'super_admin') {
        return 'destructive';
    }

    if (role === 'admin') {
        return 'default';
    }

    if (role === 'manager') {
        return 'secondary';
    }

    return 'outline';
};

const confirmDelete = (row) => {
    pendingDeleteId.value = row.id;
};

const cancelDelete = () => {
    pendingDeleteId.value = null;
};

const destroy = (id) => {
    router.delete(`/admin/users/${id}`, {
        preserveScroll: true,
        onFinish: () => {
            pendingDeleteId.value = null;
        },
    });
};

const restore = (id) => {
    router.post(`/admin/users/${id}/restore`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />
    <AdminLayout>
        <template #header>Users</template>
        <PageHeader title="Users" subtitle="Manage staff accounts, departments, roles, and archived users.">
            <template #actions>
                <Button as-child variant="outline">
                    <Link href="/admin/departments">Departments</Link>
                </Button>
                <Button as-child>
                    <Link href="/admin/users/create">Add user</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Active users" :value="stats.active" compact />
            <StatCard label="Archived" :value="stats.archived" compact />
            <StatCard label="Super admins" :value="stats.super_admins" compact />
            <StatCard label="With referral code" :value="stats.with_referral" compact />
        </div>

        <Card class="mb-6 shadow-none">
            <CardContent class="space-y-4 pt-6">
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        size="sm"
                        :variant="form.status === tab.value ? 'default' : 'outline'"
                        @click="setStatus(tab.value)"
                    >
                        {{ tab.label }}
                    </Button>
                </div>

                <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="submitFilters">
                    <input
                        v-model="form.search"
                        type="search"
                        class="admin-input !py-2 xl:col-span-2"
                        placeholder="Search name, email, referral…"
                    />
                    <select v-model="form.role" class="admin-input !py-2">
                        <option value="">All roles</option>
                        <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <select v-model="form.department_id" class="admin-input !py-2">
                        <option value="">All departments</option>
                        <option v-for="option in departmentOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" size="sm">Apply</Button>
                        <Button
                            v-if="activeFilterCount > 0"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="resetFilters"
                        >
                            Reset
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <DataTable :columns="columns" :rows="rows" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ row.name }}</span>
                    <Badge v-if="row.is_archived" variant="outline">Archived</Badge>
                    <Badge v-if="row.is_super_admin" variant="destructive">Protected</Badge>
                </div>
            </template>
            <template #cell-department_label="{ row }">
                <span class="text-sm text-muted-foreground">{{ row.department_label || '—' }}</span>
            </template>
            <template #cell-referral_code="{ row }">
                <span class="font-mono text-sm text-muted-foreground">{{ row.referral_code || '—' }}</span>
            </template>
            <template #cell-donation_target="{ row }">
                <span class="text-sm text-muted-foreground">{{ row.donation_target ? `₹ ${row.donation_target.toLocaleString('en-IN')}` : '—' }}</span>
            </template>
            <template #cell-roles="{ row }">
                <div class="flex flex-wrap gap-1.5">
                    <Badge
                        v-for="role in row.roles ?? []"
                        :key="`${row.id}-${role}`"
                        :variant="roleVariant(role)"
                    >
                        {{ role }}
                    </Badge>
                </div>
            </template>
            <template #cell-created_at_label="{ row }">
                <span class="text-sm text-muted-foreground">{{ row.created_at_label || '—' }}</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="inline-flex items-center gap-1">
                    <Button
                        v-if="row.referrals_href"
                        as-child
                        variant="ghost"
                        size="icon-sm"
                        class="text-blue-600 hover:text-blue-700"
                        title="View partner donations"
                        aria-label="View partner donations"
                    >
                        <Link :href="row.referrals_href">
                            <TrendingUp class="size-4" />
                        </Link>
                    </Button>
                    <TableRowActions
                        v-if="row.can_edit"
                        :edit-href="`/admin/users/${row.id}/edit`"
                        :show-delete="row.can_delete"
                        @delete="confirmDelete(row)"
                    />
                    <Button
                        v-if="row.can_restore"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="text-emerald-600 hover:text-emerald-700"
                        title="Restore user"
                        aria-label="Restore user"
                        @click="restore(row.id)"
                    >
                        <RotateCcw class="size-4" />
                    </Button>
                </div>
            </template>
            <template #footer>
                <Pagination :links="users.links" :meta="users.meta" />
            </template>
        </DataTable>

        <div
            v-if="pendingDeleteId"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-user-title"
        >
            <Card class="w-full max-w-md shadow-lg">
                <CardContent class="space-y-4 pt-6">
                    <div>
                        <h2 id="delete-user-title" class="text-lg font-semibold">Archive this user?</h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            The account will be deactivated and hidden from active lists. You can restore it later from the Archived tab.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="cancelDelete">Cancel</Button>
                        <Button type="button" variant="destructive" @click="destroy(pendingDeleteId)">
                            Archive user
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
