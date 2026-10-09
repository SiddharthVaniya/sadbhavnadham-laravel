<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaInsightsTable from '@/Components/Admin/MetaInsightsTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SyncFromLiveMetaButton from '@/Components/Admin/SyncFromLiveMetaButton.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    rows: { type: Object, required: true },
    unmatchedCount: { type: Number, default: 0 },
    lastSyncedAt: { type: String, default: null },
});

const spendRows = computed(() => props.rows?.data ?? []);

const applyFilters = (form) => {
    router.get('/admin/meta/insights', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Meta ad insights" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Ad insights"
            subtitle="Ad-level spend, impressions, clicks, and rates. Matched spend overwrites Marketers → Today."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="insights"
                    :from="filters.from_date || null"
                    :to="filters.to_date || null"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
            <span v-if="unmatchedCount" class="ml-2 text-amber-700">· {{ unmatchedCount }} unmatched in this filter</span>
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <MetaSpendFilters
                    :filters="filters"
                    :options="filterOptions"
                    show-marketer
                    show-match
                    @apply="applyFilters"
                    @reset="applyFilters"
                />
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Insights detail</CardTitle>
                <CardDescription>Paginated ad-level rows for the current filters.</CardDescription>
            </CardHeader>
            <CardContent>
                <MetaInsightsTable :rows="spendRows" show-marketer :empty-colspan="13" />
                <Pagination v-if="rows?.links" class="mt-4" :links="rows.links" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
