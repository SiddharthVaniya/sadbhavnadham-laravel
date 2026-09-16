<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    permission: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
});

const form = useForm({ name: props.permission?.name ?? '' });

const submit = () => {
    if (props.isEdit) form.put(`/admin/permissions/${props.permission.id}`);
    else form.post('/admin/permissions');
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Permission' : 'Create Permission'" />
    <AdminLayout>
        <template #header>Permissions</template>
        <PageHeader :title="isEdit ? 'Edit permission' : 'Create permission'" />
        <form class="w-full pb-24" @submit.prevent="submit">
            <FormSection title="Permission">
                <FormInput v-model="form.name" label="Permission name" :error="form.errors.name" required />
            </FormSection>
            <FormActions :processing="form.processing">
                <Link href="/admin/permissions" class="admin-btn-secondary">Cancel</Link>
            </FormActions>
        </form>
    </AdminLayout>
</template>
