<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    account: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
});

const form = useForm({
    name: props.account?.name ?? '',
    api_key: props.account?.has_api_key ? '********' : '',
    project_api_password: props.account?.has_project_api_password ? '********' : '',
    project_id: props.account?.project_id ?? '',
    country_code: props.account?.country_code ?? '91',
    is_active: props.account?.is_active ?? true,
});

const submit = () => {
    if (props.isEdit) form.put(`/admin/aisensy-accounts/${props.account.id}`);
    else form.post('/admin/aisensy-accounts');
};
</script>

<template>
    <Head :title="isEdit ? 'Edit AiSensy Account' : 'Create AiSensy Account'" />
    <AdminLayout>
        <template #header>AiSensy</template>
        <PageHeader :title="isEdit ? 'Edit account' : 'Create account'" />
        <form class="w-full pb-24" @submit.prevent="submit">
            <FormSection title="Account details" description="API Campaign Key is used for transactional and portal campaign sends. Project API password unlocks approved template sync.">
                <div class="grid gap-5 md:grid-cols-2">
                    <FormInput v-model="form.name" label="Account name" :error="form.errors.name" required />
                    <FormInput v-model="form.country_code" label="Country code" hint="Example: 91 for India" :error="form.errors.country_code" required />
                    <FormInput v-model="form.project_id" label="Project ID" hint="Required for Project API campaigns/templates. From AiSensy project settings / Project API docs." :error="form.errors.project_id" required />
                    <FormInput
                        v-model="form.project_api_password"
                        label="Project API password"
                        hint="From AiSensy Developer Hub → Project API Keys. Leave as ******** to keep existing."
                        :error="form.errors.project_api_password"
                    />
                </div>
                <FormTextarea
                    v-model="form.api_key"
                    label="API Campaign Key"
                    hint="Used by thank-you / certificate / portal campaigns (campaign/t1/api/v2). Leave as ******** to keep existing."
                    :rows="3"
                    :error="form.errors.api_key"
                />
                <FormToggle v-model="form.is_active" label="Active" description="Enable this account for automations" />
            </FormSection>
            <FormActions :processing="form.processing">
                <Link href="/admin/aisensy-accounts" class="admin-btn-secondary">Cancel</Link>
            </FormActions>
        </form>
    </AdminLayout>
</template>
