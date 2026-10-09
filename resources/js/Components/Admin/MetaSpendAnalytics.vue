<script setup>
import { computed } from 'vue';
import {
    IndianRupee,
    LayoutGrid,
    Megaphone,
    CalendarDays,
    Smartphone,
    Eye,
    MousePointerClick,
    Users,
    Percent,
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
    mode: { type: String, default: 'full' },
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const formatInt = (value) => Number(value || 0).toLocaleString('en-IN');

const formatPercent = (value) =>
    value == null ? '—' : `${Number(value).toFixed(2)}%`;

const totals = computed(() => props.analytics?.totals ?? {});
const trendLabels = computed(() => props.analytics?.chart_labels ?? []);
const trendSeries = computed(() => props.analytics?.chart_series ?? []);
const engagementSeries = computed(() => props.analytics?.chart_engagement_series ?? []);
const byAccount = computed(() => props.analytics?.by_account ?? []);
const byAppId = computed(() => props.analytics?.by_app_id ?? []);
const byCampaign = computed(() => props.analytics?.by_campaign ?? []);
const byAdset = computed(() => props.analytics?.by_adset ?? []);
const byTheme = computed(() => props.analytics?.by_theme ?? []);
const byCause = computed(() => props.analytics?.by_cause ?? []);
const byMarketer = computed(() => props.analytics?.by_marketer ?? []);
const byAd = computed(() => props.analytics?.by_ad ?? []);
const byCampaignImpressions = computed(() => props.analytics?.by_campaign_impressions ?? []);
const byAdImpressions = computed(() => props.analytics?.by_ad_impressions ?? []);
</script>

<template>
    <div class="mb-4 space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <MarketerStatCard label="Total spend" :value="formatMoney(totals.spend)" hint="Filtered Meta spend">
                <template #icon><IndianRupee class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Impressions" :value="formatInt(totals.impressions)" hint="Ad impressions in range">
                <template #icon><Eye class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Clicks" :value="formatInt(totals.clicks)" hint="All clicks (Insights)">
                <template #icon><MousePointerClick class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Reach" :value="formatInt(totals.reach)" hint="Sum of daily reach (ad-level)">
                <template #icon><Users class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="CTR" :value="formatPercent(totals.ctr)" hint="Clicks ÷ impressions">
                <template #icon><Percent class="h-4 w-4" /></template>
            </MarketerStatCard>
        </div>

        <div v-if="mode === 'full'" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            <MarketerStatCard label="CPC" :value="totals.cpc != null ? formatMoney(totals.cpc) : '—'" hint="Spend ÷ clicks">
                <template #icon><IndianRupee class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="CPM" :value="totals.cpm != null ? formatMoney(totals.cpm) : '—'" hint="Cost per 1k impressions">
                <template #icon><IndianRupee class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Avg / day" :value="formatMoney(totals.avg_daily_spend)" hint="Average daily spend">
                <template #icon><CalendarDays class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Ads" :value="String(totals.ads || 0)" hint="Distinct ads">
                <template #icon><LayoutGrid class="h-4 w-4" /></template>
            </MarketerStatCard>
            <MarketerStatCard label="Campaigns" :value="String(totals.campaigns || 0)" hint="Distinct campaigns">
                <template #icon><Megaphone class="h-4 w-4" /></template>
            </MarketerStatCard>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Spend trend</CardTitle>
                    <CardDescription>Daily spend plus top app accounts.</CardDescription>
                </CardHeader>
                <CardContent>
                    <MarketerLineChart
                        :labels="trendLabels"
                        :series="trendSeries"
                        empty-message="No spend in this date range yet. Sync from live Meta or widen filters."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Impressions &amp; clicks</CardTitle>
                    <CardDescription>Daily engagement totals for the filtered range.</CardDescription>
                </CardHeader>
                <CardContent>
                    <MarketerLineChart
                        :labels="trendLabels"
                        :series="engagementSeries"
                        empty-message="No impression data yet. Run Sync from live Meta after DB columns are added."
                    />
                </CardContent>
            </Card>
        </div>

        <div v-if="mode === 'full'" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
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
                    <CardTitle class="text-base">Impressions by app</CardTitle>
                    <CardDescription>Share of impressions</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaDonutChart
                        :items="byAppId"
                        value-key="impressions"
                        value-format="number"
                        empty-message="No impressions yet."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By app account name</CardTitle>
                    <CardDescription>Credential label (spend)</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaDonutChart :items="byAccount" empty-message="No account spend yet." />
                </CardContent>
            </Card>

            <Card v-if="showMarketerCharts" class="shadow-none lg:col-span-2 xl:col-span-1">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By marketer (spend)</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byMarketer" empty-message="No matched marketer spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top campaigns (spend)</CardTitle>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart :items="byCampaign" empty-message="No campaign spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top campaigns (impressions)</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaBarChart
                        :items="byCampaignImpressions"
                        value-key="count"
                        value-format="number"
                        empty-message="No campaign impressions."
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top ad sets (spend)</CardTitle>
                </CardHeader>
                <CardContent>
                    <TopCampaignsChart :items="byAdset" empty-message="No ad set spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By theme (spend)</CardTitle>
                    <CardDescription>Pipe segment from ad name</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byTheme" empty-message="No theme tags in ad names." />
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">By cause (spend)</CardTitle>
                    <CardDescription>Pipe segment from ad name</CardDescription>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byCause" empty-message="No cause tags in ad names." />
                </CardContent>
            </Card>

            <Card class="shadow-none lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top ads by spend</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaBarChart :items="byAd" empty-message="No ad-level spend." />
                </CardContent>
            </Card>

            <Card class="shadow-none lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top ads by impressions</CardTitle>
                </CardHeader>
                <CardContent>
                    <MetaBarChart
                        :items="byAdImpressions"
                        value-key="count"
                        value-format="number"
                        empty-message="No ad-level impressions."
                    />
                </CardContent>
            </Card>

            <Card v-if="showMarketerCharts" class="shadow-none">
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
