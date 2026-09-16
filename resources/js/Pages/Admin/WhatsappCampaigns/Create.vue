<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormFile from '@/Components/Admin/FormFile.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    causes: { type: Array, default: () => [] },
    sourceOptions: { type: Array, default: () => [] },
    paramOptions: { type: Array, default: () => [] },
    headerTypes: { type: Array, default: () => ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION', 'CAROUSEL', 'LIMITED TIME OFFER'] },
    maxAudience: { type: Number, default: 10000 },
});

const page = usePage();
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const mediaPreviewUrl = ref('');

const form = useForm({
    name: '',
    aisensy_account_id: props.accounts[0]?.id ?? '',
    aisensy_wa_template_id: '',
    param_map: [],
    media: null,
    location: {
        latitude: '',
        longitude: '',
        name: '',
        address: '',
    },
    dry_run: false,
    search: '',
    city: '',
    state: '',
    source: '',
    from_date: '',
    to_date: '',
    min_paid: '',
    repeat: false,
    utm_campaign: '',
    utm_content: '',
    cause_id: '',
});

const manualTemplate = useForm({
    aisensy_account_id: props.accounts[0]?.id ?? '',
    name: '',
    live_campaign_name: '',
    header_type: 'TEXT',
    param_count: 0,
    body_preview: '',
});

const selectedAccount = computed(() =>
    props.accounts.find((account) => String(account.id) === String(form.aisensy_account_id)) || null,
);

const accountReady = computed(() =>
    Boolean(selectedAccount.value?.has_project_api_password && selectedAccount.value?.has_project_id),
);

watch(
    () => form.aisensy_account_id,
    (id) => {
        manualTemplate.aisensy_account_id = id;
        form.aisensy_wa_template_id = '';
        form.media = null;
        mediaPreviewUrl.value = '';
    },
);

const accountTemplates = computed(() =>
    props.templates.filter((t) => String(t.aisensy_account_id) === String(form.aisensy_account_id)),
);

const selectedTemplate = computed(() =>
    accountTemplates.value.find((t) => String(t.id) === String(form.aisensy_wa_template_id)) || null,
);

const needsMedia = computed(() => Boolean(selectedTemplate.value?.requires_media));
const needsLocation = computed(() => Boolean(selectedTemplate.value?.requires_location));

const mediaAccept = computed(() => {
    const type = selectedTemplate.value?.header_type;
    if (type === 'VIDEO') {
        return 'video/mp4,video/3gpp';
    }
    if (type === 'DOCUMENT') {
        return 'application/pdf';
    }
    return 'image/jpeg,image/png,image/webp';
});

const mediaHint = computed(() => {
    const type = selectedTemplate.value?.header_type || 'IMAGE';
    if (type === 'VIDEO') {
        return 'Upload MP4/3GPP video. File must be publicly reachable by AiSensy (APP_URL).';
    }
    if (type === 'DOCUMENT') {
        return 'Upload PDF document. File must be publicly reachable by AiSensy (APP_URL).';
    }
    if (type === 'LIMITED TIME OFFER') {
        return 'Limited Time Offer templates need an image/video header file. Must be publicly reachable by AiSensy (APP_URL).';
    }
    return 'Upload JPEG/PNG/WebP image. File must be publicly reachable by AiSensy (APP_URL).';
});

const mediaLabel = computed(() => {
    const type = selectedTemplate.value?.header_type || 'IMAGE';
    if (type === 'LIMITED TIME OFFER') {
        return 'Offer media file';
    }
    return `${type} file`;
});

watch(
    () => form.aisensy_wa_template_id,
    () => {
        const template = selectedTemplate.value;
        form.media = null;
        mediaPreviewUrl.value = '';
        form.location = { latitude: '', longitude: '', name: '', address: '' };
        if (! template) {
            return;
        }
        const count = Number(template.param_count || 0);
        form.param_map = Array.from({ length: count }, (_, index) => form.param_map[index] || 'donor.name');
    },
);

const onMediaChange = (event) => {
    const file = event.target.files?.[0] || null;
    form.media = file;
    if (mediaPreviewUrl.value) {
        URL.revokeObjectURL(mediaPreviewUrl.value);
    }
    mediaPreviewUrl.value = file && ['IMAGE', 'LIMITED TIME OFFER'].includes(selectedTemplate.value?.header_type)
        ? URL.createObjectURL(file)
        : '';
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const runPreview = async () => {
    previewLoading.value = true;
    previewError.value = '';
    try {
        const response = await fetch('/admin/whatsapp-campaigns/preview-audience', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ ...form.data(), media: undefined }),
        });
        const data = await response.json();
        if (! response.ok) {
            previewError.value = data.message || 'Preview failed';
            return;
        }
        preview.value = data;
    } catch (e) {
        previewError.value = 'Preview failed';
    } finally {
        previewLoading.value = false;
    }
};

const syncTemplates = () => {
    router.post('/admin/whatsapp-campaigns/sync-templates', {
        aisensy_account_id: form.aisensy_account_id,
    }, { preserveScroll: true });
};

const saveManualTemplate = () => {
    manualTemplate.aisensy_account_id = form.aisensy_account_id;
    if (! manualTemplate.live_campaign_name) {
        manualTemplate.live_campaign_name = manualTemplate.name;
    }
    manualTemplate.post('/admin/whatsapp-campaigns/templates', {
        preserveScroll: true,
        onSuccess: () => {
            manualTemplate.reset('name', 'live_campaign_name', 'header_type', 'param_count', 'body_preview');
            manualTemplate.header_type = 'TEXT';
        },
    });
};

const submit = (dryRun = false) => {
    if (! accountReady.value) {
        alert('Selected AiSensy account needs Project API password + Project ID first.');
        return;
    }
    if (! form.aisensy_wa_template_id) {
        alert('Please select an approved template.');
        return;
    }
    if (needsMedia.value && ! form.media) {
        alert(`Please upload a ${selectedTemplate.value?.header_type || 'media'} file for this template.`);
        return;
    }
    if (needsLocation.value && (! form.location.latitude || ! form.location.longitude)) {
        alert('LOCATION templates need latitude and longitude.');
        return;
    }

    const template = selectedTemplate.value;

    if (! dryRun) {
        const count = preview.value?.candidate_count;
        const templateLabel = template?.name || 'selected template';
        const audienceLabel = typeof count === 'number' ? `${count} candidate(s)` : 'the filtered audience';
        if (! confirm(`Create AiSensy campaign from this portal and send to ${audienceLabel} using "${templateLabel}"?`)) {
            return;
        }
        if (typeof count === 'number' && count > 2000 && ! confirm(`Audience is large (${count}). Confirm launch?`)) {
            return;
        }
    }
    form.dry_run = dryRun;
    form.post('/admin/whatsapp-campaigns', { forceFormData: true });
};
</script>

<template>
    <Head title="New WhatsApp Campaign" />
    <AdminLayout>
        <template #header>WA Campaigns</template>
        <PageHeader title="New WhatsApp campaign" subtitle="Audience filters here → portal creates the AiSensy campaign → sends approved template">
            <template #actions>
                <Link href="/admin/whatsapp-campaigns" class="admin-btn-secondary">Back</Link>
            </template>
        </PageHeader>

        <p v-if="page.props.flash?.status" class="mb-4 text-sm text-emerald-700">{{ page.props.flash.status }}</p>

        <form class="space-y-6 pb-24" @submit.prevent="submit(false)">
            <FormSection title="1. Audience filters" description="Same donor filters as Donors list. Opted-out and invalid phones are excluded on launch.">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <FormInput v-model="form.search" label="Search" />
                    <FormInput v-model="form.city" label="City" />
                    <FormInput v-model="form.state" label="State" />
                    <div>
                        <label class="admin-label">Source</label>
                        <select v-model="form.source" class="admin-input">
                            <option value="">All</option>
                            <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <FormDatePicker v-model="form.from_date" label="Paid from" placeholder="From date" />
                    <FormDatePicker v-model="form.to_date" label="Paid to" placeholder="To date" />
                    <FormInput v-model="form.min_paid" type="number" label="Min paid total" />
                    <FormInput v-model="form.utm_campaign" label="utm_campaign" />
                    <FormInput v-model="form.utm_content" label="utm_content (employee)" />
                    <div>
                        <label class="admin-label">Cause donated to</label>
                        <select v-model="form.cause_id" class="admin-input">
                            <option value="">Any</option>
                            <option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.title }}</option>
                        </select>
                    </div>
                    <FormToggle v-model="form.repeat" label="Repeat donors only" />
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="admin-btn-secondary" :disabled="previewLoading" @click="runPreview">
                        {{ previewLoading ? 'Previewing…' : 'Preview audience' }}
                    </button>
                    <p v-if="preview" class="text-sm text-muted-foreground">
                        Candidates ≈ {{ preview.candidate_count }} (sample eligible {{ preview.sample_eligible_count }}). Max {{ maxAudience }}.
                        <span v-if="preview.candidate_count > 500" class="text-amber-700"> Large audience — double-check before launch.</span>
                    </p>
                </div>
                <p v-if="previewError" class="text-sm text-rose-600">{{ previewError }}</p>
                <ul v-if="preview?.sample?.length" class="grid gap-1 rounded-lg border border-border p-3 text-sm text-muted-foreground sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="row in preview.sample" :key="row.id">{{ row.name }} · {{ row.phone }} · {{ row.city || '—' }}</li>
                </ul>
            </FormSection>

            <FormSection title="2. Template & launch" description="Sync pulls templates from AiSensy campaigns (Project API template-list is not available with Project API password). Portal then creates a unique campaign and queues sends.">
                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <label class="admin-label">AiSensy account</label>
                            <select v-model="form.aisensy_account_id" class="admin-input" required>
                                <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                            </select>
                            <p v-if="!accountReady" class="mt-1 text-sm text-amber-700">
                                This account needs <Link href="/admin/aisensy-accounts" class="underline">Project API password + Project ID</Link> before sync/launch.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="admin-btn-secondary" :disabled="!accountReady" @click="syncTemplates">
                                Sync approved templates
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Sync finds templates already used on AiSensy API/broadcast campaigns. Templates never used in a campaign yet: add their Meta template name on the right.
                        </p>
                        <div>
                            <label class="admin-label">Approved template</label>
                            <select v-model="form.aisensy_wa_template_id" class="admin-input" required>
                                <option value="">Select template…</option>
                                <option v-for="template in accountTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                    <template v-if="template.header_type && template.header_type !== 'TEXT'"> · {{ template.header_type }}</template>
                                    <template v-if="template.is_manual"> (manual)</template>
                                </option>
                            </select>
                            <p v-if="selectedTemplate?.body_preview" class="mt-1 text-xs text-muted-foreground">{{ selectedTemplate.body_preview }}</p>
                            <p v-if="form.errors.aisensy_wa_template_id" class="mt-1 text-sm text-rose-600">{{ form.errors.aisensy_wa_template_id }}</p>
                        </div>
                        <FormFile
                            v-if="needsMedia"
                            :label="mediaLabel"
                            :accept="mediaAccept"
                            :hint="mediaHint"
                            :preview-url="mediaPreviewUrl"
                            :error="form.errors.media"
                            @change="onMediaChange"
                        />
                        <div v-if="needsLocation" class="grid gap-3 sm:grid-cols-2">
                            <FormInput v-model="form.location.latitude" label="Latitude" required :error="form.errors['location.latitude']" />
                            <FormInput v-model="form.location.longitude" label="Longitude" required :error="form.errors['location.longitude']" />
                            <FormInput v-model="form.location.name" label="Location name" />
                            <FormInput v-model="form.location.address" label="Address" />
                        </div>
                        <p v-if="selectedTemplate?.header_type === 'CAROUSEL'" class="text-xs text-muted-foreground">
                            Carousel cards are defined in AiSensy when the template is approved. Portal sends template params only.
                        </p>
                        <FormInput
                            v-model="form.name"
                            label="Portal campaign name"
                            hint="Used in admin history. AiSensy campaign name is auto-generated unique from this."
                            required
                            :error="form.errors.name"
                        />
                        <div v-if="form.param_map.length" class="space-y-2">
                            <p class="text-sm font-medium text-foreground">Template params</p>
                            <div v-for="(_, index) in form.param_map" :key="index" class="grid gap-2 sm:grid-cols-[120px_1fr]">
                                <label class="admin-label self-center">Param {{ index + 1 }}</label>
                                <select v-model="form.param_map[index]" class="admin-input">
                                    <option v-for="opt in paramOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="h-fit space-y-3 rounded-lg border border-dashed border-border p-4">
                        <p class="text-sm font-medium text-foreground">Manual template fallback</p>
                        <p class="text-xs text-muted-foreground">
                            Only if Sync fails. Add an approved template name from AiSensy so you can still launch.
                        </p>
                        <FormInput v-model="manualTemplate.name" label="Approved template name" />
                        <div>
                            <label class="admin-label">Template type</label>
                            <select v-model="manualTemplate.header_type" class="admin-input">
                                <option v-for="type in headerTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </div>
                        <FormInput v-model="manualTemplate.param_count" type="number" label="Param count" />
                        <button type="button" class="admin-btn-secondary" :disabled="manualTemplate.processing" @click="saveManualTemplate">
                            {{ manualTemplate.processing ? 'Saving…' : 'Save template' }}
                        </button>
                        <p v-if="manualTemplate.errors.name" class="text-sm text-rose-600">{{ manualTemplate.errors.name }}</p>
                    </div>
                </div>
            </FormSection>

            <FormActions :processing="form.processing">
                <button type="button" class="admin-btn-secondary" :disabled="form.processing" @click="submit(true)">Dry run</button>
                <button type="submit" class="admin-btn-primary" :disabled="form.processing || !accountReady">
                    {{ form.processing ? 'Queuing…' : 'Launch campaign' }}
                </button>
            </FormActions>
        </form>
    </AdminLayout>
</template>
