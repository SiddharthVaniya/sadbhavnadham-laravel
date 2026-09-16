<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Cake,
    HeartHandshake,
    MessageCircle,
    Plus,
    RefreshCw,
    TrendingUp,
    Users,
} from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import DashboardWidgetPagination from '@/Components/Admin/DashboardWidgetPagination.vue';
import DashboardMonthlyChart from '@/Components/Admin/DashboardMonthlyChart.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/Components/ui/tabs';

const props = defineProps({
    stats: { type: Object, required: true },
    monthlyTrend: { type: Array, default: () => [] },
    recentDonations: { type: Object, required: true },
    topDonors: { type: Array, default: () => [] },
    topCauses: { type: Array, default: () => [] },
    staffLeaderboard: { type: Array, default: () => [] },
    dailyPartnerReferrals: { type: Object, default: null },
    monthlyPartnerReferrals: { type: Object, default: null },
    monthFilter: { type: Object, default: () => ({ options: [], selectedKey: null, selectedLabel: '' }) },
    todaysBirthdays: { type: Array, default: () => [] },
    upcomingBirthdays: { type: Object, required: true },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions ?? []);
const branding = computed(() => page.props.branding ?? {});
const userName = computed(() => page.props.auth.user?.name?.split(' ')[0] ?? 'there');

const can = (permission) => permissions.value.includes(permission);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;

const monthOptions = computed(() => props.monthFilter.options ?? []);
const selectedMonth = ref(props.monthFilter.selectedKey ?? '');

const recentDonationsList = computed(() => props.recentDonations.data ?? []);
const upcomingBirthdaysList = computed(() => props.upcomingBirthdays.data ?? []);

const causeTotalAmount = computed(() => props.topCauses.reduce((sum, cause) => sum + Number(cause.amount || 0), 0));

const causeShare = (amount) => {
    if (causeTotalAmount.value <= 0) {
        return 0;
    }

    return Math.round((Number(amount || 0) / causeTotalAmount.value) * 100);
};

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
});

const awaitingNudge = computed(() => Number(props.stats.awaitingNudgeCount || 0));
const todayBirthdayCount = computed(() => props.todaysBirthdays.length);

const changeMonth = (value) => {
    selectedMonth.value = value;
    router.get('/admin', { month: value || undefined }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <template #header>Dashboard</template>

        <div class="mb-8 space-y-2">
            <p class="text-sm text-muted-foreground">
                {{ branding.shortName || branding.adminLabel || 'Sadbhavna' }} · donation office
            </p>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {{ greeting }}, {{ userName }}
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        A clear view of collections, donors who need follow-up, and where support is coming from.
                        Updated {{ stats.updatedAt }}.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can('manage donations')" as-child>
                        <Link href="/admin/donations/create">
                            <Plus />
                            Record donation
                        </Link>
                    </Button>
                    <Button v-if="can('view donors')" as-child variant="outline">
                        <Link href="/admin/donors">
                            <Users />
                            Donors
                        </Link>
                    </Button>
                    <Button v-if="can('manage receipts')" as-child variant="outline">
                        <Link href="/admin/donations/recovery">
                            <RefreshCw />
                            Recovery
                        </Link>
                    </Button>
                    <Button v-if="can('manage whatsapp campaigns')" as-child variant="outline">
                        <Link href="/admin/whatsapp-campaigns">
                            <MessageCircle />
                            Broadcasts
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="awaitingNudge > 0 || todayBirthdayCount > 0"
            class="mb-6 grid gap-3 md:grid-cols-2"
        >
            <Card v-if="awaitingNudge > 0" class="shadow-none">
                <CardContent class="flex items-center justify-between gap-4 py-4">
                    <div>
                        <p class="text-sm font-medium">Incomplete checkouts waiting</p>
                        <p class="text-xs text-muted-foreground">
                            {{ awaitingNudge }} donor{{ awaitingNudge === 1 ? '' : 's' }} can be nudged with a payment link
                        </p>
                    </div>
                    <Button v-if="can('manage receipts')" as-child size="sm">
                        <Link href="/admin/donations/recovery">
                            Open queue
                            <ArrowRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
            <Card v-if="todayBirthdayCount > 0" class="shadow-none">
                <CardContent class="flex items-center justify-between gap-4 py-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-muted p-2">
                            <Cake class="size-4" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">Birthdays today</p>
                            <p class="text-xs text-muted-foreground">
                                {{ todayBirthdayCount }} donor{{ todayBirthdayCount === 1 ? '' : 's' }} — a good day for a warm wish
                            </p>
                        </div>
                    </div>
                    <Button v-if="can('view donors')" as-child size="sm" variant="outline">
                        <Link href="/admin/donors">View donors</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardDescription>Collected to date</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatMoney(stats.totalDonationAmount) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-xs text-muted-foreground">{{ stats.totalDonations }} successful donations · {{ stats.donorCount }} donors</p>
                </CardContent>
            </Card>
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardDescription>Today's collection</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatMoney(stats.todayAmount) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-xs text-muted-foreground">{{ stats.todayDonations }} donation{{ stats.todayDonations === 1 ? '' : 's' }} today</p>
                </CardContent>
            </Card>
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardDescription>This month</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatMoney(stats.thisMonthAmount) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-xs text-muted-foreground">{{ stats.thisMonthDonations }} donation{{ stats.thisMonthDonations === 1 ? '' : 's' }} this month</p>
                </CardContent>
            </Card>
            <Card class="shadow-none">
                <CardHeader class="pb-2">
                    <CardDescription>Average donation</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">{{ formatMoney(stats.averageDonationAmount) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-xs text-muted-foreground">{{ stats.activeCauseCount }} active causes</p>
                </CardContent>
            </Card>
        </div>

        <Card v-if="dailyPartnerReferrals" class="mb-6 shadow-none">
            <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="size-4" />
                        Today's partner referrals
                    </CardTitle>
                    <CardDescription>
                        Paid donations via staff referral links · {{ dailyPartnerReferrals.date_label }}
                    </CardDescription>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ formatMoney(dailyPartnerReferrals.total_revenue) }} collected
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ dailyPartnerReferrals.total_orders }} order{{ dailyPartnerReferrals.total_orders === 1 ? '' : 's' }}
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ dailyPartnerReferrals.active_partners }} active partner{{ dailyPartnerReferrals.active_partners === 1 ? '' : 's' }}
                    </span>
                    <Button v-if="can('view staff referrals')" as-child size="sm" variant="outline">
                        <Link :href="dailyPartnerReferrals.referrals_href">
                            Full report
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Partner</TableHead>
                            <TableHead>Referral code</TableHead>
                            <TableHead>Orders</TableHead>
                            <TableHead class="text-right">Today</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="partner in dailyPartnerReferrals.partners" :key="partner.user_id">
                            <TableCell class="font-medium">{{ partner.name }}</TableCell>
                            <TableCell>
                                <span class="font-mono text-sm text-muted-foreground">{{ partner.code }}</span>
                            </TableCell>
                            <TableCell class="tabular-nums">{{ partner.paid_orders }}</TableCell>
                            <TableCell class="text-right font-medium tabular-nums">
                                {{ formatMoney(partner.revenue) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button as-child size="sm" variant="ghost" class="h-8 gap-1.5 text-blue-600 hover:text-blue-700">
                                    <Link :href="partner.referrals_href">
                                        <TrendingUp class="size-3.5" />
                                        Details
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="! dailyPartnerReferrals.partners.length">
                            <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                No staff members have referral codes yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="monthlyPartnerReferrals" class="mb-6 shadow-none">
            <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="size-4" />
                        This month partner referrals
                    </CardTitle>
                    <CardDescription>
                        Paid donations via staff referral links · {{ monthlyPartnerReferrals.date_label }}
                    </CardDescription>
                </div>
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ formatMoney(monthlyPartnerReferrals.total_revenue) }} collected
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ monthlyPartnerReferrals.total_orders }} order{{ monthlyPartnerReferrals.total_orders === 1 ? '' : 's' }}
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ monthlyPartnerReferrals.active_partners }} active partner{{ monthlyPartnerReferrals.active_partners === 1 ? '' : 's' }}
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ formatMoney(monthlyPartnerReferrals.total_target) }} target
                    </span>
                    <span class="rounded-md border border-border bg-muted/40 px-2.5 py-1 tabular-nums">
                        {{ formatMoney(monthlyPartnerReferrals.total_spend) }} spend
                    </span>
                    <Button v-if="can('view staff referrals')" as-child size="sm" variant="outline">
                        <Link :href="monthlyPartnerReferrals.referrals_href">
                            Full report
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Partner</TableHead>
                            <TableHead>Referral code</TableHead>
                            <TableHead>Orders</TableHead>
                            <TableHead class="text-right">Collected</TableHead>
                            <TableHead class="text-right">Target</TableHead>
                            <TableHead class="text-right">Spend</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="partner in monthlyPartnerReferrals.partners" :key="partner.user_id">
                            <TableCell class="font-medium">{{ partner.name }}</TableCell>
                            <TableCell>
                                <span class="font-mono text-sm text-muted-foreground">{{ partner.code }}</span>
                            </TableCell>
                            <TableCell class="tabular-nums">{{ partner.paid_orders }}</TableCell>
                            <TableCell class="text-right font-medium tabular-nums">
                                {{ formatMoney(partner.revenue) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums text-muted-foreground">
                                {{ partner.target_amount == null ? '—' : formatMoney(partner.target_amount) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums text-muted-foreground">
                                {{ formatMoney(partner.spend_amount) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button as-child size="sm" variant="ghost" class="h-8 gap-1.5 text-blue-600 hover:text-blue-700">
                                    <Link :href="partner.referrals_href">
                                        <TrendingUp class="size-3.5" />
                                        Details
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="! monthlyPartnerReferrals.partners.length">
                            <TableCell colspan="7" class="py-8 text-center text-muted-foreground">
                                No staff members have referral codes yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div class="mb-6 grid gap-4 xl:grid-cols-5">
            <Card class="shadow-none xl:col-span-3">
                <CardHeader>
                    <CardTitle>Collection trend</CardTitle>
                    <CardDescription>Paid donations over the last 12 months</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardMonthlyChart :months="monthlyTrend" />
                </CardContent>
            </Card>

            <Card class="shadow-none xl:col-span-2">
                <CardHeader class="gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>Cause wise collections</CardTitle>
                        <CardDescription>{{ monthFilter.selectedLabel }}</CardDescription>
                    </div>
                    <Select :model-value="selectedMonth" @update:model-value="changeMonth">
                        <SelectTrigger class="w-full sm:w-[160px]">
                            <SelectValue placeholder="Month" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in monthOptions" :key="opt.key" :value="opt.key">
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="(cause, index) in topCauses" :key="index" class="space-y-2">
                        <div class="flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ cause.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ cause.donations }} donations</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-medium tabular-nums">{{ formatMoney(cause.amount) }}</p>
                                <p class="text-xs text-muted-foreground">{{ causeShare(cause.amount) }}%</p>
                            </div>
                        </div>
                        <Progress :model-value="causeShare(cause.amount)" class="h-1.5" />
                    </div>
                    <p v-if="! topCauses.length" class="py-6 text-center text-sm text-muted-foreground">
                        No cause collections in this month yet.
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="mb-6 grid gap-4 xl:grid-cols-5">
            <Card id="recent-donations" class="shadow-none xl:col-span-3">
                <CardHeader class="flex-row items-center justify-between gap-3 space-y-0">
                    <div>
                        <CardTitle>Recent donations</CardTitle>
                        <CardDescription>Latest donations received in the portal</CardDescription>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/admin/donations">
                            View all
                            <ArrowRight />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-3 md:hidden">
                        <div
                            v-for="donation in recentDonationsList"
                            :key="donation.id"
                            class="rounded-lg border border-border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <Link :href="donation.show_url" class="font-medium hover:underline">{{ donation.donor_name }}</Link>
                                    <div class="truncate text-xs text-muted-foreground">{{ donation.cause }}</div>
                                </div>
                                <StatusBadge :status="donation.status" />
                            </div>
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="font-medium tabular-nums">{{ formatMoney(donation.total_amount) }}</span>
                                <span class="text-xs text-muted-foreground">{{ donation.created_date }}</span>
                            </div>
                        </div>
                        <p v-if="! recentDonationsList.length" class="py-8 text-center text-sm text-muted-foreground">No donations yet.</p>
                    </div>

                    <div class="hidden md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Donor</TableHead>
                                    <TableHead>Cause</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>When</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="donation in recentDonationsList" :key="donation.id">
                                    <TableCell>
                                        <Link :href="donation.show_url" class="font-medium hover:underline">{{ donation.donor_name }}</Link>
                                        <div class="text-xs text-muted-foreground">{{ donation.donor_email }}</div>
                                    </TableCell>
                                    <TableCell>{{ donation.cause }}</TableCell>
                                    <TableCell class="font-medium tabular-nums">{{ formatMoney(donation.total_amount) }}</TableCell>
                                    <TableCell class="text-muted-foreground">
                                        <div>{{ donation.created_date }}</div>
                                        <div class="text-xs">{{ donation.created_time }}</div>
                                    </TableCell>
                                    <TableCell><StatusBadge :status="donation.status" /></TableCell>
                                </TableRow>
                                <TableRow v-if="! recentDonationsList.length">
                                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">No donations yet.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <DashboardWidgetPagination
                        :links="recentDonations.links ?? []"
                        :meta="recentDonations.meta"
                    />
                </CardContent>
            </Card>

            <Card class="shadow-none xl:col-span-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <HeartHandshake class="size-4" />
                        Donor care
                    </CardTitle>
                    <CardDescription>People to remember this week</CardDescription>
                </CardHeader>
                <CardContent>
                    <Tabs default-value="today">
                        <TabsList class="mb-4 grid w-full grid-cols-2">
                            <TabsTrigger value="today">
                                Today
                                <Badge v-if="todayBirthdayCount" variant="secondary" class="ml-1">{{ todayBirthdayCount }}</Badge>
                            </TabsTrigger>
                            <TabsTrigger value="upcoming">Next 7 days</TabsTrigger>
                        </TabsList>

                        <TabsContent value="today" class="space-y-2">
                            <div
                                v-for="(donor, index) in todaysBirthdays"
                                :key="index"
                                class="rounded-lg border border-border px-3 py-2.5 text-sm"
                            >
                                <div class="flex justify-between gap-2">
                                    <Link :href="donor.show_url" class="font-medium hover:underline">{{ donor.donor_name }}</Link>
                                    <Badge variant="secondary">{{ donor.age }} yrs</Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">{{ donor.donor_email || 'No email' }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ donor.orders }} donations · {{ formatMoney(donor.lifetime_amount) }} lifetime
                                </p>
                            </div>
                            <p v-if="! todaysBirthdays.length" class="py-8 text-center text-sm text-muted-foreground">
                                No birthdays today.
                            </p>
                        </TabsContent>

                        <TabsContent value="upcoming" class="space-y-2">
                            <div
                                v-for="(donor, index) in upcomingBirthdaysList"
                                :key="index"
                                class="rounded-lg border border-border px-3 py-2.5 text-sm"
                            >
                                <div class="flex justify-between gap-2">
                                    <Link :href="donor.show_url" class="min-w-0 font-medium hover:underline">{{ donor.donor_name }}</Link>
                                    <Badge variant="outline">In {{ donor.days_until_birthday }}d</Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ donor.next_birthday_label }} · {{ donor.donor_email || 'No email' }}
                                </p>
                            </div>
                            <p v-if="! upcomingBirthdaysList.length" class="py-8 text-center text-sm text-muted-foreground">
                                No upcoming birthdays.
                            </p>
                            <DashboardWidgetPagination
                                class="mt-3"
                                :links="upcomingBirthdays.links ?? []"
                                :meta="upcomingBirthdays.meta"
                            />
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="shadow-none">
                <CardHeader>
                    <CardTitle>Top donors</CardTitle>
                    <CardDescription>Highest lifetime support</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-3">
                        <li
                            v-for="(donor, index) in topDonors"
                            :key="index"
                            class="flex items-start justify-between gap-3 rounded-lg border border-border px-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="tabular-nums">{{ index + 1 }}</Badge>
                                    <p class="truncate font-medium">{{ donor.donor_name }}</p>
                                </div>
                                <p class="mt-1 truncate text-xs text-muted-foreground">{{ donor.donor_email }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ donor.orders }} paid donation{{ donor.orders === 1 ? '' : 's' }}</p>
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums text-emerald-700">{{ formatMoney(donor.amount) }}</p>
                        </li>
                        <li v-if="! topDonors.length" class="py-8 text-center text-sm text-muted-foreground">No donor analytics yet.</li>
                    </ul>
                </CardContent>
            </Card>

            <Card class="shadow-none">
                <CardHeader>
                    <CardTitle>Team contribution</CardTitle>
                    <CardDescription>Paid by staff tag (utm_content) · {{ monthFilter.selectedLabel }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Staff / tag</TableHead>
                                <TableHead>Orders</TableHead>
                                <TableHead class="text-right">Collected</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in staffLeaderboard" :key="row.content">
                                <TableCell class="font-medium">{{ row.content }}</TableCell>
                                <TableCell>{{ row.paid_orders }}</TableCell>
                                <TableCell class="text-right font-medium tabular-nums">{{ formatMoney(row.revenue) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="! staffLeaderboard.length">
                                <TableCell colspan="3" class="py-8 text-center text-muted-foreground">
                                    No staff attribution for this month yet. Add utm_content on donation links.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
