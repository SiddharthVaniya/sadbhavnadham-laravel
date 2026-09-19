<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import AttributionSourceCard from '@/Components/Admin/AttributionSourceCard.vue';

const props = defineProps({
    donation: { type: Object, required: true },
    back_url: { type: String, default: '/admin/donations' },
});

const page = usePage();
const canManageReceipts = computed(() => page.props.auth.permissions?.includes('manage receipts') ?? false);
const resending = ref(false);
const generating = ref(false);
const queuingAction = ref(null);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const isDailyNeedItem = (item) => {
    if (item?.is_daily_needs) {
        return true;
    }

    const cause = String(item?.cause ?? '').trim().toLowerCase();
    const slug = String(item?.cause_slug ?? '').trim().toLowerCase();
    const title = String(item?.title ?? '').trim().toLowerCase();

    return (
        slug === 'daily-needs'
        || cause === 'daily need'
        || cause === 'daily needs'
        || cause === 'daily-needs'
        || title.startsWith('daily needs')
        || title.startsWith('daily need')
        || /=\s*₹/.test(String(item?.title ?? ''))
    );
};

const formatLineItemTitle = (item) => {
    const raw = String(item?.title ?? '').replace(/\r\n/g, '\n').trim();
    if (!raw) {
        return '';
    }

    if (raw.includes('\n')) {
        return raw;
    }

    return raw
        .replace(/,\s*(?=\d)/g, ',\n')
        .replace(/\s*—\s*total/i, '\n— total');
};

const displayCause = (item) => (isDailyNeedItem(item) ? 'Daily Need' : item.cause);

const isDailyNeedsDonation = computed(() => {
    const items = props.donation.items ?? [];
    return items.length > 0 && items.every(isDailyNeedItem);
});

const dailyNeedRows = computed(() => {
    const items = props.donation.items ?? [];
    const rows = [];

    for (const item of items) {
        if (!isDailyNeedItem(item)) {
            continue;
        }

        const lines = Array.isArray(item.daily_needs_lines) ? item.daily_needs_lines : [];
        if (lines.length) {
            for (const line of lines) {
                rows.push({
                    title: line.title,
                    qty_label: line.qty_label || String(line.qty || 1),
                    unit_price: Number(line.unit_price || 0),
                    amount: Number(line.amount || 0),
                });
            }
            continue;
        }

        // Fallback: keep one row if lines could not be parsed.
        rows.push({
            title: formatLineItemTitle(item) || item.title || 'Daily Need',
            qty_label: String(item.quantity || 1),
            unit_price: 0,
            amount: Number(item.amount || 0),
        });
    }

    return rows;
});

const dailyNeedTotal = computed(() =>
    dailyNeedRows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0),
);

const causeColumnLabel = computed(() => {
    const items = props.donation.items ?? [];

    if (items.length && items.every(isDailyNeedItem)) {
        return 'Daily Need';
    }

    return 'Cause';
});

const delivery = computed(() => props.donation.delivery ?? {
    email: { status: 'not_sent', label: props.donation.receipt_label || props.donation.receipt_email_label || 'Not sent', at: null, error: null },
    whatsapp: { status: 'not_sent', label: 'Not sent', at: null },
    certificate_whatsapp: { status: 'not_sent', label: 'Not sent', at: null },
    receipt_whatsapp: { status: 'not_sent', label: 'Not sent', at: null },
    payment_link_whatsapp: { status: 'not_applicable', label: '—', at: null, url: null },
    payment_link_email: { status: 'not_applicable', label: '—', at: null },
    payment_link_sms: { status: 'not_applicable', label: '—', at: null },
    sheet: { status: 'not_logged', label: 'Not logged', at: null },
    follow_up_sheet: { status: 'not_applicable', label: '—', at: null },
});

const isFailed = computed(() => Boolean(props.donation.is_failed));
const isPending = computed(() => Boolean(props.donation.is_pending));
const isPaid = computed(() => Boolean(props.donation.is_paid));
const showPaymentLink = computed(() => isFailed.value
    || isPending.value
    || Boolean(delivery.value.payment_link_whatsapp?.url)
    || delivery.value.payment_link_whatsapp?.status === 'sent'
    || delivery.value.payment_link_email?.status === 'sent'
    || delivery.value.payment_link_sms?.status === 'sent');

const canResendEmail = computed(() => Boolean(props.donation.can_resend_receipt_email));
const canResendSheet = computed(() => Boolean(props.donation.can_resend_sheet));
const canResendThankYou = computed(() => Boolean(props.donation.can_resend_thank_you_whatsapp));
const canResendCertificate = computed(() => Boolean(props.donation.can_resend_certificate_whatsapp));
const canResendReceiptWhatsApp = computed(() => Boolean(props.donation.can_resend_receipt_whatsapp));
const canResendPaymentLink = computed(() => Boolean(props.donation.can_resend_payment_link_whatsapp));
const canNotifyPaymentLinkEmail = computed(() => Boolean(props.donation.can_notify_payment_link_email));
const canNotifyPaymentLinkSms = computed(() => Boolean(props.donation.can_notify_payment_link_sms));

const addressLine = computed(() => {
    const parts = [
        props.donation.address,
        [props.donation.city, props.donation.state, props.donation.pincode].filter(Boolean).join(', '),
        props.donation.country,
    ].filter(Boolean);

    return parts.join(' · ') || null;
});

const statusTone = (status) => {
    if (['sent', 'logged'].includes(status)) {
        return 'bg-emerald-50 text-emerald-800';
    }

    if (status === 'failed') {
        return 'bg-rose-50 text-rose-800';
    }

    if (status === 'not_applicable') {
        return 'bg-muted/50 text-muted-foreground';
    }

    return 'bg-amber-50 text-amber-900';
};

const deliveryRows = computed(() => {
    const rows = [];

    if (showPaymentLink.value) {
        rows.push(
            {
                key: 'payment_link_whatsapp',
                label: 'Link WhatsApp',
                status: delivery.value.payment_link_whatsapp?.status,
                labelText: delivery.value.payment_link_whatsapp?.label || '—',
                at: delivery.value.payment_link_whatsapp?.at,
            },
            {
                key: 'payment_link_email',
                label: 'Link email',
                status: delivery.value.payment_link_email?.status,
                labelText: delivery.value.payment_link_email?.label || '—',
                at: delivery.value.payment_link_email?.at,
            },
            {
                key: 'payment_link_sms',
                label: 'Link SMS',
                status: delivery.value.payment_link_sms?.status,
                labelText: delivery.value.payment_link_sms?.label || '—',
                at: delivery.value.payment_link_sms?.at,
            },
        );
    }

    if (isPaid.value || props.donation.receipt_number) {
        rows.push(
            {
                key: 'email',
                label: 'Email',
                status: delivery.value.email?.status,
                labelText: delivery.value.email?.label || 'Not sent',
                at: delivery.value.email?.at,
                error: delivery.value.email?.error,
            },
            {
                key: 'whatsapp',
                label: 'Thank-you',
                status: delivery.value.whatsapp?.status,
                labelText: delivery.value.whatsapp?.label || 'Not sent',
                at: delivery.value.whatsapp?.at,
            },
            {
                key: 'certificate_whatsapp',
                label: 'Certificate',
                status: delivery.value.certificate_whatsapp?.status,
                labelText: delivery.value.certificate_whatsapp?.label || 'Not sent',
                at: delivery.value.certificate_whatsapp?.at,
            },
            {
                key: 'receipt_whatsapp',
                label: 'Receipt WA',
                status: delivery.value.receipt_whatsapp?.status,
                labelText: delivery.value.receipt_whatsapp?.label || 'Not sent',
                at: delivery.value.receipt_whatsapp?.at,
            },
            {
                key: 'sheet',
                label: 'Sheet',
                status: delivery.value.sheet?.status,
                labelText: delivery.value.sheet?.label || 'Not logged',
                at: delivery.value.sheet?.at,
            },
        );
    }

    if (isFailed.value || delivery.value.follow_up_sheet?.status === 'logged') {
        rows.push({
            key: 'follow_up_sheet',
            label: 'Follow-up',
            status: delivery.value.follow_up_sheet?.status,
            labelText: delivery.value.follow_up_sheet?.label || '—',
            at: delivery.value.follow_up_sheet?.at,
        });
    }

    return rows;
});

const queueDelivery = (action, url, canRun) => {
    if (!canRun || queuingAction.value) {
        return;
    }

    queuingAction.value = action;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            queuingAction.value = null;
        },
    });
};

const resendReceipt = () => {
    if (!canResendEmail.value || resending.value) {
        return;
    }

    resending.value = true;
    router.post(props.donation.receipt_resend_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            resending.value = false;
        },
    });
};

const generateReceipt = () => {
    if (generating.value) {
        return;
    }

    generating.value = true;
    router.post(props.donation.receipt_generate_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            generating.value = false;
        },
    });
};

const paymentLinkButtonLabel = computed(() => {
    if (queuingAction.value === 'payment_link') {
        return '…';
    }

    if (isPending.value) {
        return 'Send link instant';
    }

    if (!delivery.value.payment_link_whatsapp?.url) {
        return 'Create & send WA';
    }

    return delivery.value.payment_link_whatsapp.status === 'sent'
        ? 'Resend link WA'
        : 'Send link WA';
});

const paymentLinkEmailButtonLabel = computed(() => {
    if (queuingAction.value === 'payment_link_email') {
        return '…';
    }

    return delivery.value.payment_link_email?.status === 'sent'
        ? 'Resend email'
        : 'Link email';
});

const paymentLinkSmsButtonLabel = computed(() => {
    if (queuingAction.value === 'payment_link_sms') {
        return '…';
    }

    return delivery.value.payment_link_sms?.status === 'sent'
        ? 'Resend SMS'
        : 'Link SMS';
});
</script>

<template>
    <Head title="Donation Details" />
    <AdminLayout>
        <template #header>Donation details</template>
        <PageHeader :title="`Donation ${donation.payment_id}`" :subtitle="donation.created_date">
            <template #actions>
                <Link
                    v-if="donation.edit_url"
                    :href="donation.edit_url"
                    class="rounded-lg border border-border px-3 py-2 text-sm"
                >
                    Edit
                </Link>
                <Link :href="back_url" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
            </template>
        </PageHeader>

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="space-y-4 xl:col-span-8">
                <section class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">{{ donation.donor_name }}</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ donation.donor_email || 'No email' }}
                                <span class="text-muted-foreground">·</span>
                                {{ donation.donor_phone || 'No phone' }}
                            </p>
                        </div>
                        <StatusBadge :status="donation.status" />
                    </div>

                    <dl class="mt-4 grid gap-x-6 gap-y-3 border-t border-border pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Amount</dt>
                            <dd class="mt-0.5 font-semibold text-foreground">{{ formatMoney(donation.total_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Provider</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ donation.provider }}</dd>
                        </div>
                        <div v-if="donation.paid_at">
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Paid at</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ donation.paid_at }}</dd>
                        </div>
                        <div v-if="donation.receipt_number">
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Receipt</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ donation.receipt_number }}</dd>
                        </div>
                        <div v-if="donation.pan_number">
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">PAN</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ donation.pan_number }}</dd>
                        </div>
                        <div v-if="addressLine" class="sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Address</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ addressLine }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">Line items</h3>

                    <div v-if="isDailyNeedsDonation" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-border text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="pb-2 text-left font-medium">Item</th>
                                    <th class="pb-2 text-left font-medium">Qty</th>
                                    <th class="pb-2 text-right font-medium">Rate</th>
                                    <th class="pb-2 text-right font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in dailyNeedRows"
                                    :key="`${row.title}-${i}`"
                                    class="border-b border-border"
                                >
                                    <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.title }}</td>
                                    <td class="py-2.5 pr-3 text-muted-foreground">{{ row.qty_label }}</td>
                                    <td class="py-2.5 pr-3 text-right text-muted-foreground">
                                        {{ row.unit_price > 0 ? formatMoney(row.unit_price) : '—' }}
                                    </td>
                                    <td class="py-2.5 text-right font-medium">{{ formatMoney(row.amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-border">
                                    <td class="pt-3 text-sm font-semibold text-foreground" colspan="3">Total</td>
                                    <td class="pt-3 text-right text-sm font-semibold text-foreground">
                                        {{ formatMoney(dailyNeedTotal || donation.total_amount) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-border text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="pb-2 text-left font-medium">{{ causeColumnLabel }}</th>
                                    <th class="pb-2 text-left font-medium">Title</th>
                                    <th class="pb-2 text-left font-medium">Campaign</th>
                                    <th class="pb-2 text-right font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, i) in donation.items"
                                    :key="i"
                                    class="border-b border-border last:border-0"
                                >
                                    <td class="py-2.5 pr-3 align-top">{{ displayCause(item) }}</td>
                                    <td class="py-2.5 pr-3 align-top min-w-[18rem] max-w-2xl">
                                        <div class="break-words text-sm leading-relaxed">{{ item.title }}</div>
                                        <div
                                            v-if="item.honoree_names?.length"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            <div v-for="(name, nameIndex) in item.honoree_names" :key="nameIndex">
                                                {{ name }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 pr-3 align-top text-muted-foreground">{{ item.campaign || '—' }}</td>
                                    <td class="py-2.5 align-top text-right font-medium">{{ formatMoney(item.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    v-if="deliveryRows.length || isPaid || donation.receipt_number || showPaymentLink"
                    class="rounded-xl border border-border bg-card p-4 shadow-none"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-foreground">Delivery & receipt</h3>
                        <div
                            v-if="canManageReceipts && (isPaid || donation.receipt_number)"
                            class="flex flex-wrap items-center gap-x-2 text-xs"
                        >
                            <a :href="donation.receipt_preview_url" target="_blank" class="font-medium hover:underline">Preview</a>
                            <span class="text-muted-foreground">·</span>
                            <a :href="donation.receipt_print_url" target="_blank" class="font-medium hover:underline">Open</a>
                            <span class="text-muted-foreground">·</span>
                            <button
                                type="button"
                                class="font-medium hover:underline disabled:opacity-60"
                                :disabled="generating"
                                @click="generateReceipt"
                            >
                                {{ generating ? 'Queuing…' : 'Generate' }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="deliveryRows.length"
                        class="mt-3 grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="row in deliveryRows"
                            :key="row.key"
                            class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1.5"
                            :title="row.error || row.at || undefined"
                        >
                            <span class="truncate text-xs text-muted-foreground">{{ row.label }}</span>
                            <span
                                class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="statusTone(row.status)"
                            >
                                {{ row.labelText }}
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="delivery.email?.error"
                        class="mt-2 truncate text-xs text-rose-600"
                        :title="delivery.email.error"
                    >
                        {{ delivery.email.error }}
                    </p>

                    <div
                        v-if="canManageReceipts && isPaid"
                        class="mt-3 flex flex-wrap gap-1.5 border-t border-border pt-3"
                    >
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                            :disabled="!canResendThankYou || Boolean(queuingAction)"
                            @click="queueDelivery('thank_you', donation.whatsapp_thank_you_url, canResendThankYou)"
                        >
                            {{ queuingAction === 'thank_you' ? '…' : (delivery.whatsapp.status === 'sent' ? 'Resend thank-you' : 'Thank-you') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                            :disabled="!canResendCertificate || Boolean(queuingAction)"
                            @click="queueDelivery('certificate', donation.whatsapp_certificate_url, canResendCertificate)"
                        >
                            {{ queuingAction === 'certificate' ? '…' : (delivery.certificate_whatsapp.status === 'sent' ? 'Resend cert' : 'Certificate') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                            :disabled="!canResendReceiptWhatsApp || Boolean(queuingAction)"
                            @click="queueDelivery('receipt_whatsapp', donation.whatsapp_receipt_url, canResendReceiptWhatsApp)"
                        >
                            {{ queuingAction === 'receipt_whatsapp' ? '…' : (delivery.receipt_whatsapp.status === 'sent' ? 'Resend receipt WA' : 'Receipt WA') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                            :disabled="!canResendSheet || Boolean(queuingAction)"
                            @click="queueDelivery('sheet', donation.sheet_resend_url, canResendSheet)"
                        >
                            {{ queuingAction === 'sheet' ? '…' : (delivery.sheet.status === 'logged' ? 'Re-log sheet' : 'Log sheet') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-foreground px-2 py-1 text-xs text-background disabled:opacity-60"
                            :disabled="!canResendEmail || resending"
                            @click="resendReceipt"
                        >
                            {{ resending ? '…' : (delivery.email.status === 'sent' ? 'Resend email' : 'Send email') }}
                        </button>
                    </div>

                    <div
                        v-else-if="canManageReceipts && (isFailed || isPending)"
                        class="mt-3 space-y-2 border-t border-border pt-3"
                    >
                        <p v-if="isPending" class="text-xs text-muted-foreground">
                            Sends a Razorpay payment link on WhatsApp and marks this donation failed. Pending checkouts also get this link automatically after 5 minutes.
                        </p>
                        <div
                            v-if="delivery.payment_link_whatsapp.url || donation.payment_link_url"
                            class="truncate text-xs"
                        >
                            <a
                                :href="delivery.payment_link_whatsapp.url || donation.payment_link_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-sky-700 hover:underline"
                            >
                                {{ delivery.payment_link_whatsapp.url || donation.payment_link_url }}
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                class="rounded-md bg-foreground px-2 py-1 text-xs text-background disabled:opacity-60"
                                :disabled="!canResendPaymentLink || Boolean(queuingAction)"
                                @click="queueDelivery('payment_link', donation.whatsapp_payment_link_url, canResendPaymentLink)"
                            >
                                {{ paymentLinkButtonLabel }}
                            </button>
                            <template v-if="isFailed">
                                <button
                                    type="button"
                                    class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                                    :disabled="!canNotifyPaymentLinkEmail || Boolean(queuingAction)"
                                    @click="queueDelivery('payment_link_email', donation.payment_link_notify_email_url, canNotifyPaymentLinkEmail)"
                                >
                                    {{ paymentLinkEmailButtonLabel }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-border px-2 py-1 text-xs disabled:opacity-60"
                                    :disabled="!canNotifyPaymentLinkSms || Boolean(queuingAction)"
                                    @click="queueDelivery('payment_link_sms', donation.payment_link_notify_sms_url, canNotifyPaymentLinkSms)"
                                >
                                    {{ paymentLinkSmsButtonLabel }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <p v-if="canManageReceipts && isPaid && !canResendThankYou && !canResendCertificate" class="mt-2 text-xs text-rose-600">
                        Add a valid donor phone before sending WhatsApp.
                    </p>
                    <p v-else-if="canManageReceipts && isPaid && !canResendEmail" class="mt-2 text-xs text-rose-600">
                        Add a valid donor email before resending the receipt.
                    </p>
                    <p v-else-if="canManageReceipts && (isFailed || isPending) && !canResendPaymentLink" class="mt-2 text-xs text-rose-600">
                        Add a valid donor phone before sending the payment link WhatsApp.
                    </p>
                </section>
            </div>

            <div class="space-y-4 xl:col-span-4">
                <AttributionSourceCard
                    :source="donation.source"
                    empty-message="No UTM or referrer captured for this donation."
                />
            </div>
        </div>
    </AdminLayout>
</template>
