<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaInsightsTable from '@/Components/Admin/MetaInsightsTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    rows: { type: Object, required: true },
    lastSyncedAt: { type: String, default: null },
});

const refreshing = ref(false);
const spendRows = computed(() => props.rows?.data ?? []);

const applyFilters = (form) => {
    router.get('/marketer/meta/ads', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const refresh = () => {
    refreshing.value = true;
    router.post('/marketer/meta/refresh', {}, {
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Your Meta ads" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Your ads"
            subtitle="Ad-level rows matched to your name in Meta ad titles."
        >
            <template #actions>
                <Button type="button" :disabled="refreshing" @click="refresh">
                    {{ refreshing ? 'Refreshing…' : 'Refresh from Meta' }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <MetaSpendFilters
                    :filters="filters"
                    :options="filterOptions"
                    @apply="applyFilters"
                    @reset="applyFilters"
                />
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Insights detail</CardTitle>
                <CardDescription>Only rows attributed to you.</CardDescription>
            </CardHeader>
            <CardContent>
                <MetaInsightsTable :rows="spendRows" :empty-colspan="12" />
                <Pagination v-if="rows?.links" class="mt-4" :links="rows.links" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
