<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
});

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ?? [],
});

const submit = () => {
    if (props.isEdit) {
        form.put(`/admin/roles/${props.role.id}`);
    } else {
        form.post('/admin/roles');
    }
};

const toggle = (name) => {
    if (form.permissions.includes(name)) {
        form.permissions = form.permissions.filter((permission) => permission !== name);
    } else {
        form.permissions.push(name);
    }
};

const isChecked = (name) => form.permissions.includes(name);
</script>

<template>
    <Head :title="isEdit ? 'Edit Role' : 'Create Role'" />
    <AdminLayout>
        <template #header>Roles</template>
        <PageHeader
            :title="isEdit ? 'Edit role' : 'Create role'"
            subtitle="Pick only the operations each role needs. Example: link-only staff get View + Copy permissions; content staff get Create without Delete."
        />
        <form class="w-full space-y-6 pb-24" @submit.prevent="submit">
            <FormSection title="Role details">
                <FormInput v-model="form.name" label="Role name" :error="form.errors.name" required class="max-w-xl" />
            </FormSection>

            <FormSection
                v-for="group in permissionGroups"
                :key="group.key"
                :title="group.label"
                :subtitle="group.description"
            >
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.name"
                        class="flex items-start gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                        :class="permission.name === 'view all donations' ? 'border-amber-300 bg-amber-50/60' : ''"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5"
                            :checked="isChecked(permission.name)"
                            @change="toggle(permission.name)"
                        >
                        <span>
                            <span class="font-medium text-foreground">{{ permission.label }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{ permission.help }}</span>
                            <span class="mt-1 block font-mono text-[11px] text-muted-foreground">{{ permission.name }}</span>
                        </span>
                    </label>
                </div>
            </FormSection>

            <FormActions :processing="form.processing">
                <Link href="/admin/roles" class="admin-btn-secondary">Cancel</Link>
            </FormActions>
        </form>
    </AdminLayout>
</template>
