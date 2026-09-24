<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
    qrCode: { type: Object, required: true },
    causes: { type: Array, default: () => [] },
    can_update: { type: Boolean, default: false },
});

const page = usePage();
const canClose = computed(() => page.props.auth.permissions?.includes('close qr codes')
    || page.props.auth.permissions?.includes('manage qr codes'));
const canSync = computed(() => page.props.auth.permissions?.includes('sync qr codes')
    || page.props.auth.permissions?.includes('manage qr codes'));

const mappingForm = useForm({
    cause_id: props.qrCode.cause_id ? String(props.qrCode.cause_id) : '',
    cause_package_id: props.qrCode.cause_package_id ? String(props.qrCode.cause_package_id) : '',
});

const filteredPackages = computed(() => {
    if (!mappingForm.cause_id) {
        return [];
    }

    const cause = props.causes.find((item) => String(item.id) === String(mappingForm.cause_id));

    return cause?.packages ?? [];
});

watch(() => mappingForm.cause_id, (next, prev) => {
    if (String(next) !== String(prev)) {
        mappingForm.cause_package_id = '';
    }
});

const formatMoney = (amount) => {
    if (amount === null || amount === undefined) {
        return 'Any amount';
    }

    return `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const closeQr = () => {
    if (!confirm('Close this QR code in Razorpay? New scans will stop working.')) {
        return;
    }

    router.post(props.qrCode.close_url, {}, { preserveScroll: true });
};

const syncQr = () => {
    router.post(props.qrCode.sync_url, {}, { preserveScroll: true });
};

const saveMapping = () => {
    mappingForm.transform((data) => ({
        cause_id: data.cause_id || null,
        cause_package_id: data.cause_id && data.cause_package_id ? data.cause_package_id : null,
    })).put(props.qrCode.update_url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`QR · ${qrCode.name}`" />
    <AdminLayout>
        <template #header>QR code details</template>

        <PageHeader :title="qrCode.name" :subtitle="qrCode.razorpay_qr_code_id">
            <template #actions>
                <Link href="/admin/qr-codes" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
                <button
                    v-if="canSync"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                    @click="syncQr"
                >
                    Sync
                </button>
                <button
                    v-if="canClose && qrCode.can_close"
                    type="button"
                    class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                    @click="closeQr"
                >
                    Close QR
                </button>
            </template>
        </PageHeader>

        <div class="grid min-w-0 gap-6 lg:grid-cols-12">
            <section class="min-w-0 rounded-xl border border-border bg-card p-5 shadow-none lg:col-span-5">
                <h3 class="text-sm font-semibold text-foreground">QR image</h3>
                <div class="mt-4 flex justify-center rounded-lg border border-dashed border-border bg-muted/30 p-4">
                    <img
                        v-if="qrCode.image_url"
                        :src="qrCode.image_url"
                        :alt="qrCode.name"
                        class="max-h-72 w-auto max-w-full"
                    >
                    <p v-else class="text-sm text-muted-foreground">No image URL from Razorpay</p>
                </div>
                <a
                    v-if="qrCode.image_url"
                    :href="qrCode.image_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-block text-sm font-medium text-indigo-700 hover:underline"
                >
                    Open / download image
                </a>
            </section>

            <section class="min-w-0 space-y-4 lg:col-span-7">
                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-foreground">Details</h3>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="qrCode.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                        >
                            {{ qrCode.status_label }}
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">Usage</dt>
                            <dd class="font-medium">{{ qrCode.usage_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Amount</dt>
                            <dd class="font-medium">
                                {{ qrCode.fixed_amount ? formatMoney(qrCode.payment_amount) : 'Any amount' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Payments received</dt>
                            <dd class="font-medium">{{ qrCode.payments_count_received }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Collected</dt>
                            <dd class="font-medium">{{ formatMoney(qrCode.payments_amount_received) }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground">Razorpay ID</dt>
                            <dd class="break-all font-mono text-xs font-medium">{{ qrCode.razorpay_qr_code_id }}</dd>
                        </div>
                        <div v-if="qrCode.description" class="sm:col-span-2">
                            <dt class="text-muted-foreground">Description</dt>
                            <dd class="break-words font-medium">{{ qrCode.description }}</dd>
                        </div>
                        <div v-if="qrCode.razorpay_created_at">
                            <dt class="text-muted-foreground">Created at Razorpay</dt>
                            <dd class="font-medium">{{ qrCode.razorpay_created_at }}</dd>
                        </div>
                        <div v-if="qrCode.closed_at">
                            <dt class="text-muted-foreground">Closed</dt>
                            <dd class="font-medium">{{ qrCode.closed_at }} · {{ qrCode.close_reason || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="text-sm font-semibold text-foreground">Cause mapping</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Mapped QR payments create a donation line item for the selected cause automatically.
                    </p>

                    <form v-if="can_update" class="mt-4 space-y-3" @submit.prevent="saveMapping">
                        <div>
                            <label class="admin-label">Cause</label>
                            <select v-model="mappingForm.cause_id" class="admin-input">
                                <option value="">No cause (complete manually on donation)</option>
                                <option v-for="cause in causes" :key="cause.id" :value="String(cause.id)">
                                    {{ cause.title }}
                                </option>
                            </select>
                            <p v-if="mappingForm.errors.cause_id" class="mt-1 text-sm text-rose-700">{{ mappingForm.errors.cause_id }}</p>
                        </div>

                        <div v-if="mappingForm.cause_id">
                            <label class="admin-label">Package (optional)</label>
                            <select v-model="mappingForm.cause_package_id" class="admin-input">
                                <option value="">Any / custom amount</option>
                                <option v-for="pkg in filteredPackages" :key="pkg.id" :value="String(pkg.id)">
                                    {{ pkg.title }} — ₹ {{ Number(pkg.amount || 0).toLocaleString('en-IN') }}
                                </option>
                            </select>
                            <p v-if="mappingForm.errors.cause_package_id" class="mt-1 text-sm text-rose-700">{{ mappingForm.errors.cause_package_id }}</p>
                        </div>

                        <button type="submit" class="admin-btn-primary !py-2" :disabled="mappingForm.processing">
                            {{ mappingForm.processing ? 'Saving…' : 'Save mapping' }}
                        </button>
                    </form>

                    <dl v-else class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">Cause</dt>
                            <dd class="font-medium">{{ qrCode.cause_title || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Package</dt>
                            <dd class="font-medium">{{ qrCode.package_title || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="text-sm font-semibold text-foreground">Donations</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        QR payments appear under Donations with provider Razorpay QR.
                    </p>
                    <Link :href="qrCode.donations_url" class="mt-3 inline-block text-sm font-medium text-indigo-700 hover:underline">
                        View Razorpay QR donations
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
