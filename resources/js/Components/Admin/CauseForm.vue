<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormFile from '@/Components/Admin/FormFile.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    cause: { type: Object, default: null },
    aisensyAccounts: { type: Array, default: () => [] },
    subscriptionsEnabled: { type: Boolean, default: false },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    cancelHref: { type: String, default: '/admin/causes' },
});

const tabs = [
    { id: 'content', label: 'Content' },
    { id: 'donation', label: 'Donation' },
    { id: 'media', label: 'Media' },
    { id: 'whatsapp', label: 'WhatsApp' },
];

const activeTab = ref('content');

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
    title: props.cause?.title ?? '',
    slug: props.cause?.slug ?? '',
    excerpt: props.cause?.excerpt ?? '',
    description: props.cause?.description ?? '',
    cta_text: props.cause?.cta_text ?? '',
    contact_heading: props.cause?.contact_heading ?? '',
    contact_address: props.cause?.contact_address ?? '',
    contact_phone: props.cause?.contact_phone ?? '',
    contact_email: props.cause?.contact_email ?? '',
    hero_image: null,
    hero_image_existing: props.cause?.hero_image ?? '',
    icon_uri_file: null,
    icon_uri_existing: props.cause?.icon_uri ?? '',
    icon_uri_active_file: null,
    icon_uri_active_existing: props.cause?.icon_uri_active ?? '',
    images: [],
    images_existing: props.cause?.images ?? [],
    aisensy_account_id: props.cause?.aisensy_account_id ?? '',
    aisensy_payment_link_campaign: props.cause?.aisensy_payment_link_campaign ?? '',
    aisensy_thank_you_campaign: props.cause?.aisensy_thank_you_campaign ?? '',
    aisensy_certificate_campaign: props.cause?.aisensy_certificate_campaign ?? '',
    aisensy_receipt_campaign: props.cause?.aisensy_receipt_campaign ?? '',
    certificate_template: null,
    certificate_template_existing: props.cause?.certificate_template ?? '',
    remove_certificate_template: false,
    certificate_template_english: null,
    certificate_template_english_existing: props.cause?.certificate_template_english ?? '',
    remove_certificate_template_english: false,
    aisensy_send_thank_you: props.cause?.aisensy_send_thank_you ?? true,
    aisensy_send_certificate: props.cause?.aisensy_send_certificate ?? true,
    aisensy_send_receipt: props.cause?.aisensy_send_receipt ?? true,
    aisensy_thank_you_image: null,
    aisensy_thank_you_image_existing: props.cause?.aisensy_thank_you_image ?? '',
    remove_aisensy_thank_you_image: false,
    aisensy_thank_you_message_mode: props.cause?.aisensy_thank_you_message_mode ?? 'template',
    aisensy_thank_you_message_template: props.cause?.aisensy_thank_you_message_template ?? '',
    aisensy_thank_you_include_name: props.cause?.aisensy_thank_you_include_name ?? true,
    aisensy_thank_you_include_amount: props.cause?.aisensy_thank_you_include_amount ?? true,
    aisensy_thank_you_include_cause: props.cause?.aisensy_thank_you_include_cause ?? true,
    aisensy_thank_you_include_receipt: props.cause?.aisensy_thank_you_include_receipt ?? false,
    sort_order: props.cause?.sort_order ?? 0,
    default_amount: props.cause?.default_amount ?? '',
    default_title: props.cause?.default_title ?? '',
    details_text: props.cause?.details_text ?? '',
    allow_custom_amount: props.cause?.allow_custom_amount ?? true,
    allow_recurring: props.cause?.allow_recurring ?? false,
    allow_weekly_recurring: props.cause?.allow_weekly_recurring ?? false,
    pan_required: props.cause?.pan_required ?? true,
    is_active: props.cause?.is_active ?? true,
});

const isEdit = computed(() => props.method === 'put');

const thankYouImagePreview = computed(() => {
    if (form.remove_aisensy_thank_you_image) {
        return '';
    }

    return assetUrl(form.aisensy_thank_you_image_existing);
});

const certificateTemplatePreview = computed(() => {
    if (form.remove_certificate_template) {
        return '';
    }

    return assetUrl(form.certificate_template_existing);
});

const certificateTemplateEnglishPreview = computed(() => {
    if (form.remove_certificate_template_english) {
        return '';
    }

    return assetUrl(form.certificate_template_english_existing);
});

const clearThankYouImage = () => {
    form.remove_aisensy_thank_you_image = true;
    form.aisensy_thank_you_image_existing = '';
    form.aisensy_thank_you_image = null;
};

const onThankYouImageChange = (event) => {
    form.aisensy_thank_you_image = event.target.files[0] ?? null;
    form.remove_aisensy_thank_you_image = false;
};

const clearCertificateTemplate = () => {
    form.remove_certificate_template = true;
    form.certificate_template_existing = '';
    form.certificate_template = null;
};

const onCertificateTemplateChange = (event) => {
    form.certificate_template = event.target.files[0] ?? null;
    form.remove_certificate_template = false;
};

const clearCertificateTemplateEnglish = () => {
    form.remove_certificate_template_english = true;
    form.certificate_template_english_existing = '';
    form.certificate_template_english = null;
};

const onCertificateTemplateEnglishChange = (event) => {
    form.certificate_template_english = event.target.files[0] ?? null;
    form.remove_certificate_template_english = false;
};

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(props.submitUrl, { forceFormData: true });
    } else {
        form.post(props.submitUrl, { forceFormData: true });
    }
};
</script>

<template>
    <form class="w-full pb-20" @submit.prevent="submit">
        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <div class="flex gap-1 overflow-x-auto border-b border-border bg-muted/50 px-3 pt-3">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="shrink-0 rounded-t-lg px-4 py-2.5 text-sm font-medium transition"
                    :class="activeTab === tab.id
                        ? 'border border-b-card border-border bg-card text-foreground -mb-px'
                        : 'text-muted-foreground hover:bg-card/70 hover:text-foreground'"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="p-5 sm:p-6">
                <div v-show="activeTab === 'content'" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Public cause page</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Title, copy, and default values shown on the donate page.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <FormInput v-model="form.title" label="Title" :error="form.errors.title" required />
                        <FormInput v-model="form.slug" label="Slug" :error="form.errors.slug" required />
                        <FormInput v-model="form.excerpt" label="Excerpt" :error="form.errors.excerpt" />
                        <FormInput v-model="form.cta_text" label="CTA text" :error="form.errors.cta_text" hint="Button label" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormInput v-model="form.sort_order" label="Sort order" type="number" :error="form.errors.sort_order" />
                        <FormInput v-model="form.default_amount" label="Default amount (₹)" type="number" :error="form.errors.default_amount" />
                        <FormInput v-model="form.default_title" label="Default title" :error="form.errors.default_title" />
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <FormTextarea v-model="form.description" label="Description" :error="form.errors.description" :rows="4" />
                        <FormTextarea v-model="form.details_text" label="Details bullets" hint="One line per bullet." :rows="4" />
                    </div>
                    <div class="border-t border-border pt-5">
                        <h4 class="text-sm font-semibold text-foreground">Contact card (donate page)</h4>
                        <p class="mt-1 text-sm text-muted-foreground">Shown below the cause image. Leave blank to hide the card.</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <FormInput v-model="form.contact_heading" label="Card heading" :error="form.errors.contact_heading" hint="Default: Contact &amp; Address" />
                            <FormInput v-model="form.contact_phone" label="Phone" :error="form.errors.contact_phone" />
                            <FormInput v-model="form.contact_email" label="Email" type="email" :error="form.errors.contact_email" />
                            <div class="lg:col-span-2">
                                <FormTextarea v-model="form.contact_address" label="Address" :error="form.errors.contact_address" :rows="3" />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'donation'" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Donation form behaviour</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Controls checkout options on the public donate form.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                        <FormToggle v-model="form.is_active" label="Published" description="Visible on site" compact />
                        <FormToggle v-model="form.allow_custom_amount" label="Custom amount" description="Any amount" compact />
                        <FormToggle v-model="form.allow_recurring" label="Monthly donations" description="Every month on donate page" compact />
                        <FormToggle v-model="form.allow_weekly_recurring" label="Weekly donations" description="Every week on donate page" compact />
                        <FormToggle v-model="form.pan_required" label="Collect PAN when required" description="Shown when donation or FY total reaches ₹1,00,000" compact />
                    </div>
                    <p v-if="!subscriptionsEnabled" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Recurring checkout needs
                        <code class="rounded bg-card/80 px-1.5 py-0.5 text-xs">RAZORPAY_SUBSCRIPTIONS_ENABLED=true</code>
                        in `.env`. Enable monthly and/or weekly here, then turn recurring on for each package.
                    </p>
                    <div v-if="$slots.donation" class="border-t border-border pt-5">
                        <slot name="donation" />
                    </div>
                </div>

                <div v-show="activeTab === 'media'" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Cause images</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Hero, icons, and gallery slider for the donate page.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <FormFile
                            label="Hero image"
                            :preview-url="assetUrl(form.hero_image_existing)"
                            @change="form.hero_image = $event.target.files[0]"
                        />
                        <FormFile
                            label="Cause icon"
                            accept="image/svg+xml,image/*"
                            :preview-url="assetUrl(form.icon_uri_existing)"
                            @change="form.icon_uri_file = $event.target.files[0]"
                        />
                        <FormFile
                            label="Active icon (white)"
                            accept="image/svg+xml,image/*"
                            :preview-url="assetUrl(form.icon_uri_active_existing)"
                            @change="form.icon_uri_active_file = $event.target.files[0]"
                        />
                        <FormFile
                            label="Gallery images"
                            hint="Select multiple files"
                            multiple
                            @change="form.images = Array.from($event.target.files)"
                        />
                    </div>
                </div>

                <div v-show="activeTab === 'whatsapp'" class="space-y-8">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">WhatsApp (AiSensy)</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Per-cause AiSensy account and campaigns. Global on/off switches live in
                            <Link href="/admin/settings" class="font-medium text-foreground underline decoration-border underline-offset-2 hover:decoration-foreground">Settings → Notifications</Link>.
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <label class="admin-label">AiSensy account</label>
                                <select v-model="form.aisensy_account_id" class="admin-input">
                                    <option value="">None / disabled</option>
                                    <option v-for="account in aisensyAccounts" :key="account.id" :value="account.id">
                                        {{ account.name }}
                                    </option>
                                </select>
                            </div>
                            <FormInput
                                v-model="form.aisensy_payment_link_campaign"
                                label="Payment link campaign"
                                hint="Used after a failed payment (Settings → Send WhatsApp Payment Link Message)."
                                :error="form.errors.aisensy_payment_link_campaign"
                            />
                        </div>
                    </div>

                    <div class="space-y-5 rounded-xl border border-border bg-muted/50 p-5">
                        <div>
                            <h4 class="text-sm font-semibold text-foreground">1. Thank you message</h4>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Sent after a successful donation when enabled below and
                                <strong class="font-medium text-foreground">Send WhatsApp Thank You Message</strong>
                                is on in Settings.
                            </p>
                        </div>
                        <FormToggle
                            v-model="form.aisensy_send_thank_you"
                            label="Send thank you for this cause"
                            description="Turn off to skip the text thank you for donations to this cause only."
                            compact
                        />
                        <div class="grid gap-4 lg:grid-cols-2">
                            <FormInput
                                v-model="form.aisensy_thank_you_campaign"
                                label="Thank you campaign"
                                hint="AiSensy text-only thank you campaign (e.g. thank you for donation general)."
                                :error="form.errors.aisensy_thank_you_campaign"
                            />
                            <div>
                                <label class="admin-label">Message mode</label>
                                <select v-model="form.aisensy_thank_you_message_mode" class="admin-input">
                                    <option value="template">Template</option>
                                    <option value="builder">Builder</option>
                                </select>
                            </div>
                        </div>
                        <FormTextarea
                            v-model="form.aisensy_thank_you_message_template"
                            label="Thank you message template"
                            hint="Placeholders: {name}, {full_name}, {amount}, {cause}, {receipt_number}. Leave blank to use the AiSensy campaign default text."
                            :rows="3"
                        />
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <FormToggle v-model="form.aisensy_thank_you_include_name" label="Include donor name" compact />
                            <FormToggle v-model="form.aisensy_thank_you_include_amount" label="Include amount" compact />
                            <FormToggle v-model="form.aisensy_thank_you_include_cause" label="Include cause" compact />
                            <FormToggle v-model="form.aisensy_thank_you_include_receipt" label="Include receipt number" compact />
                        </div>
                    </div>

                    <div class="space-y-5 rounded-xl border border-border bg-muted/50 p-5">
                        <div>
                            <h4 class="text-sm font-semibold text-foreground">2. Certificate message</h4>
                            <p class="mt-1 text-sm text-muted-foreground">
                                A separate WhatsApp when enabled below and
                                <strong class="font-medium text-foreground">Send Donation Certificate on WhatsApp</strong>
                                is on in Settings.
                            </p>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Upload Gujarati and English certificate designs for this cause.
                                Gujarat donors use the Gujarati template; everyone else uses English.
                                Leave a slot blank to fall back to the default bundled artwork.
                                If both thank you and certificate are enabled for this cause, thank you is sent first.
                            </p>
                        </div>
                        <FormToggle
                            v-model="form.aisensy_send_certificate"
                            label="Send certificate for this cause"
                            description="Turn off to skip the sanman patra image for donations to this cause only."
                            compact
                        />
                        <FormInput
                            v-model="form.aisensy_certificate_campaign"
                            label="Certificate campaign"
                            hint="AiSensy image campaign (e.g. certificate_of_donation_old_age_home)."
                            :error="form.errors.aisensy_certificate_campaign"
                        />
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <FormFile
                                    label="Gujarati certificate template"
                                    hint="JPG/PNG for Gujarat donors. Name and date positions use global settings."
                                    :preview-url="certificateTemplatePreview"
                                    @change="onCertificateTemplateChange"
                                />
                                <button
                                    v-if="certificateTemplatePreview"
                                    type="button"
                                    class="mt-2 text-sm font-medium text-rose-600 hover:text-rose-700"
                                    @click="clearCertificateTemplate"
                                >
                                    Remove Gujarati template
                                </button>
                            </div>
                            <div>
                                <FormFile
                                    label="English certificate template"
                                    hint="JPG/PNG for donors outside Gujarat (and when state is blank)."
                                    :preview-url="certificateTemplateEnglishPreview"
                                    @change="onCertificateTemplateEnglishChange"
                                />
                                <button
                                    v-if="certificateTemplateEnglishPreview"
                                    type="button"
                                    class="mt-2 text-sm font-medium text-rose-600 hover:text-rose-700"
                                    @click="clearCertificateTemplateEnglish"
                                >
                                    Remove English template
                                </button>
                            </div>
                        </div>
                        <div class="max-w-md">
                            <FormFile
                                label="Fallback image (optional)"
                                hint="Used only when PNG generation fails on the server."
                                :preview-url="thankYouImagePreview"
                                @change="onThankYouImageChange"
                            />
                            <button
                                v-if="thankYouImagePreview"
                                type="button"
                                class="mt-2 text-sm font-medium text-rose-600 hover:text-rose-700"
                                @click="clearThankYouImage"
                            >
                                Remove image
                            </button>
                        </div>
                    </div>

                    <div class="space-y-5 rounded-xl border border-border bg-muted/50 p-5">
                        <div>
                            <h4 class="text-sm font-semibold text-foreground">3. Receipt message</h4>
                            <p class="mt-1 text-sm text-muted-foreground">
                                A separate WhatsApp with the donation receipt PDF when enabled below and
                                <strong class="font-medium text-foreground">Send Donation Receipt on WhatsApp</strong>
                                is on in Settings.
                            </p>
                        </div>
                        <FormToggle
                            v-model="form.aisensy_send_receipt"
                            label="Send receipt for this cause"
                            description="Turn off to skip the receipt PDF for donations to this cause only."
                            compact
                        />
                        <FormInput
                            v-model="form.aisensy_receipt_campaign"
                            label="Receipt campaign"
                            hint="AiSensy document campaign (e.g. donation_receipt_pdf)."
                            :error="form.errors.aisensy_receipt_campaign"
                        />
                    </div>
                </div>
            </div>
        </div>

        <FormActions :processing="form.processing" :submit-label="isEdit ? 'Update cause' : 'Create cause'">
            <Link :href="cancelHref" class="admin-btn-secondary">Cancel</Link>
        </FormActions>
    </form>
</template>
