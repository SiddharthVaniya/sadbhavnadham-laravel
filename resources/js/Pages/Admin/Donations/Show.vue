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
const resendingWhatsApp = ref(false);
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
const isPaid = computed(() => Boolean(props.donation.is_paid));
const showPaymentLink = computed(() => isFailed.value
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
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (status === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-800';
    }

    if (status === 'not_applicable') {
        return 'border-border bg-muted/40 text-muted-foreground';
    }

    return 'border-amber-200 bg-amber-50 text-amber-900';
};

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

const resendReceiptWhatsApp = () => {
    if (!canResendReceiptWhatsApp.value || resendingWhatsApp.value) {
        return;
    }

    resendingWhatsApp.value = true;
    router.post(props.donation.whatsapp_receipt_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            resendingWhatsApp.value = false;
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
        return 'Queuing…';
    }

    if (!delivery.value.payment_link_whatsapp?.url) {
        return 'Create link & send WhatsApp';
    }

    return delivery.value.payment_link_whatsapp.status === 'sent'
        ? 'Resend payment link WhatsApp'
        : 'Send payment link WhatsApp';
});

const paymentLinkEmailButtonLabel = computed(() => {
    if (queuingAction.value === 'payment_link_email') {
        return 'Queuing…';
    }

    return delivery.value.payment_link_email?.status === 'sent'
        ? 'Resend via Email'
        : 'Send via Email';
});

const paymentLinkSmsButtonLabel = computed(() => {
    if (queuingAction.value === 'payment_link_sms') {
        return 'Queuing…';
    }

    return delivery.value.payment_link_sms?.status === 'sent'
        ? 'Resend via SMS'
        : 'Send via SMS';
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
            <div class="space-y-4 xl:col-span-7">
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
                    v-if="showPaymentLink"
                    class="rounded-xl border border-border bg-card p-5 shadow-none"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Payment recovery link</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Sent on failed payments so the donor can retry via Razorpay.
                            </p>
                        </div>
                        <span
                            class="rounded-md border px-2 py-1 text-xs font-medium"
                            :class="statusTone(delivery.payment_link_whatsapp.status)"
                        >
                            {{ delivery.payment_link_whatsapp.label }}
                        </span>
                    </div>

                    <div class="mt-3 space-y-2 text-sm">
                        <p v-if="delivery.payment_link_whatsapp.at" class="text-xs text-muted-foreground">
                            WhatsApp sent {{ delivery.payment_link_whatsapp.at }}
                        </p>
                        <p v-if="delivery.payment_link_email?.at" class="text-xs text-muted-foreground">
                            Email sent {{ delivery.payment_link_email.at }}
                        </p>
                        <p v-if="delivery.payment_link_sms?.at" class="text-xs text-muted-foreground">
                            SMS sent {{ delivery.payment_link_sms.at }}
                        </p>
                        <a
                            v-if="delivery.payment_link_whatsapp.url || donation.payment_link_url"
                            :href="delivery.payment_link_whatsapp.url || donation.payment_link_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block break-all font-medium text-sky-700 hover:underline"
                        >
                            {{ delivery.payment_link_whatsapp.url || donation.payment_link_url }}
                        </a>
                        <p v-else class="text-muted-foreground">No payment link created yet.</p>
                    </div>

                    <div v-if="canManageReceipts && isFailed" class="mt-4 space-y-2 border-t border-border pt-3">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-foreground px-3 py-2 text-sm text-background disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendPaymentLink || Boolean(queuingAction)"
                            @click="queueDelivery('payment_link', donation.whatsapp_payment_link_url, canResendPaymentLink)"
                        >
                            {{ paymentLinkButtonLabel }}
                        </button>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canNotifyPaymentLinkEmail || Boolean(queuingAction)"
                                @click="queueDelivery('payment_link_email', donation.payment_link_notify_email_url, canNotifyPaymentLinkEmail)"
                            >
                                {{ paymentLinkEmailButtonLabel }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border px-3 py-2 text-sm text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canNotifyPaymentLinkSms || Boolean(queuingAction)"
                                @click="queueDelivery('payment_link_sms', donation.payment_link_notify_sms_url, canNotifyPaymentLinkSms)"
                            >
                                {{ paymentLinkSmsButtonLabel }}
                            </button>
                        </div>
                        <p v-if="!canResendPaymentLink" class="text-xs text-rose-600">
                            Add a valid donor phone before sending the payment link WhatsApp.
                        </p>
                        <p v-if="!canNotifyPaymentLinkEmail" class="text-xs text-rose-600">
                            Add a valid donor email before sending the payment link email.
                        </p>
                        <p v-if="!canNotifyPaymentLinkSms" class="text-xs text-rose-600">
                            Add a valid donor phone before sending the payment link SMS.
                        </p>
                    </div>
                </section>
            </div>

            <div class="space-y-4 xl:col-span-5">
                <AttributionSourceCard
                    :source="donation.source"
                    empty-message="No UTM or referrer captured for this donation."
                />

                <section class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="text-sm font-semibold text-foreground">Delivery status</h3>
                    <div class="mt-3 space-y-2">
                        <div
                            v-if="showPaymentLink"
                            class="rounded-lg border px-3 py-2"
                            :class="statusTone(delivery.payment_link_whatsapp.status)"
                        >
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Payment link WhatsApp</span>
                                <span>{{ delivery.payment_link_whatsapp.label }}</span>
                            </div>
                            <p v-if="delivery.payment_link_whatsapp.at" class="mt-1 text-xs opacity-80">
                                {{ delivery.payment_link_whatsapp.at }}
                            </p>
                        </div>
                        <div
                            v-if="showPaymentLink"
                            class="rounded-lg border px-3 py-2"
                            :class="statusTone(delivery.payment_link_email?.status)"
                        >
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Payment link email</span>
                                <span>{{ delivery.payment_link_email?.label || '—' }}</span>
                            </div>
                            <p v-if="delivery.payment_link_email?.at" class="mt-1 text-xs opacity-80">
                                {{ delivery.payment_link_email.at }}
                            </p>
                        </div>
                        <div
                            v-if="showPaymentLink"
                            class="rounded-lg border px-3 py-2"
                            :class="statusTone(delivery.payment_link_sms?.status)"
                        >
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Payment link SMS</span>
                                <span>{{ delivery.payment_link_sms?.label || '—' }}</span>
                            </div>
                            <p v-if="delivery.payment_link_sms?.at" class="mt-1 text-xs opacity-80">
                                {{ delivery.payment_link_sms.at }}
                            </p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="statusTone(delivery.email.status)">
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Email receipt</span>
                                <span>{{ delivery.email.label }}</span>
                            </div>
                            <p v-if="delivery.email.at" class="mt-1 text-xs opacity-80">{{ delivery.email.at }}</p>
                            <p v-if="delivery.email.error" class="mt-1 text-xs">{{ delivery.email.error }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="statusTone(delivery.whatsapp.status)">
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>WhatsApp thank-you</span>
                                <span>{{ delivery.whatsapp.label }}</span>
                            </div>
                            <p v-if="delivery.whatsapp.at" class="mt-1 text-xs opacity-80">{{ delivery.whatsapp.at }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="statusTone(delivery.certificate_whatsapp.status)">
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>WhatsApp certificate</span>
                                <span>{{ delivery.certificate_whatsapp.label }}</span>
                            </div>
                            <p v-if="delivery.certificate_whatsapp.at" class="mt-1 text-xs opacity-80">{{ delivery.certificate_whatsapp.at }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="statusTone(delivery.receipt_whatsapp.status)">
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>WhatsApp receipt</span>
                                <span>{{ delivery.receipt_whatsapp.label }}</span>
                            </div>
                            <p v-if="delivery.receipt_whatsapp.at" class="mt-1 text-xs opacity-80">{{ delivery.receipt_whatsapp.at }}</p>
                        </div>
                        <div class="rounded-lg border px-3 py-2" :class="statusTone(delivery.sheet.status)">
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Google Sheet</span>
                                <span>{{ delivery.sheet.label }}</span>
                            </div>
                            <p v-if="delivery.sheet.at" class="mt-1 text-xs opacity-80">{{ delivery.sheet.at }}</p>
                        </div>
                        <div
                            v-if="isFailed || delivery.follow_up_sheet?.status === 'logged'"
                            class="rounded-lg border px-3 py-2"
                            :class="statusTone(delivery.follow_up_sheet?.status)"
                        >
                            <div class="flex items-center justify-between gap-2 text-sm font-medium">
                                <span>Follow-up sheet</span>
                                <span>{{ delivery.follow_up_sheet?.label || '—' }}</span>
                            </div>
                            <p v-if="delivery.follow_up_sheet?.at" class="mt-1 text-xs opacity-80">
                                {{ delivery.follow_up_sheet.at }}
                            </p>
                        </div>
                    </div>

                    <div v-if="canManageReceipts && isPaid" class="mt-3 flex flex-col gap-2 border-t border-border pt-3">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendThankYou || Boolean(queuingAction)"
                            @click="queueDelivery('thank_you', donation.whatsapp_thank_you_url, canResendThankYou)"
                        >
                            {{
                                queuingAction === 'thank_you'
                                    ? 'Queuing…'
                                    : (delivery.whatsapp.status === 'sent' ? 'Resend thank-you WhatsApp' : 'Send thank-you WhatsApp')
                            }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendCertificate || Boolean(queuingAction)"
                            @click="queueDelivery('certificate', donation.whatsapp_certificate_url, canResendCertificate)"
                        >
                            {{
                                queuingAction === 'certificate'
                                    ? 'Queuing…'
                                    : (delivery.certificate_whatsapp.status === 'sent' ? 'Resend certificate WhatsApp' : 'Send certificate WhatsApp')
                            }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendReceiptWhatsApp || Boolean(queuingAction)"
                            @click="queueDelivery('receipt_whatsapp', donation.whatsapp_receipt_url, canResendReceiptWhatsApp)"
                        >
                            {{
                                queuingAction === 'receipt_whatsapp'
                                    ? 'Queuing…'
                                    : (delivery.receipt_whatsapp.status === 'sent' ? 'Resend receipt WhatsApp' : 'Send receipt WhatsApp')
                            }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendSheet || Boolean(queuingAction)"
                            @click="queueDelivery('sheet', donation.sheet_resend_url, canResendSheet)"
                        >
                            {{
                                queuingAction === 'sheet'
                                    ? 'Queuing…'
                                    : (delivery.sheet.status === 'logged' ? 'Re-log to Google Sheet' : 'Log to Google Sheet')
                            }}
                        </button>
                        <p v-if="!canResendThankYou && !canResendCertificate" class="text-xs text-rose-600">
                            Add a valid donor phone before sending WhatsApp.
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            These actions queue jobs. Status above updates after the queue worker runs.
                        </p>
                    </div>

                    <p v-else-if="isFailed" class="mt-3 text-xs text-muted-foreground">
                        Paid delivery actions unlock after this donation is paid. Use the payment recovery link card to retry WhatsApp.
                    </p>
                </section>

                <section v-if="isPaid || donation.receipt_number" class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="text-sm font-semibold text-foreground">Receipt</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Email: {{ donation.receipt_label || donation.receipt_email_label || 'Not sent' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">WhatsApp: {{ delivery.receipt_whatsapp.label }}</p>
                    <div v-if="canManageReceipts" class="mt-3 flex flex-col gap-2">
                        <a :href="donation.receipt_preview_url" target="_blank" class="rounded-lg border border-border px-3 py-2 text-center text-sm">Preview receipt</a>
                        <a :href="donation.receipt_print_url" target="_blank" class="rounded-lg border border-border px-3 py-2 text-center text-sm">Open receipt</a>
                        <button
                            type="button"
                            class="rounded-lg border border-border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="generating"
                            @click="generateReceipt"
                        >
                            {{ generating ? 'Queuing…' : 'Generate receipt' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-foreground px-3 py-2 text-sm text-background disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendEmail || resending"
                            @click="resendReceipt"
                        >
                            {{ resending ? 'Queuing email…' : (delivery.email.status === 'sent' ? 'Resend email' : 'Send email') }}
                        </button>
                        <p v-if="!canResendEmail" class="text-xs text-rose-600">
                            Add a valid donor email before resending the receipt.
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            Resend queues the email. Status above updates after the queue worker sends it.
                        </p>
                        <button
                            v-if="isPaid"
                            type="button"
                            class="rounded-lg bg-foreground px-3 py-2 text-sm text-background disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canResendReceiptWhatsApp || resendingWhatsApp"
                            @click="resendReceiptWhatsApp"
                        >
                            {{
                                resendingWhatsApp
                                    ? 'Queuing WhatsApp…'
                                    : (delivery.receipt_whatsapp.status === 'sent' ? 'Resend receipt WhatsApp' : 'Send receipt WhatsApp')
                            }}
                        </button>
                        <p v-if="isPaid && !canResendReceiptWhatsApp" class="text-xs text-rose-600">
                            Add a valid donor phone before sending the receipt on WhatsApp.
                        </p>
                        <p v-else-if="isPaid" class="text-xs text-muted-foreground">
                            Send queues the receipt PDF on WhatsApp. Status above updates after the queue worker runs.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
