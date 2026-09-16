<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormFile from '@/Components/Admin/FormFile.vue';
import FormActions from '@/Components/Admin/FormActions.vue';

const props = defineProps({
    branding: { type: Object, required: true },
});

const page = usePage();
const status = () => page.props.flash?.status;

const assetUrl = (path) => (path ? `/${path.replace(/^\/+/, '')}` : '');

const form = useForm({
    name: props.branding.name ?? '',
    short_name: props.branding.short_name ?? '',
    legal_name: props.branding.legal_name ?? '',
    tagline: props.branding.tagline ?? '',
    admin_label: props.branding.admin_label ?? '',
    razorpay_name: props.branding.razorpay_name ?? '',
    recurring_mandate: props.branding.recurring_mandate ?? '',
    receipt_thank_you: props.branding.receipt_thank_you ?? '',
    payment_link_description: props.branding.payment_link_description ?? '',
    website_url: props.branding.website_url ?? '',
    privacy_url: props.branding.privacy_url ?? '',
    refund_url: props.branding.refund_url ?? '',
    terms_url: props.branding.terms_url ?? '',
    canonical_url: props.branding.canonical_url ?? '',
    seo_home_description: props.branding.seo_home_description ?? '',
    logo: null,
    logo_existing: props.branding.logo ?? '',
    logo_public: null,
    logo_public_existing: props.branding.logo_public ?? '',
    favicon: null,
    favicon_existing: props.branding.favicon ?? '',
    og_image: null,
    og_image_existing: props.branding.og_image ?? '',
    footer_about: props.branding.footer_about ?? '',
    contact_address: props.branding.contact_address ?? '',
    contact_phone_primary: props.branding.contact_phone_primary ?? '',
    contact_phone_secondary: props.branding.contact_phone_secondary ?? '',
    contact_email: props.branding.contact_email ?? '',
    social_facebook: props.branding.social_facebook ?? '',
    social_instagram: props.branding.social_instagram ?? '',
    social_youtube: props.branding.social_youtube ?? '',
    social_whatsapp: props.branding.social_whatsapp ?? '',
    bank_account_name: props.branding.bank_account_name ?? '',
    bank_account_number: props.branding.bank_account_number ?? '',
    bank_ifsc: props.branding.bank_ifsc ?? '',
    bank_name: props.branding.bank_name ?? '',
    bank_branch: props.branding.bank_branch ?? '',
    bank_account_type: props.branding.bank_account_type ?? '',
    bank_upi_id: props.branding.bank_upi_id ?? '',
    bank_note: props.branding.bank_note ?? '',
});

const submit = () => {
    form.post('/admin/settings/branding', { forceFormData: true });
};
</script>

<template>
    <Head title="Branding" />
    <AdminLayout>
        <template #header>Settings</template>
        <PageHeader
            title="Branding"
            subtitle="Update site name, logos, footer links, and SEO. Empty fields fall back to .env defaults."
        />

        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <Link href="/admin/settings" class="text-muted-foreground hover:underline">← Notification settings</Link>
            <span v-if="status()" class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800">{{ status() }}</span>
        </div>

        <form class="w-full space-y-6" @submit.prevent="submit">
            <FormSection title="Organisation" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.name" label="Full name" :error="form.errors.name" hint="Public site title" />
                    <FormInput v-model="form.short_name" label="Short name" :error="form.errors.short_name" />
                    <FormInput v-model="form.legal_name" label="Legal name" :error="form.errors.legal_name" hint="Footer & receipts" />
                    <FormInput v-model="form.admin_label" label="Admin label" :error="form.errors.admin_label" />
                </div>
                <FormTextarea v-model="form.tagline" label="Tagline" :error="form.errors.tagline" :rows="2" />
            </FormSection>

            <FormSection title="Payments & messages" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.razorpay_name" label="Razorpay checkout name" :error="form.errors.razorpay_name" />
                    <FormInput v-model="form.payment_link_description" label="Payment link description" :error="form.errors.payment_link_description" />
                </div>
                <FormTextarea
                    v-model="form.recurring_mandate"
                    label="Monthly mandate text"
                    hint="Use {brand}, {name}, or {legal_name}"
                    :error="form.errors.recurring_mandate"
                    :rows="2"
                />
                <FormTextarea
                    v-model="form.receipt_thank_you"
                    label="Receipt thank-you line"
                    hint="Use {brand}, {name}, or {legal_name}"
                    :error="form.errors.receipt_thank_you"
                    :rows="2"
                />
            </FormSection>

            <FormSection title="Links" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.website_url" label="Main website URL" type="url" :error="form.errors.website_url" />
                    <FormInput v-model="form.privacy_url" label="Privacy policy URL" type="url" :error="form.errors.privacy_url" />
                    <FormInput v-model="form.refund_url" label="Refund policy URL" type="url" :error="form.errors.refund_url" />
                    <FormInput v-model="form.terms_url" label="Terms URL" type="url" :error="form.errors.terms_url" />
                </div>
            </FormSection>

            <FormSection title="Footer & contact" dense>
                <FormTextarea
                    v-model="form.footer_about"
                    label="Footer about text"
                    :error="form.errors.footer_about"
                    :rows="3"
                />
                <FormTextarea
                    v-model="form.contact_address"
                    label="Address"
                    :error="form.errors.contact_address"
                    :rows="2"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.contact_phone_primary" label="Primary phone" :error="form.errors.contact_phone_primary" />
                    <FormInput v-model="form.contact_phone_secondary" label="Secondary phone" :error="form.errors.contact_phone_secondary" />
                    <FormInput v-model="form.contact_email" label="Email" type="email" :error="form.errors.contact_email" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.social_facebook" label="Facebook URL" type="url" :error="form.errors.social_facebook" />
                    <FormInput v-model="form.social_instagram" label="Instagram URL" type="url" :error="form.errors.social_instagram" />
                    <FormInput v-model="form.social_youtube" label="YouTube URL" type="url" :error="form.errors.social_youtube" />
                    <FormInput v-model="form.social_whatsapp" label="WhatsApp URL" type="url" :error="form.errors.social_whatsapp" />
                </div>
            </FormSection>

            <FormSection title="Bank details" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.bank_account_name" label="Account name" :error="form.errors.bank_account_name" />
                    <FormInput v-model="form.bank_account_number" label="Account number" :error="form.errors.bank_account_number" />
                    <FormInput v-model="form.bank_ifsc" label="IFSC" :error="form.errors.bank_ifsc" />
                    <FormInput v-model="form.bank_name" label="Bank name" :error="form.errors.bank_name" />
                    <FormInput v-model="form.bank_branch" label="Branch" :error="form.errors.bank_branch" />
                    <FormInput v-model="form.bank_account_type" label="Account type" :error="form.errors.bank_account_type" />
                    <FormInput v-model="form.bank_upi_id" label="UPI ID" :error="form.errors.bank_upi_id" />
                </div>
                <FormTextarea
                    v-model="form.bank_note"
                    label="Bank transfer note"
                    hint="Shown under bank details for donors"
                    :error="form.errors.bank_note"
                    :rows="2"
                />
            </FormSection>

            <FormSection title="SEO" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.canonical_url" label="Canonical URL" type="url" :error="form.errors.canonical_url" />
                </div>
                <FormTextarea
                    v-model="form.seo_home_description"
                    label="Homepage meta description"
                    :error="form.errors.seo_home_description"
                    :rows="2"
                />
            </FormSection>

            <FormSection title="Logos & images" dense>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormFile label="Admin logo" :preview-url="assetUrl(form.logo_existing)" @change="form.logo = $event.target.files[0]" />
                    <FormFile label="Public donate logo" :preview-url="assetUrl(form.logo_public_existing)" @change="form.logo_public = $event.target.files[0]" />
                    <FormFile label="Favicon" :preview-url="assetUrl(form.favicon_existing)" @change="form.favicon = $event.target.files[0]" />
                    <FormFile label="Social share image (OG)" :preview-url="assetUrl(form.og_image_existing)" @change="form.og_image = $event.target.files[0]" />
                </div>
            </FormSection>

            <FormActions :processing="form.processing">
                <Link href="/admin/settings" class="admin-btn-secondary">Back</Link>
            </FormActions>
        </form>
    </AdminLayout>
</template>
