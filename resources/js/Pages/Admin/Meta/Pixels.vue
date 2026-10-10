<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import MetaCapiEventFilters from '@/Components/Admin/MetaCapiEventFilters.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
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
    envPixels: { type: Array, default: () => [] },
    allowDatabasePixels: { type: Boolean, default: false },
    capiEnabled: { type: Boolean, default: true },
    pixels: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    eventLogs: { type: Object, default: () => ({ data: [] }) },
});

const applyEventFilters = (form) => {
    router.get('/admin/meta/pixels', { ...form }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const pixelForm = useForm({
    label: '',
    pixel_id: '',
    access_token: '',
    is_active: true,
    send_purchase: true,
    send_initiate_checkout: true,
    test_event_code: '',
});

const editingId = ref(null);
const editForm = useForm({
    label: '',
    pixel_id: '',
    access_token: '',
    is_active: true,
    send_purchase: true,
    send_initiate_checkout: true,
    test_event_code: '',
});

const savePixel = () => {
    pixelForm.post('/admin/meta/pixels', {
        preserveScroll: true,
        onSuccess: () => {
            pixelForm.reset();
            pixelForm.is_active = true;
            pixelForm.send_purchase = true;
            pixelForm.send_initiate_checkout = true;
            pixelForm.clearErrors();
        },
    });
};

const startEdit = (pixel) => {
    editingId.value = pixel.id;
    editForm.clearErrors();
    editForm.label = pixel.label;
    editForm.pixel_id = pixel.pixel_id;
    editForm.access_token = '';
    editForm.is_active = pixel.is_active;
    editForm.send_purchase = pixel.send_purchase;
    editForm.send_initiate_checkout = pixel.send_initiate_checkout;
    editForm.test_event_code = pixel.test_event_code || '';
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = () => {
    editForm.put(`/admin/meta/pixels/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
            editForm.clearErrors();
        },
    });
};

const removePixel = (pixel) => {
    if (! window.confirm(`Remove pixel “${pixel.label}”?`)) {
        return;
    }

    router.delete(`/admin/meta/pixels/${pixel.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Meta pixels (CAPI)" />
    <AdminLayout>
        <template #header>Meta</template>

        <PageHeader
            title="Meta pixels (Conversions API)"
            subtitle="Credentials are read from .env (META_CAPI_*). After changing .env, run php artisan config:cache. Browser pixels on the public site are unchanged."
        />

        <p v-if="!capiEnabled" class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            CAPI is disabled (META_CAPI_ENABLED=false).
        </p>

        <Card class="mb-6 shadow-none border-primary/20">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Environment pixels (.env)</CardTitle>
                <CardDescription>
                    Primary configuration. Tokens are never stored in the database or shown in the admin UI.
                </CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Slot</TableHead>
                            <TableHead>Label</TableHead>
                            <TableHead>Pixel ID</TableHead>
                            <TableHead>Events</TableHead>
                            <TableHead>Token</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in envPixels" :key="row.pixel_id">
                            <TableCell>{{ row.slot }}</TableCell>
                            <TableCell class="font-medium">{{ row.label }}</TableCell>
                            <TableCell class="font-mono text-sm">{{ row.pixel_id }}</TableCell>
                            <TableCell class="text-sm">
                                <span v-if="row.send_purchase">Purchase</span>
                                <span v-if="row.send_purchase && row.send_initiate_checkout"> · </span>
                                <span v-if="row.send_initiate_checkout">InitiateCheckout</span>
                            </TableCell>
                            <TableCell>
                                <span v-if="row.has_access_token" class="text-emerald-700">Set in .env</span>
                                <span v-else class="text-rose-700">Missing token</span>
                            </TableCell>
                            <TableCell>
                                <span v-if="row.is_active" class="text-emerald-700">Active</span>
                                <span v-else class="text-muted-foreground">Off</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!envPixels.length">
                            <TableCell colspan="6" class="text-center text-muted-foreground">
                                Set META_CAPI_PIXEL_1_ID and META_CAPI_PIXEL_1_TOKEN in .env (see .env.example).
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="allowDatabasePixels" class="mb-6 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Add pixel (database)</CardTitle>
                <CardDescription>
                    Optional extra pixels when META_CAPI_ALLOW_DATABASE_PIXELS=true. Env pixels take precedence for matching IDs.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="savePixel">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Label</label>
                        <Input v-model="pixelForm.label" placeholder="e.g. Main site pixel" required />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Pixel ID</label>
                        <Input v-model="pixelForm.pixel_id" placeholder="1436406881878584" required />
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground">Access token</label>
                        <Input v-model="pixelForm.access_token" type="password" autocomplete="off" required />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Test event code (optional)</label>
                        <Input v-model="pixelForm.test_event_code" placeholder="TEST12345" autocomplete="off" />
                    </div>
                    <div class="flex flex-wrap items-end gap-4 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="pixelForm.is_active" type="checkbox" class="rounded border-input" />
                            Active
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="pixelForm.send_purchase" type="checkbox" class="rounded border-input" />
                            Send Purchase (paid)
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="pixelForm.send_initiate_checkout" type="checkbox" class="rounded border-input" />
                            Send InitiateCheckout
                        </label>
                        <Button type="submit" :disabled="pixelForm.processing">Save pixel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="mb-6 shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Database records (metadata &amp; logs)</CardTitle>
                <CardDescription>Synced from .env for delivery logs. Env-managed rows cannot be edited here.</CardDescription>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Label</TableHead>
                            <TableHead>Pixel ID</TableHead>
                            <TableHead>Events</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Last CAPI</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="pixel in pixels" :key="pixel.id">
                            <TableCell class="font-medium">{{ pixel.label }}</TableCell>
                            <TableCell class="font-mono text-sm">{{ pixel.pixel_id }}</TableCell>
                            <TableCell class="text-sm">
                                <span v-if="pixel.send_purchase">Purchase</span>
                                <span v-if="pixel.send_purchase && pixel.send_initiate_checkout"> · </span>
                                <span v-if="pixel.send_initiate_checkout">InitiateCheckout</span>
                            </TableCell>
                            <TableCell>
                                <span v-if="pixel.managed_by_env" class="text-xs text-muted-foreground">.env</span>
                                <span v-else-if="pixel.is_active" class="text-emerald-700">Active</span>
                                <span v-else class="text-muted-foreground">Off</span>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                <template v-if="pixel.last_event_at">
                                    {{ pixel.last_event_at }}
                                    <span v-if="pixel.last_event_status" class="block text-xs">{{ pixel.last_event_status }}</span>
                                </template>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell class="text-right space-x-2">
                                <template v-if="!pixel.managed_by_env && allowDatabasePixels">
                                    <Button type="button" variant="outline" size="sm" @click="startEdit(pixel)">Edit</Button>
                                    <Button type="button" variant="outline" size="sm" @click="removePixel(pixel)">Remove</Button>
                                </template>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!pixels.length">
                            <TableCell colspan="6" class="text-center text-muted-foreground">No pixels yet.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="editingId && allowDatabasePixels" class="mb-6 shadow-none border-primary/30">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Edit pixel</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveEdit">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Label</label>
                        <Input v-model="editForm.label" required />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Pixel ID</label>
                        <Input v-model="editForm.pixel_id" required />
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground">Access token (blank = keep current)</label>
                        <Input v-model="editForm.access_token" type="password" autocomplete="off" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Test event code</label>
                        <Input v-model="editForm.test_event_code" autocomplete="off" />
                    </div>
                    <div class="flex flex-wrap items-end gap-4 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-input" />
                            Active
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="editForm.send_purchase" type="checkbox" class="rounded border-input" />
                            Send Purchase
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="editForm.send_initiate_checkout" type="checkbox" class="rounded border-input" />
                            Send InitiateCheckout
                        </label>
                        <Button type="submit" :disabled="editForm.processing">Update</Button>
                        <Button type="button" variant="outline" @click="cancelEdit">Cancel</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="shadow-none">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">CAPI event log</CardTitle>
                <CardDescription>
                    Server-side Purchase and InitiateCheckout deliveries. Filter by pixel, event, marketer sid, and date.
                </CardDescription>
            </CardHeader>
            <CardContent class="mb-4">
                <MetaCapiEventFilters
                    :filters="props.filters"
                    :options="props.filterOptions"
                    @apply="applyEventFilters"
                    @reset="applyEventFilters"
                />
            </CardContent>
            <CardContent class="overflow-x-auto border-t pt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Time</TableHead>
                            <TableHead>Pixel</TableHead>
                            <TableHead>Event</TableHead>
                            <TableHead>SID (marketer)</TableHead>
                            <TableHead>Order</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="log in eventLogs.data || []" :key="log.id">
                            <TableCell class="whitespace-nowrap text-sm">{{ log.sent_at || '—' }}</TableCell>
                            <TableCell class="text-sm">
                                <span class="font-medium">{{ log.pixel_label }}</span>
                                <span class="block font-mono text-xs text-muted-foreground">{{ log.pixel_id }}</span>
                            </TableCell>
                            <TableCell class="text-sm">{{ log.event_name }}</TableCell>
                            <TableCell class="text-sm">
                                <template v-if="log.sid">
                                    <span class="font-mono text-xs">{{ log.sid }}</span>
                                    <span v-if="log.partner_name" class="block text-muted-foreground">{{ log.partner_name }}</span>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{ log.order_uuid || '—' }}</TableCell>
                            <TableCell class="text-sm">
                                <span :class="log.status === 'success' ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ log.status }}
                                </span>
                                <span v-if="log.http_status" class="block text-xs text-muted-foreground">HTTP {{ log.http_status }}</span>
                                <span v-if="log.error_message" class="block text-xs text-muted-foreground">{{ log.error_message }}</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!(eventLogs.data || []).length">
                            <TableCell colspan="6" class="text-center text-muted-foreground">No CAPI events match these filters.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Pagination
                    v-if="eventLogs?.links"
                    class="mt-4"
                    :links="eventLogs.links"
                    :meta="eventLogs.meta"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>
