<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaTabs from '@/Components/Admin/MetaTabs.vue';
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

defineProps({
    accounts: { type: Array, default: () => [] },
    lastSyncedAt: { type: String, default: null },
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

const saveAccount = () => {
    accountForm.post('/admin/meta/accounts', {
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
    editForm.put(`/admin/meta/accounts/${editingId.value}`, {
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

    router.delete(`/admin/meta/accounts/${account.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Meta accounts" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta ad accounts"
            subtitle="Store Marketing API credentials for each ad account. Secrets are encrypted and never shown after save."
        >
            <template #actions>
                <SyncFromLiveMetaButton
                    redirect="accounts"
                    label="Sync all from live Meta"
                />
            </template>
        </PageHeader>

        <MetaTabs current="accounts" />

        <p v-if="lastSyncedAt" class="mb-4 text-sm text-muted-foreground">
            Last synced: {{ lastSyncedAt }}
        </p>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Credentials</CardTitle>
                <CardDescription>
                    Token needs ads_read and access to the ad account. Leave secret fields blank when editing to keep current values.
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
                            <TableCell v-if="editingId === account.id" colspan="5" class="bg-muted/20">
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
                                <TableCell class="space-x-2 text-right">
                                    <SyncFromLiveMetaButton
                                        redirect="accounts"
                                        :meta-ad-account-id="account.id"
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
    </AdminLayout>
</template>
