<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import CauseForm from '@/Components/Admin/CauseForm.vue';

defineProps({
    aisensyAccounts: { type: Array, default: () => [] },
    subscriptionsEnabled: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="Create Cause" />
    <AdminLayout>
        <template #header>Causes</template>
        <PageHeader compact title="Create cause">
            <template #actions>
                <Link href="/admin/causes" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
            </template>
        </PageHeader>
        <CauseForm
            submit-url="/admin/causes"
            method="post"
            :aisensy-accounts="aisensyAccounts"
            :subscriptions-enabled="subscriptionsEnabled"
        />
    </AdminLayout>
</template>
