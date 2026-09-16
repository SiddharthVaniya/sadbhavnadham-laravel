<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormFile from '@/Components/Admin/FormFile.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    cause: { type: Object, required: true },
    package: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
    subscriptionsEnabled: { type: Boolean, default: false },
});

const assetUrl = (path) => {
    if (! path) {
        return '';
    }

    if (path.startsWith('http')) {
        return path;
    }

    return `/${path.replace(/^\/+/, '')}`;
};

const form = useForm({
    title: props.package?.title ?? '',
    amount: props.package?.amount ?? '',
    sort_order: props.package?.sort_order ?? 0,
    image: null,
    image_existing: props.package?.image ?? '',
    is_active: props.package?.is_active ?? true,
    is_default: props.package?.is_default ?? false,
    allow_recurring: props.package?.allow_recurring ?? false,
});

const fileInputKey = ref(0);
const selectedImagePreview = ref('');

const imagePreview = computed(() => {
    if (selectedImagePreview.value) {
        return selectedImagePreview.value;
    }

    return assetUrl(form.image_existing);
});

const onImageChange = (event) => {
    const file = event.target.files[0] ?? null;

    form.image = file;

    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
        selectedImagePreview.value = '';
    }

    selectedImagePreview.value = file ? URL.createObjectURL(file) : '';
};

onBeforeUnmount(() => {
    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
    }
});

const submit = () => {
    const url = props.isEdit
        ? `/admin/causes/${props.cause.id}/packages/${props.package.id}`
        : `/admin/causes/${props.cause.id}/packages`;

    const options = { forceFormData: true, preserveScroll: true };

    // Multipart PUT bodies are unreliable in PHP; spoof PUT via POST like other admin forms.
    if (props.isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(url, options);
    } else {
        form.post(url, options);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Package' : 'Create Package'" />
    <AdminLayout>
        <template #header>Packages</template>
        <PageHeader :title="isEdit ? 'Edit package' : 'Create package'" :subtitle="cause.title" />
        <form class="w-full" @submit.prevent="submit">
            <FormSection title="Package details" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.title" label="Title" :error="form.errors.title" required />
                    <FormInput v-model="form.amount" label="Amount (₹)" type="number" :error="form.errors.amount" required />
                    <FormInput v-model="form.sort_order" label="Sort order" type="number" :error="form.errors.sort_order" />
                    <FormFile
                        :key="fileInputKey"
                        label="Image"
                        hint="Shown on the public donate page when this package is selected."
                        :preview-url="imagePreview"
                        :error="form.errors.image"
                        @change="onImageChange"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormToggle v-model="form.is_active" label="Active" />
                    <FormToggle
                        v-model="form.is_default"
                        label="Set as default"
                        description="Pre-select this package (amount & title) on the cause page"
                    />
                    <FormToggle
                        v-model="form.allow_recurring"
                        label="Allow recurring donations"
                        description="Enable monthly/weekly giving for this package when the cause allows it"
                        :disabled="!cause.allow_recurring && !cause.allow_weekly_recurring"
                    />
                </div>
                <p v-if="!cause.allow_recurring" class="text-sm text-amber-800">
                    Turn on monthly donations for the cause first, then enable it per package.
                </p>
                <p v-else-if="!subscriptionsEnabled" class="text-sm text-amber-800">
                    Set <code class="rounded bg-muted px-1">RAZORPAY_SUBSCRIPTIONS_ENABLED=true</code> to activate monthly checkout.
                </p>
            </FormSection>
            <FormActions :processing="form.processing">
                <Link :href="`/admin/causes/${cause.id}/edit`" class="admin-btn-secondary">Cancel</Link>
            </FormActions>
        </form>
    </AdminLayout>
</template>
