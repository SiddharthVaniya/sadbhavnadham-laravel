<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaTabs from '@/Components/Marketer/MetaTabs.vue';
import MetaSpendFilters from '@/Components/Admin/MetaSpendFilters.vue';
import MetaSpendAnalytics from '@/Components/Admin/MetaSpendAnalytics.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

defineProps({
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    analytics: { type: Object, required: true },
    lastSyncedAt: { type: String, default: null },
});

const refreshing = ref(false);

const applyFilters = (form) => {
    router.get('/marketer/meta/analytics', { ...form }, {
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
    <Head title="Meta analytics" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta analytics"
            subtitle="Breakdown charts for your matched ads."
        >
            <template #actions>
                <Button type="button" :disabled="refreshing" @click="refresh">
                    {{ refreshing ? 'Refreshing…' : 'Refresh from Meta' }}
                </Button>
            </template>
        </PageHeader>

        <MetaTabs current="analytics" :filters="filters" />

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

        <MetaSpendAnalytics :analytics="analytics" mode="full" />
    </AdminLayout>
</template>
