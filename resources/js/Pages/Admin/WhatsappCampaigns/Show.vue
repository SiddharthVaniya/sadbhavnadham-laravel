<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const props = defineProps({
    run: { type: Object, required: true },
    failedRecipients: { type: Array, default: () => [] },
});

const page = usePage();

const progressPercent = computed(() => {
    const total = Number(props.run.audience_count || 0);
    if (total <= 0) {
        return 0;
    }
    const done = Number(props.run.sent_count || 0) + Number(props.run.failed_count || 0) + Number(props.run.skipped_count || 0);
    return Math.min(100, Math.round((done / total) * 100));
});

const hasDeliveryStats = computed(() => props.run.delivery_synced_at != null);

const cancel = () => {
    if (!props.run.is_cancellable) return;
    if (!confirm('Cancel remaining pending sends for this campaign?')) return;
    router.post(props.run.cancel_url);
};

const retryFailed = () => {
    if (!props.run.can_retry_failed) return;
    if (!confirm(`Retry ${props.run.failed_count} failed recipient(s)?`)) return;
    router.post(props.run.retry_failed_url);
};

const syncDelivery = () => {
    if (!props.run.can_sync_delivery) return;
    router.post(props.run.sync_delivery_url);
};
</script>

<template>
    <Head :title="run.name" />
    <AdminLayout>
        <template #header>WA Campaigns</template>
        <PageHeader :title="run.name" :subtitle="run.live_campaign_name">
            <template #actions>
                <Link href="/admin/whatsapp-campaigns" class="admin-btn-secondary">All campaigns</Link>
                <button
                    v-if="run.can_sync_delivery"
                    type="button"
                    class="admin-btn-secondary"
                    @click="syncDelivery"
                >
                    Sync AiSensy delivery
                </button>
                <button
                    v-if="run.can_retry_failed"
                    type="button"
                    class="admin-btn-secondary"
                    @click="retryFailed"
                >
                    Retry API failed
                </button>
                <button
                    v-if="run.is_cancellable"
                    type="button"
                    class="admin-btn-secondary"
                    @click="cancel"
                >
                    Cancel
                </button>
            </template>
        </PageHeader>

        <p v-if="page.props.flash?.status" class="mb-4 text-sm text-emerald-700">{{ page.props.flash.status }}</p>

        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong>API accepted</strong> = portal successfully handed the message to AiSensy.
            <strong>Delivery failed</strong> (invalid number, blocked, Meta limit, etc.) shows in AiSensy later —
            click <em>Sync AiSensy delivery</em> to pull those counts here.
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">Status</div>
                <div class="mt-2"><StatusBadge :status="run.status" /></div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">Audience</div>
                <div class="mt-2 text-2xl font-semibold">{{ run.audience_count }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">API accepted</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ run.sent_count }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">API rejected</div>
                <div class="mt-2 text-2xl font-semibold text-rose-700">{{ run.failed_count }}</div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">AiSensy sent</div>
                <div class="mt-2 text-2xl font-semibold">{{ hasDeliveryStats ? run.delivery_sent_count : '—' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">Delivered</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ hasDeliveryStats ? run.delivery_delivered_count : '—' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">Read</div>
                <div class="mt-2 text-2xl font-semibold">{{ hasDeliveryStats ? run.delivery_read_count : '—' }}</div>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-none">
                <div class="text-xs uppercase text-muted-foreground">Delivery failed</div>
                <div class="mt-2 text-2xl font-semibold text-rose-700">{{ hasDeliveryStats ? run.delivery_failed_count : '—' }}</div>
            </div>
        </div>
        <p v-if="run.delivery_synced_at" class="mt-2 text-xs text-muted-foreground">
            Delivery stats last synced {{ run.delivery_synced_at }}
        </p>

        <div class="mt-6 rounded-xl border border-border bg-card p-4 shadow-none">
            <div class="mb-2 flex items-center justify-between text-sm text-muted-foreground">
                <span>API send progress</span>
                <span>{{ progressPercent }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-emerald-600 transition-all" :style="{ width: `${progressPercent}%` }" />
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground shadow-none">
            <div>Account: {{ run.account_name || '—' }}</div>
            <div>Template: {{ run.template_name || '—' }}</div>
            <div v-if="run.media_filename">Media: {{ run.media_filename }}</div>
            <div v-if="run.location">
                Location:
                {{ run.location.name || '—' }}
                ({{ run.location.latitude }}, {{ run.location.longitude }})
            </div>
            <div>Created by: {{ run.created_by || '—' }} · {{ run.created_at }}</div>
            <div v-if="run.started_at">Started: {{ run.started_at }}</div>
            <div v-if="run.finished_at">Finished: {{ run.finished_at }}</div>
            <div v-if="run.skipped_count">Skipped: {{ run.skipped_count }}</div>
            <div v-if="run.last_error" class="mt-2 text-rose-700">{{ run.last_error }}</div>
        </div>

        <div v-if="failedRecipients.length" class="mt-6 overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <div class="border-b border-border px-4 py-3 font-medium">API-rejected recipients (latest 50)</div>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2">Donor</th>
                        <th class="px-4 py-2">Phone</th>
                        <th class="px-4 py-2">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="row in failedRecipients" :key="row.id">
                        <td class="px-4 py-2">{{ row.donor_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.phone }}</td>
                        <td class="px-4 py-2 text-rose-700">{{ row.error }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
