<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MarketerBudgetTabs from '@/Components/Admin/MarketerBudgetTabs.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SyncFromLiveMetaButton from '@/Components/Admin/SyncFromLiveMetaButton.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    filters: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    accountOptions: { type: Array, default: () => [] },
    marketers: { type: Array, default: () => [] },
    rows: { type: Object, required: true },
    unmatchedCount: { type: Number, default: 0 },
    lastSyncedAt: { type: String, default: null },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ?? '',
    meta_ad_account_id: props.filters.meta_ad_account_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    campaign: props.filters.campaign ?? '',
    adset: props.filters.adset ?? '',
    match: props.filters.match ?? 'all',
    cause: props.filters.cause ?? '',
});

const accountForm = useForm({
    label: '',
    app_id: '',
    app_secret: '',
    access_token: '',
    ad_account_id: '',
    is_active: true,
});

const editingId = ref(null);
const editForm = useForm({
    label: '',
    app_id: '',
    app_secret: '',
    access_token: '',
    ad_account_id: '',
    is_active: true,
});

const formatMoney = (amount) =>
    `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

const applyFilters = () => {
    router.get('/admin/marketers/meta', { ...filterForm }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.q = '';
    filterForm.user_id = '';
    filterForm.meta_ad_account_id = '';
    filterForm.from_date = '';
    filterForm.to_date = '';
    filterForm.campaign = '';
    filterForm.adset = '';
    filterForm.match = 'all';
    filterForm.cause = '';
    applyFilters();
};

const saveAccount = () => {
    accountForm.post('/admin/marketers/meta/accounts', {
        preserveScroll: true,
        onSuccess: () => accountForm.reset(),
    });
};

const startEdit = (account) => {
    editingId.value = account.id;
    editForm.label = account.label;
    editForm.app_id = account.app_id;
    editForm.app_secret = '';
    editForm.access_token = '';
    editForm.ad_account_id = account.ad_account_id;
    editForm.is_active = account.is_active;
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const saveEdit = () => {
    editForm.put(`/admin/marketers/meta/accounts/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
};

const removeAccount = (account) => {
    if (! window.confirm(`Remove Meta account “${account.label}”?`)) {
        return;
    }

    router.delete(`/admin/marketers/meta/accounts/${account.id}`, { preserveScroll: true });
};

const spendRows = computed(() => props.rows?.data ?? []);
</script>

<template>
    <Head title="Meta ads spend" />
    <AdminLayout>
        <template #header>Marketers</template>

        <PageHeader
            title="Meta ads"
            subtitle="Credentials, live Insights sync, and smart filters on ad names like Marketer | Date | Brand | Theme | Cause."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="meta"
                    :from="filterForm.from_date || null"
                    :to="filterForm.to_date || null"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <MarketerBudgetTabs current="meta" />

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
            <span v-if="unmatchedCount" class="ml-2 text-amber-700">· {{ unmatchedCount }} unmatched in this filter</span>
        </p>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Meta ad accounts</CardTitle>
                <CardDescription>
                    Secrets stay encrypted and are never shown after save. Leave secret fields blank when editing to keep the current values.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="saveAccount">
                    <Input v-model="accountForm.label" placeholder="Label" required />
                    <Input v-model="accountForm.app_id" placeholder="META_APP_ID" required />
                    <Input v-model="accountForm.app_secret" type="password" placeholder="META_APP_SECRET" required autocomplete="off" />
                    <Input v-model="accountForm.access_token" type="password" placeholder="META_ACCESS_TOKEN" required autocomplete="off" />
                    <Input v-model="accountForm.ad_account_id" placeholder="Ad account id (act_… or digits)" required />
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="accountForm.is_active" type="checkbox" class="rounded border" />
                        Active
                    </label>
                    <div class="md:col-span-2 xl:col-span-3">
                        <Button type="submit" :disabled="accountForm.processing">
                            {{ accountForm.processing ? 'Saving…' : 'Add account' }}
                        </Button>
                    </div>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Label</TableHead>
                            <TableHead>Ad account</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Last sync</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="account in accounts" :key="account.id">
                            <TableCell colspan="5" v-if="editingId === account.id" class="bg-muted/20">
                                <form class="grid gap-2 md:grid-cols-3" @submit.prevent="saveEdit">
                                    <Input v-model="editForm.label" required />
                                    <Input v-model="editForm.app_id" required />
                                    <Input v-model="editForm.ad_account_id" required />
                                    <Input v-model="editForm.app_secret" type="password" placeholder="App secret (leave blank to keep)" autocomplete="off" />
                                    <Input v-model="editForm.access_token" type="password" placeholder="Access token (leave blank to keep)" autocomplete="off" />
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="editForm.is_active" type="checkbox" class="rounded border" />
                                        Active
                                    </label>
                                    <div class="flex gap-2 md:col-span-3">
                                        <Button type="submit" size="sm" :disabled="editForm.processing">Save</Button>
                                        <Button type="button" size="sm" variant="outline" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </form>
                            </TableCell>
                            <template v-else>
                                <TableCell>
                                    <p class="font-medium">{{ account.label }}</p>
                                    <p class="text-xs text-muted-foreground">App {{ account.app_id }} · secrets {{ account.has_access_token ? 'saved' : 'missing' }}</p>
                                </TableCell>
                                <TableCell class="font-mono text-sm">{{ account.ad_account_id }}</TableCell>
                                <TableCell>
                                    <span :class="account.is_active ? 'text-emerald-700' : 'text-muted-foreground'">
                                        {{ account.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <p v-if="account.last_sync_status === 'error'" class="text-xs text-destructive">{{ account.last_sync_error }}</p>
                                </TableCell>
                                <TableCell class="text-sm tabular-nums">
                                    {{ account.last_synced_at || '—' }}
                                    <span v-if="account.last_sync_status" class="text-muted-foreground"> ({{ account.last_sync_status }})</span>
                                </TableCell>
                                <TableCell class="text-right space-x-2">
                                    <SyncFromLiveMetaButton
                                        redirect="meta"
                                        :meta-ad-account-id="account.id"
                                        :from="filterForm.from_date || null"
                                        :to="filterForm.to_date || null"
                                        label="Sync"
                                        variant="outline"
                                        size="sm"
                                    />
                                    <Button type="button" size="sm" variant="outline" @click="startEdit(account)">Edit</Button>
                                    <Button type="button" size="sm" variant="outline" @click="removeAccount(account)">Remove</Button>
                                </TableCell>
                            </template>
                        </TableRow>
                        <TableRow v-if="! accounts.length">
                            <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                No Meta accounts yet. Add one above (needs ads_read + ad account access).
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card class="mb-4 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Smart filters</CardTitle>
                <CardDescription>
                    Search matches marketer names inside pipe-separated ad/campaign strings.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-3 xl:grid-cols-4" @submit.prevent="applyFilters">
                    <Input v-model="filterForm.q" placeholder="Search ad / campaign / ad set" />
                    <select v-model="filterForm.user_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">All marketers</option>
                        <option v-for="m in marketers" :key="m.id" :value="String(m.id)">{{ m.name }} ({{ m.code }})</option>
                    </select>
                    <select v-model="filterForm.meta_ad_account_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">All Meta accounts</option>
                        <option v-for="a in accountOptions" :key="a.id" :value="String(a.id)">{{ a.label }}</option>
                    </select>
                    <Input v-model="filterForm.from_date" type="date" />
                    <Input v-model="filterForm.to_date" type="date" />
                    <Input v-model="filterForm.campaign" placeholder="Campaign contains" />
                    <Input v-model="filterForm.adset" placeholder="Ad set contains" />
                    <Input v-model="filterForm.cause" placeholder="Cause / theme keyword" />
                    <select v-model="filterForm.match" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="all">Matched + unmatched</option>
                        <option value="matched">Matched only</option>
                        <option value="unmatched">Unmatched only</option>
                    </select>
                    <div class="flex gap-2 md:col-span-2">
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Insights spend</CardTitle>
                <CardDescription>Daily ad-level rows from Meta. Matched spend overwrites Today for that marketer.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Marketer</TableHead>
                            <TableHead>Ad</TableHead>
                            <TableHead>Campaign</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead class="text-right">Spend</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in spendRows" :key="row.id">
                            <TableCell class="tabular-nums whitespace-nowrap">{{ row.spend_date }}</TableCell>
                            <TableCell>
                                <template v-if="row.marketer_name">
                                    <p class="font-medium">{{ row.marketer_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.marketer_code }}</p>
                                </template>
                                <span v-else class="text-amber-700">Unmatched</span>
                            </TableCell>
                            <TableCell>
                                <p class="max-w-xs truncate font-medium" :title="row.ad_name">{{ row.ad_name || '—' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="row.pipe?.theme">{{ row.pipe.theme }}</span>
                                    <span v-if="row.pipe?.cause"> · {{ row.pipe.cause }}</span>
                                </p>
                            </TableCell>
                            <TableCell class="max-w-[180px] truncate text-sm" :title="row.campaign_name">{{ row.campaign_name || '—' }}</TableCell>
                            <TableCell class="text-sm">{{ row.account_label }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatMoney(row.spend_amount) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="! spendRows.length">
                            <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                No Insights rows for this filter. Sync from live Meta or widen the date range.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination v-if="rows?.links" class="mt-4" :links="rows.links" />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
