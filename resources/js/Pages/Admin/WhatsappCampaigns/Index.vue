<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    runs: { type: Object, required: true },
});
</script>

<template>
    <Head title="WhatsApp Campaigns" />
    <AdminLayout>
        <template #header>WA Campaigns</template>
        <PageHeader title="WhatsApp campaigns" subtitle="Filter donors and send approved AiSensy Live API campaigns">
            <template #actions>
                <Link href="/admin/whatsapp-campaigns/create" class="admin-btn-primary">New campaign</Link>
            </template>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Campaign</th>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Audience</th>
                        <th class="px-4 py-3 font-medium text-right">Sent</th>
                        <th class="px-4 py-3 font-medium text-right">Failed</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="run in runs.data" :key="run.uuid">
                        <td class="px-4 py-3">
                            <Link :href="run.show_url" class="font-medium text-foreground hover:underline">{{ run.name }}</Link>
                            <div class="text-xs text-muted-foreground">{{ run.live_campaign_name }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ run.account_name || '—' }}</td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="run.status" />
                        </td>
                        <td class="px-4 py-3 text-right">{{ run.audience_count }}</td>
                        <td class="px-4 py-3 text-right">{{ run.sent_count }}</td>
                        <td class="px-4 py-3 text-right">{{ run.failed_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ run.created_at }}</div>
                            <div class="text-xs">{{ run.created_by || '' }}</div>
                        </td>
                    </tr>
                    <tr v-if="!runs.data?.length">
                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">No campaigns yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination class="mt-4" :links="runs.links || []" :meta="runs.meta" />
    </AdminLayout>
</template>
