<script setup>
import { computed } from 'vue';
import {
    IndianRupee,
    LayoutGrid,
    Megaphone,
    CalendarDays,
    Smartphone,
} from '@lucide/vue';
import MarketerStatCard from '@/Components/Admin/MarketerStatCard.vue';
import MarketerLineChart from '@/Components/Admin/MarketerLineChart.vue';
import TopCampaignsChart from '@/Components/Admin/TopCampaignsChart.vue';
import MetaBarChart from '@/Components/Admin/MetaBarChart.vue';
import MetaDonutChart from '@/Components/Admin/MetaDonutChart.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    analytics: { type: Object, required: true },
    showMarketerCharts: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const totals = computed(() => props.analytics?.totals ?? {});
const trendLabels = computed(() => props.analytics?.chart_labels ?? []);
const trendSeries = computed(() => props.analytics?.chart_series ?? []);
const byAccount = computed(() => props.analytics?.by_account ?? []);
const byAppId = computed(() => props.analytics?.by_app_id ?? []);
const byCampaign = computed(() => props.analytics?.by_campaign ?? []);
const byAdset = computed(() => props.analytics?.by_adset ?? []);
const byTheme = computed(() => props.analytics?.by_theme ?? []);
const byCause = computed(() => props.analytics?.by_cause ?? []);
const byMarketer = computed(() => props.analytics?.by_marketer ?? []);
const byAd = computed(() => props.analytics?.by_ad ?? []);
</script>

<template>
    <div class="mb-4 space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <MarketerStatCard label="Total spend" :value="formatMoney(totals.spend)" hint="Filtered Meta Insights spend">
                <template #icon><IndianRupee class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Avg / day" :value="formatMoney(totals.avg_daily_spend)" hint="Average daily spend in range">
                <template #icon><CalendarDays class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Ads" :value="String(totals.ads || 0)" hint="Distinct ads">
                <template #icon><LayoutGrid class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Campaigns" :value="String(totals.campaigns || 0)" hint="Distinct campaigns">
                <template #icon><Megaphone class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="App accounts" :value="String(totals.accounts || 0)" hint="Meta ad accounts with spend">
                <template #icon><Smartphone class="h-4 w-4" /></template>
            </MarketerStatCard>
        </div>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Spend trend</CardTitle>
                <CardDescription>
                    Daily total plus top app accounts (hover lines to compare).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <MarketerLineChart
                    :labels="trendLabels"
                    :series="trendSeries"
                    empty-message="No spend in this date range yet. Sync from live Meta or widen filters."
                />
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Spend share by app</CardTitle>
                    <CardDescription>Donut by Meta App ID</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaDonutChart :items="byAppId" empty-message="No app spend yet." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By app account name</CardTitle>
                    <CardDescription>Credential label breakdown</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaDonutChart :items="byAccount" empty-message="No account spend yet." />
                </CardContent>
            </Card>

            <Card v-if="showMarketerCharts" class="shadow-none lg:col-span-2 xl:col-span-1">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By marketer</CardTitle>
                    <CardDescription>Matched spend per marketer</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byMarketer" empty-message="No matched marketer spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top campaigns</CardTitle>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart :items="byCampaign" empty-message="No campaign spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top ad sets</CardTitle>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart :items="byAdset" empty-message="No ad set spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By theme</CardTitle>
                    <CardDescription>Pipe segment from ad name</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byTheme" empty-message="No theme tags in ad names." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By cause</CardTitle>
                    <CardDescription>Pipe segment from ad name</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byCause" empty-message="No cause tags in ad names." />
                </CardContent>
            </Card>

            <Card v-if="! compact" class="shadow-none lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top ads by spend</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byAd" empty-message="No ad-level spend." />
                </CardContent>
            </Card>

            <Card v-if="showMarketerCharts && ! compact" class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Marketer share</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaDonutChart :items="byMarketer" empty-message="No marketer breakdown." />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
