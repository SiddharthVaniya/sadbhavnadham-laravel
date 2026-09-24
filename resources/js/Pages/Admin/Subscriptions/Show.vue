<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import SubscriptionStatusBadge from '@/Components/Admin/SubscriptionStatusBadge.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import AttributionSourceCard from '@/Components/Admin/AttributionSourceCard.vue';

const props = defineProps({
    subscription: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
});

const page = usePage();
const canManageSubscriptions = computed(() => page.props.auth.permissions?.includes('manage subscriptions') ?? false);
const showCancelForm = ref(false);

const cancelForm = useForm({
    cancel_reason: '',
    cancel_at_cycle_end: false,
});

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const submitCancel = () => {
    if (!confirm('Cancel this subscription? Future monthly charges will stop.')) {
        return;
    }

    cancelForm.post(props.subscription.cancel_url, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelForm.value = false;
        },
    });
};

const syncFromRazorpay = () => {
    if (!props.subscription.sync_url) {
        return;
    }

    router.post(props.subscription.sync_url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Subscription · ${subscription.donor.name}`" />
    <AdminLayout>
        <template #header>Subscription details</template>

        <PageHeader :title="subscription.donor.name" :subtitle="subscription.item_title">
            <template #actions>
                <Link href="/admin/subscriptions" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
                <button
                    v-if="canManageSubscriptions && subscription.sync_url"
                    type="button"
                    class="rounded-lg border border-border px-3 py-2 text-sm text-foreground hover:bg-muted"
                    @click="syncFromRazorpay"
                >
                    Sync from Razorpay
                </button>
                <button
                    v-if="canManageSubscriptions && subscription.can_cancel"
                    type="button"
                    class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                    @click="showCancelForm = !showCancelForm"
                >
                    Cancel subscription
                </button>
            </template>
        </PageHeader>

        <div
            v-if="showCancelForm && canManageSubscriptions && subscription.can_cancel"
            class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-5"
        >
            <h3 class="text-sm font-semibold text-rose-900">Cancel monthly subscription</h3>
            <p class="mt-1 text-sm text-rose-800">
                This stops future Razorpay charges for {{ subscription.donor.name }}.
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="submitCancel">
                <div>
                    <label class="admin-label">Reason (optional)</label>
                    <textarea v-model="cancelForm.cancel_reason" class="admin-input" rows="3" placeholder="Why is this subscription being cancelled?" />
                    <p v-if="cancelForm.errors.cancel" class="mt-2 text-sm text-rose-700">{{ cancelForm.errors.cancel }}</p>
                </div>
                <label class="flex items-start gap-2 text-sm text-rose-900">
                    <input v-model="cancelForm.cancel_at_cycle_end" type="checkbox" class="mt-1">
                    <span>Cancel at end of current billing cycle instead of immediately</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800 disabled:opacity-60"
                        :disabled="cancelForm.processing"
                    >
                        {{ cancelForm.processing ? 'Cancelling…' : 'Confirm cancellation' }}
                    </button>
                    <button type="button" class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-800" @click="showCancelForm = false">
                        Keep active
                    </button>
                </div>
            </form>
        </div>

        <div class="mb-6 grid min-w-0 gap-4 lg:grid-cols-3">
            <div class="min-w-0 rounded-xl border border-border bg-card p-4 shadow-none sm:p-5 lg:col-span-2">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 basis-[12rem]">
                        <h2 class="break-words text-lg font-semibold text-foreground">{{ subscription.cause.title }}</h2>
                        <p class="break-words text-sm text-muted-foreground">{{ subscription.package.title }}</p>
                    </div>
                    <SubscriptionStatusBadge :status="subscription.status" :label="subscription.status_label" />
                </div>

                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Monthly amount</dt>
                        <dd class="font-semibold text-foreground">{{ formatMoney(subscription.total_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Frequency</dt>
                        <dd class="font-medium">{{ subscription.frequency_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Billing cycles</dt>
                        <dd class="font-medium">{{ subscription.billing_cycle_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Collected so far</dt>
                        <dd class="font-medium">{{ formatMoney(subscription.totals.collected_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Paid charges</dt>
                        <dd class="font-medium">{{ subscription.totals.paid_cycles }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Next charge</dt>
                        <dd class="font-medium">{{ subscription.next_charge_at || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Started</dt>
                        <dd class="font-medium">{{ subscription.started_at || subscription.created_at }}</dd>
                    </div>
                    <div v-if="subscription.cancelled_at">
                        <dt class="text-muted-foreground">Cancelled</dt>
                        <dd class="font-medium">{{ subscription.cancelled_at }}</dd>
                    </div>
                    <div v-if="subscription.ended_at">
                        <dt class="text-muted-foreground">Ended</dt>
                        <dd class="font-medium">{{ subscription.ended_at }}</dd>
                    </div>
                </dl>

                <div v-if="subscription.cancel_reason" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <strong>Cancellation reason:</strong> {{ subscription.cancel_reason }}
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                <h3 class="text-sm font-semibold text-foreground">Razorpay</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Subscription ID</dt>
                        <dd class="break-all font-mono text-xs">{{ subscription.razorpay_subscription_id || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Plan ID</dt>
                        <dd class="break-all font-mono text-xs">{{ subscription.razorpay_plan_id || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Local UUID</dt>
                        <dd class="break-all font-mono text-xs">{{ subscription.uuid }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="mt-6 grid min-w-0 gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-6 xl:col-span-7">
                <div class="min-w-0 rounded-xl border border-border bg-card p-4 shadow-none sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-foreground">Subscriber details</h3>
                        <Link
                            v-if="subscription.donor.profile_url"
                            :href="subscription.donor.profile_url"
                            class="text-sm font-medium text-sky-700 hover:underline"
                        >
                            View donor profile
                        </Link>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-muted-foreground">Email</dt>
                            <dd class="break-all font-medium">{{ subscription.donor.email }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="font-medium">{{ subscription.donor.phone }}</dd>
                        </div>
                        <div v-if="subscription.donor.date_of_birth"><dt class="text-muted-foreground">Date of birth</dt><dd>{{ subscription.donor.date_of_birth }}</dd></div>
                        <div v-if="subscription.donor.pan_number"><dt class="text-muted-foreground">PAN</dt><dd>{{ subscription.donor.pan_number }}</dd></div>
                    </dl>
                    <p v-if="subscription.donor.address" class="mt-4 break-words text-sm text-muted-foreground">
                        {{ subscription.donor.address }}, {{ subscription.donor.city }}, {{ subscription.donor.state }} {{ subscription.donor.pincode }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span v-if="subscription.donor.consent_indian_citizen" class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">Indian citizen declared</span>
                        <span v-if="subscription.donor.consent_recurring" class="rounded-full bg-sky-50 px-2.5 py-1 text-sky-700">Recurring mandate accepted</span>
                    </div>
                </div>

                <div class="min-w-0 rounded-xl border border-border bg-card p-4 shadow-none sm:p-5">
                    <h3 class="text-sm font-semibold text-foreground">Subscription summary</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="shrink-0 text-muted-foreground">Cause</dt><dd class="min-w-0 break-words text-right font-medium">{{ subscription.cause.title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="shrink-0 text-muted-foreground">Package</dt><dd class="min-w-0 break-words text-right font-medium">{{ subscription.package.title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Unit amount</dt><dd class="font-medium">{{ formatMoney(subscription.unit_amount) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Quantity</dt><dd class="font-medium">{{ subscription.quantity }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Created</dt><dd class="font-medium">{{ subscription.created_at }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="min-w-0 xl:col-span-5">
                <AttributionSourceCard
                    :source="subscription.source"
                    empty-message="No UTM or referrer captured for this subscription."
                />
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-border bg-card p-4 shadow-none sm:p-5">
            <h3 class="mb-4 text-sm font-semibold text-foreground">Billing history</h3>

            <div v-if="orders.length" class="space-y-3 md:hidden">
                <div
                    v-for="order in orders"
                    :key="`m-${order.uuid}`"
                    class="rounded-lg border border-border bg-muted/20 p-3 text-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-medium text-foreground">
                            Cycle {{ order.billing_cycle_number || '—' }}
                        </div>
                        <StatusBadge :status="order.status" />
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-muted-foreground">Date</dt>
                            <dd class="mt-0.5 font-medium">{{ order.paid_at || order.created_at }}</dd>
                        </div>
                        <div class="text-right">
                            <dt class="text-muted-foreground">Amount</dt>
                            <dd class="mt-0.5 font-semibold">{{ formatMoney(order.total_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Receipt</dt>
                            <dd class="mt-0.5 font-medium">{{ order.receipt_number || '—' }}</dd>
                        </div>
                        <div class="min-w-0 text-right">
                            <dt class="text-muted-foreground">Payment ID</dt>
                            <dd class="mt-0.5 break-all font-mono text-[11px]">{{ order.provider_payment_id || '—' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-2">
                        <Link :href="order.detail_url" class="text-sm font-medium text-indigo-700 hover:underline">
                            View donation
                        </Link>
                    </div>
                </div>
            </div>

            <div v-if="orders.length" class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="pb-3 pr-4">Cycle</th>
                            <th class="pb-3 pr-4">Date</th>
                            <th class="pb-3 pr-4">Amount</th>
                            <th class="pb-3 pr-4">Status</th>
                            <th class="pb-3 pr-4">Receipt</th>
                            <th class="pb-3 pr-4">Payment ID</th>
                            <th class="pb-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in orders" :key="order.uuid">
                            <td class="py-3 pr-4 font-medium">{{ order.billing_cycle_number || '—' }}</td>
                            <td class="py-3 pr-4">{{ order.paid_at || order.created_at }}</td>
                            <td class="py-3 pr-4">{{ formatMoney(order.total_amount) }}</td>
                            <td class="py-3 pr-4"><StatusBadge :status="order.status" /></td>
                            <td class="py-3 pr-4">{{ order.receipt_number || '—' }}</td>
                            <td class="py-3 pr-4 font-mono text-xs">{{ order.provider_payment_id || '—' }}</td>
                            <td class="py-3 text-right">
                                <Link :href="order.detail_url" class="font-medium hover:underline">Donation</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                No billing charges recorded yet. Paid monthly cycles will appear here once webhooks create donation orders.
            </p>
        </div>
    </AdminLayout>
</template>
