<script setup>
import { computed } from 'vue';
import {
    IndianRupee,
    LayoutGrid,
    Megaphone,
    CalendarDays,
} from '@lucide/vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import MarketerLineChart from '@/Components/Admin/MarketerLineChart.vue';
import TopCampaignsChart from '@/Components/Admin/TopCampaignsChart.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    analytics: { type: Object, required: true },
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const totals = computed(() => props.analytics?.totals ?? {});
const labels = computed(() => props.analytics?.chart_labels ?? []);
const series = computed(() => props.analytics?.chart_series ?? []);
const byAccount = computed(() => props.analytics?.by_account ?? []);
const byCampaign = computed(() => props.analytics?.by_campaign ?? []);
const byAdset = computed(() => props.analytics?.by_adset ?? []);
</script>

<template>
    <div class="mb-4 space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MarketerStatCard label="Total spend" :value="formatMoney(totals.spend)" hint="Filtered Meta Insights spend">
                <template #icon><IndianRupee class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Ads" :value="String(totals.ads || 0)" hint="Distinct ads in range">
                <template #icon><LayoutGrid class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Campaigns" :value="String(totals.campaigns || 0)" hint="Distinct campaigns">
                <template #icon><Megaphone class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Active days" :value="String(totals.days || 0)" hint="Days with spend">
                <template #icon><CalendarDays class="h-4 w-4" /></template>
            </MarketerStatCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Spend over time</CardTitle>
                    <CardDescription>Daily Meta spend for the selected filters</CardDescription>
                </CardHeader>
                <CardContent>
                    <MarketerLineChart
                        :labels="labels"
                        :series="series"
                        empty-message="No spend in this date range yet."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By app / account</CardTitle>
                    <CardDescription>Share of spend by Meta app name</CardDescription>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart
                        :items="byAccount"
                        empty-message="No account spend to chart."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By campaign</CardTitle>
                    <CardDescription>Top campaigns by spend</CardDescription>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart
                        :items="byCampaign"
                        empty-message="No campaign spend to chart."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By ad set</CardTitle>
                    <CardDescription>Top ad sets by spend</CardDescription>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart
                        :items="byAdset"
                        empty-message="No ad set spend to chart."
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
