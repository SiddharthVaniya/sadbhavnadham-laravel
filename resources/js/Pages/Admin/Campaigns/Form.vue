<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import FormFile from '@/Components/Admin/FormFile.vue';

const props = defineProps({
    campaign: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
    causes: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.campaign?.name ?? '',
    slug: props.campaign?.slug ?? '',
    cause_id: props.campaign?.cause_id ?? '',
    cause_package_id: props.campaign?.cause_package_id ?? '',
    amount: props.campaign?.amount ?? '',
    goal_amount: props.campaign?.goal_amount ?? '',
    title: props.campaign?.title ?? '',
    headline: props.campaign?.headline ?? '',
    subheadline: props.campaign?.subheadline ?? '',
    image: null,
    image_existing: props.campaign?.image ?? '',
    remove_image: false,
    recurring_only: props.campaign?.recurring_only ?? true,
    frequency: props.campaign?.frequency ?? 'monthly',
    is_active: props.campaign?.is_active ?? true,
    starts_at: props.campaign?.starts_at ?? '',
    ends_at: props.campaign?.ends_at ?? '',
});

const frequencyOptions = [
    {
        value: 'monthly',
        title: 'Monthly',
        description: 'Best for steady support. Donor is charged once every month.',
        example: '₹500 / month',
    },
    {
        value: 'weekly',
        title: 'Weekly',
        description: 'Steady weekly support. Donor is charged once every week.',
        example: '₹100 / week',
    },
];

const assetUrl = (path) => {
    if (! path) {
        return '';
    }

    if (path.startsWith('http')) {
        return path;
    }

    return `/${path.replace(/^\/+/, '')}`;
};

const selectedCause = computed(() => props.causes.find((cause) => cause.id === Number(form.cause_id)) ?? null);

const fileInputKey = ref(0);
const selectedImagePreview = ref('');

const hasCampaignImage = computed(() => {
    if (form.remove_image) {
        return false;
    }

    return Boolean(form.image) || Boolean(form.image_existing);
});

const imagePreview = computed(() => {
    if (selectedImagePreview.value) {
        return selectedImagePreview.value;
    }

    if (! form.remove_image && form.image_existing) {
        return assetUrl(form.image_existing);
    }

    return assetUrl(selectedCause.value?.hero_image ?? '');
});

const clearCampaignImage = () => {
    form.remove_image = true;
    form.image = null;

    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
        selectedImagePreview.value = '';
    }

    fileInputKey.value++;
};

const onCampaignImageChange = (event) => {
    const file = event.target.files[0] ?? null;

    form.image = file;
    form.remove_image = false;

    if (selectedImagePreview.value) {
        URL.revokeObjectURL(selectedImagePreview.value);
        selectedImagePreview.value = '';
    }

    selectedImagePreview.value = file ? URL.createObjectURL(file) : '';
};

const packageOptions = computed(() => {
    if (!selectedCause.value) {
        return [];
    }

    return selectedCause.value.packages.filter((pkg) => pkg.allow_recurring);
});

const usingPackage = computed(() => form.cause_package_id !== '' && form.cause_package_id !== null);

const selectedFrequency = computed(() => frequencyOptions.find((option) => option.value === form.frequency) ?? frequencyOptions[0]);

watch(() => form.cause_id, () => {
    form.cause_package_id = '';
});

watch(() => form.cause_package_id, (packageId) => {
    if (!packageId) {
        return;
    }

    const pkg = packageOptions.value.find((item) => item.id === Number(packageId));

    if (pkg) {
        form.amount = '';
    }
});

watch(() => form.recurring_only, (enabled) => {
    if (! enabled) {
        form.frequency = 'monthly';
    }
});

const submit = () => {
    const url = props.isEdit ? `/admin/campaigns/${props.campaign.id}` : '/admin/campaigns';
    const options = { forceFormData: true };

    if (props.isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(url, options);
    } else {
        form.post(url, options);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Campaign' : 'Create Campaign'" />
    <AdminLayout>
        <template #header>Campaigns</template>
        <PageHeader :title="isEdit ? 'Edit campaign' : 'Create campaign'">
            <template #actions>
                <Link href="/admin/campaigns" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
            </template>
        </PageHeader>

        <form class="w-full" @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-2">
                <FormSection title="Campaign details" dense>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput v-model="form.name" label="Internal name" :error="form.errors.name" required />
                        <FormInput v-model="form.slug" label="URL slug" :error="form.errors.slug" required />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Public URL:
                        <code class="rounded bg-muted px-1">/give/{{ form.slug || 'your-slug' }}</code>
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground">Cause</label>
                            <select
                                v-model="form.cause_id"
                                class="w-full rounded-lg border border-border px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>Select a cause</option>
                                <option
                                    v-for="cause in causes"
                                    :key="cause.id"
                                    :value="cause.id"
                                >
                                    {{ cause.title }}{{ cause.is_active ? '' : ' (inactive)' }}
                                </option>
                            </select>
                            <p v-if="form.errors.cause_id" class="mt-1 text-sm text-red-600">{{ form.errors.cause_id }}</p>
                            <p v-else-if="selectedCause && !selectedCause.allow_recurring && !selectedCause.allow_weekly_recurring" class="mt-1 text-sm text-amber-800">
                                Enable monthly or weekly donations on this cause before using it in a campaign.
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground">Package (optional)</label>
                            <select
                                v-model="form.cause_package_id"
                                class="w-full rounded-lg border border-border px-3 py-2 text-sm"
                                :disabled="!selectedCause"
                            >
                                <option value="">Use custom fixed amount</option>
                                <option
                                    v-for="pkg in packageOptions"
                                    :key="pkg.id"
                                    :value="pkg.id"
                                >
                                    {{ pkg.title }} — ₹{{ Number(pkg.amount).toLocaleString('en-IN') }}
                                </option>
                            </select>
                            <p v-if="form.errors.cause_package_id" class="mt-1 text-sm text-red-600">{{ form.errors.cause_package_id }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput
                            v-model="form.amount"
                            label="Fixed amount (₹)"
                            type="number"
                            :error="form.errors.amount"
                            :disabled="usingPackage"
                            :required="!usingPackage"
                        />
                        <FormInput
                            v-model="form.goal_amount"
                            label="Fundraising goal (₹)"
                            type="number"
                            :error="form.errors.goal_amount"
                            hint="Optional. Shows progress on the public /give page."
                        />
                        <FormInput
                            v-model="form.title"
                            label="Donation title override"
                            :error="form.errors.title"
                            placeholder="Uses package title when empty"
                        />
                    </div>
                </FormSection>

                <div class="space-y-6">
                    <FormSection title="Landing page copy" dense>
                        <div class="grid gap-4">
                            <FormInput v-model="form.headline" label="Headline" :error="form.errors.headline" />
                            <FormTextarea v-model="form.subheadline" label="Subheadline" :error="form.errors.subheadline" rows="4" />
                            <div class="max-w-md">
                                <FormFile
                                    :key="fileInputKey"
                                    label="Campaign image (optional)"
                                    hint="Leave blank to use the selected cause image on the public page."
                                    :preview-url="imagePreview"
                                    @change="onCampaignImageChange"
                                />
                                <p v-if="!hasCampaignImage && imagePreview" class="mt-2 text-xs text-muted-foreground">
                                    Preview shows the cause image because no campaign image is set.
                                </p>
                                <button
                                    v-if="hasCampaignImage"
                                    type="button"
                                    class="mt-2 text-sm font-medium text-rose-600 hover:text-rose-700"
                                    @click="clearCampaignImage"
                                >
                                    Remove campaign image
                                </button>
                                <p v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</p>
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Billing & status" dense>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FormToggle v-model="form.recurring_only" label="Recurring only" description="Turn off for one-time checkout on this campaign." />
                            <FormToggle v-model="form.is_active" label="Active" />
                        </div>

                        <div v-if="form.recurring_only" class="mt-4 space-y-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">Billing frequency</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Donors see one clear offer on the public page. Choose monthly or weekly for this campaign.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Billing frequency">
                                <button
                                    v-for="option in frequencyOptions"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-xl border p-4 text-left transition"
                                    :class="form.frequency === option.value
                                        ? 'border-sky-500 bg-sky-50 shadow-sm ring-1 ring-sky-500'
                                        : 'border-border bg-background hover:border-sky-300'"
                                    :aria-pressed="form.frequency === option.value"
                                    @click="form.frequency = option.value"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-foreground">{{ option.title }}</span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                                            :class="form.frequency === option.value ? 'bg-sky-600 text-white' : 'bg-muted text-muted-foreground'"
                                        >
                                            {{ form.frequency === option.value ? 'Selected' : 'Select' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-muted-foreground">{{ option.description }}</p>
                                    <p class="mt-2 text-xs font-medium text-foreground/80">Example: {{ option.example }}</p>
                                </button>
                            </div>

                            <p v-if="form.errors.frequency" class="text-sm text-red-600">{{ form.errors.frequency }}</p>
                            <p class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                                Public page will show
                                <strong class="text-foreground">{{ selectedFrequency.title.toLowerCase() }} gift</strong>
                                billed
                                <strong class="text-foreground">{{ selectedFrequency.value === 'weekly' ? 'per week' : 'per month' }}</strong>.
                            </p>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <FormInput v-model="form.starts_at" label="Starts at" type="datetime-local" :error="form.errors.starts_at" />
                            <FormInput v-model="form.ends_at" label="Ends at" type="datetime-local" :error="form.errors.ends_at" />
                        </div>
                    </FormSection>
                </div>
            </div>

            <div class="mt-6">
                <FormActions :processing="form.processing">
                    <Link href="/admin/campaigns" class="admin-btn-secondary">Cancel</Link>
                </FormActions>
            </div>
        </form>
    </AdminLayout>
</template>
