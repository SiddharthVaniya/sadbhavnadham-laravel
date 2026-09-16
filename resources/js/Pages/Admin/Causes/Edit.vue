<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import CauseForm from '@/Components/Admin/CauseForm.vue';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

const props = defineProps({
    cause: { type: Object, required: true },
    aisensyAccounts: { type: Array, default: () => [] },
    subscriptionsEnabled: { type: Boolean, default: false },
});

const destroyPackage = (pkg) => {
    if (confirm('Delete this package?')) {
        router.delete(`/admin/causes/${props.cause.id}/packages/${pkg.id}`);
    }
};

const toggleDefault = (pkg) => {
    router.patch(`/admin/causes/${props.cause.id}/packages/${pkg.id}/toggle-default`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${cause.title}`" />
    <AdminLayout>
        <template #header>Causes</template>
        <PageHeader compact :title="`Edit ${cause.title}`">
            <template #actions>
                <Link href="/admin/causes" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
                <Link :href="`/admin/causes/${cause.id}/packages/create`" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white">Add package</Link>
            </template>
        </PageHeader>
        <CauseForm
            :cause="cause"
            :aisensy-accounts="aisensyAccounts"
            :subscriptions-enabled="subscriptionsEnabled"
            :submit-url="`/admin/causes/${cause.id}`"
            method="put"
        >
            <template #donation>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-foreground">Donation packages</h4>
                        <p class="text-sm text-muted-foreground">Fixed amounts donors can choose on the cause page.</p>
                    </div>
                    <Link :href="`/admin/causes/${cause.id}/packages/create`" class="rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted">
                        Add package
                    </Link>
                </div>
                <ul v-if="cause.packages?.length" class="mt-4 divide-y divide-border rounded-lg border border-border">
                    <li v-for="pkg in cause.packages" :key="pkg.id" class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <span class="min-w-0 font-medium text-foreground">
                            {{ pkg.title }}
                            <span class="ml-2 font-normal text-muted-foreground">₹{{ pkg.amount }}</span>
                            <span
                                v-if="pkg.is_default"
                                class="ml-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                            >
                                Default
                            </span>
                            <span
                                v-if="pkg.allow_recurring"
                                class="ml-2 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800"
                            >
                                Recurring
                            </span>
                        </span>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="text-xs font-medium"
                                :class="pkg.is_default ? 'text-emerald-700' : 'text-muted-foreground hover:text-emerald-700'"
                                @click="toggleDefault(pkg)"
                            >
                                {{ pkg.is_default ? 'Default ✓' : 'Set default' }}
                            </button>
                            <TableRowActions
                                :edit-href="`/admin/causes/${cause.id}/packages/${pkg.id}/edit`"
                                @delete="destroyPackage(pkg)"
                            />
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                    No packages yet. Add at least one package for fixed-amount donations and monthly giving.
                </p>
            </template>
        </CauseForm>
    </AdminLayout>
</template>
