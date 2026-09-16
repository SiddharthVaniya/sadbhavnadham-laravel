<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import TableRowActions from '@/Components/Admin/TableRowActions.vue';

defineProps({
    campaigns: { type: Object, required: true },
    multiCampaignDonors: { type: Array, default: () => [] },
    abilities: {
        type: Object,
        default: () => ({
            can_view: true,
            can_create: false,
            can_edit: false,
            can_delete: false,
            can_copy_links: false,
        }),
    },
});

const columns = [
    { key: 'name', label: 'Name', sortable: false },
    { key: 'slug', label: 'Slug', sortable: false },
    { key: 'cause_title', label: 'Cause', sortable: false },
    { key: 'amount', label: 'Amount', sortable: false },
    { key: 'frequency_label', label: 'Billing', sortable: false },
    { key: 'stats', label: 'Performance', sortable: false },
    { key: 'is_active', label: 'Status', sortable: false },
    { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const toggle = async (campaign, canEdit) => {
    if (! canEdit) {
        return;
    }

    const response = await fetch(`/admin/campaigns/${campaign.id}/toggle-active`, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    });

    if (! response.ok) {
        alert('Unable to update campaign status. Please refresh and try again.');
        return;
    }

    router.reload({ only: ['campaigns', 'multiCampaignDonors'] });
};

const destroy = (id, canDelete) => {
    if (! canDelete) {
        return;
    }

    if (confirm('Delete this campaign?')) {
        router.delete(`/admin/campaigns/${id}`);
    }
};
</script>

<template>
    <Head title="Campaigns" />
    <AdminLayout>
        <template #header>Campaigns</template>
        <PageHeader
            title="Donation campaigns"
            subtitle="Track donations, recurring mandates, and donor activity per campaign."
        >
            <template v-if="abilities.can_create" #actions>
                <Link href="/admin/campaigns/create" class="admin-btn-primary">Add campaign</Link>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="campaigns.data ?? []"
            empty-message="No campaigns yet."
        >
            <template #cell-name="{ row }">
                <div class="font-medium text-foreground">
                    <Link v-if="row.can_view_stats" :href="row.stats_url" class="hover:underline">{{ row.name }}</Link>
                    <span v-else>{{ row.name }}</span>
                </div>
                <a
                    v-if="row.is_ready_for_checkout"
                    :href="row.public_url"
                    class="text-xs text-sky-700 hover:underline"
                    target="_blank"
                    rel="noopener"
                >
                    Open landing page
                </a>
            </template>
            <template #cell-slug="{ row }">
                <code class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">{{ row.slug }}</code>
            </template>
            <template #cell-cause_title="{ row }">
                <span class="text-foreground">{{ row.cause_title }}</span>
            </template>
            <template #cell-amount="{ row }">
                <span>₹{{ Number(row.amount).toLocaleString('en-IN') }}</span>
            </template>
            <template #cell-frequency_label="{ row }">
                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="row.recurring_only ? 'bg-sky-50 text-sky-800' : 'bg-muted text-muted-foreground'"
                >
                    {{ row.frequency_label }}
                </span>
            </template>
            <template #cell-stats="{ row }">
                <div class="space-y-1 text-xs text-muted-foreground">
                    <div><span class="font-medium text-foreground">{{ formatMoney(row.stats.revenue) }}</span> raised</div>
                    <div v-if="row.goal_amount">
                        Goal {{ formatMoney(row.goal_amount) }}
                        <span v-if="row.stats.goal_progress_percent !== null && row.stats.goal_progress_percent !== undefined">
                            · {{ row.stats.goal_progress_percent }}%
                        </span>
                    </div>
                    <div>{{ row.stats.paid_count }} paid · {{ row.stats.active_subscriptions }} recurring live</div>
                    <div>{{ row.stats.page_views }} page views</div>
                </div>
            </template>
            <template #cell-is_active="{ row }">
                <button
                    v-if="row.can_edit"
                    type="button"
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                    @click="toggle(row, row.can_edit)"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </button>
                <span
                    v-else
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                >
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-3">
                    <Link v-if="row.can_view_stats" :href="row.stats_url" class="text-sm font-medium hover:underline">Stats</Link>
                    <TableRowActions
                        v-if="row.can_edit || row.can_delete || row.can_copy_links"
                        :edit-href="row.can_edit ? row.edit_url : ''"
                        :copy-url="row.can_copy_links ? row.share_url : ''"
                        copy-label="Copy campaign link"
                        :show-edit="row.can_edit"
                        :show-delete="row.can_delete"
                        @delete="destroy(row.id, row.can_delete)"
                    />
                </div>
            </template>
            <template #footer>
                <Pagination :links="campaigns.links" :meta="campaigns.meta" />
            </template>
        </DataTable>

        <div v-if="multiCampaignDonors.length" class="mt-8 rounded-xl border border-border bg-card p-6 shadow-none">
            <h3 class="mb-1 text-sm font-semibold">Donors active in multiple campaigns</h3>
            <p class="mb-4 text-xs text-muted-foreground">Donors with more than one live recurring campaign mandate.</p>
            <div class="space-y-4">
                <div
                    v-for="(donor, index) in multiCampaignDonors"
                    :key="`${donor.donor_email}-${index}`"
                    class="rounded-lg border border-border bg-muted/50 p-4"
                >
                    <div class="font-medium text-foreground">{{ donor.donor_name }}</div>
                    <div class="text-sm text-muted-foreground">{{ donor.donor_email }} · {{ donor.donor_phone }}</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            v-for="campaignRow in donor.campaigns"
                            :key="campaignRow.id"
                            :href="campaignRow.stats_url"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-foreground hover:border-border"
                        >
                            <span>{{ campaignRow.name }}</span>
                            <span class="text-muted-foreground">{{ formatMoney(campaignRow.amount) }}/mo</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
