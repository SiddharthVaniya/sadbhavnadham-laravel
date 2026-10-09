<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaSpendAnalytics from '@/Components/Admin/MetaSpendAnalytics.vue';
import SyncFromLiveMetaButton from '@/Components/Admin/SyncFromLiveMetaButton.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    analytics: { type: Object, required: true },
    lastSyncedAt: { type: String, default: null },
});

const applyFilters = (form) => {
    router.get('/admin/meta', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Meta overview" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta overview"
            subtitle="KPIs and daily spend / engagement trends for the filtered range."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="overview"
                    :from="filters.from_date || null"
                    :to="filters.to_date || null"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
                <CardDescription>
                    Filters apply across Overview, Analytics, and Ad insights tabs.
                </CardDescription>
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

        <MetaSpendAnalytics :analytics="analytics" mode="overview" />
    </AdminLayout>
</template>
