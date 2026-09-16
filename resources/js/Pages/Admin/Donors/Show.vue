<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import FormSelect from '@/Components/Admin/FormSelect.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';

const props = defineProps({
    donorId: { type: Number, required: true },
    donor: { type: Object, required: true },
    profile: { type: Object, default: () => ({}) },
    crm: { type: Object, required: true },
    donations: { type: Object, required: true },
    causeBreakdown: { type: Array, default: () => [] },
    back_url: { type: String, default: '/admin/donors' },
    opt_out_url: { type: String, required: true },
    has_donation_history: { type: Boolean, default: true },
});

const page = usePage();
const donationsList = computed(() => props.donations.data ?? []);
const notes = computed(() => props.crm.notes ?? []);
const tasks = computed(() => props.crm.tasks ?? []);
const canManageCrm = computed(() => Boolean(props.crm.can_manage));

const staffOptions = computed(() => [
    { value: '', label: 'Unassigned' },
    ...(props.crm.staff_options ?? []),
]);

const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
const initials = (name) => (name || '?').trim().charAt(0).toUpperCase();

const ownerForm = useForm({
    owner_user_id: props.crm.owner_user_id ?? '',
});

const noteForm = useForm({
    body: '',
});

const taskForm = useForm({
    title: '',
    body: '',
    assigned_to: '',
    due_at: '',
    status: 'open',
});

const taskEdits = reactive({});

const ensureTaskEdit = (task) => {
    if (! taskEdits[task.id]) {
        taskEdits[task.id] = {
            title: task.title,
            body: task.body ?? '',
            assigned_to: task.assignee?.id ?? '',
            due_at: task.due_at_input ?? '',
            status: task.status,
        };
    }

    return taskEdits[task.id];
};

const toggleWhatsappOptOut = () => {
    const next = !props.donor.whatsapp_opt_out;
    const message = next
        ? 'Opt this donor out of WhatsApp campaigns?'
        : 'Allow this donor to receive WhatsApp campaigns again?';
    if (!confirm(message)) {
        return;
    }
    router.post(props.opt_out_url, { whatsapp_opt_out: next }, { preserveScroll: true });
};

const saveOwner = () => {
    ownerForm
        .transform((data) => ({
            owner_user_id: data.owner_user_id === '' ? null : data.owner_user_id,
        }))
        .put(props.crm.urls.owner, { preserveScroll: true });
};

const addNote = () => {
    noteForm.post(props.crm.urls.notes_store, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
};

const deleteNote = (noteId) => {
    if (!confirm('Delete this note?')) {
        return;
    }
    router.delete(`/admin/donors/${props.donorId}/notes/${noteId}`, { preserveScroll: true });
};

const addTask = () => {
    taskForm
        .transform((data) => ({
            ...data,
            assigned_to: data.assigned_to === '' ? null : data.assigned_to,
            due_at: data.due_at === '' ? null : data.due_at,
        }))
        .post(props.crm.urls.tasks_store, {
            preserveScroll: true,
            onSuccess: () => taskForm.reset('title', 'body', 'assigned_to', 'due_at'),
        });
};

const saveTask = (taskId) => {
    const edit = taskEdits[taskId];
    if (! edit) {
        return;
    }

    router.put(`/admin/donors/${props.donorId}/tasks/${taskId}`, {
        title: edit.title,
        body: edit.body === '' ? null : edit.body,
        assigned_to: edit.assigned_to === '' ? null : edit.assigned_to,
        due_at: edit.due_at === '' ? null : edit.due_at,
        status: edit.status,
    }, { preserveScroll: true });
};

const deleteTask = (taskId) => {
    if (!confirm('Delete this task?')) {
        return;
    }
    router.delete(`/admin/donors/${props.donorId}/tasks/${taskId}`, { preserveScroll: true });
};

const taskStatusClass = (task) => {
    if (task.is_overdue) {
        return 'bg-rose-50 text-rose-700 ring-rose-600/20';
    }
    if (task.status === 'done') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    }
    if (task.status === 'cancelled') {
        return 'bg-muted text-muted-foreground ring-muted-foreground/20';
    }
    return 'bg-amber-50 text-amber-700 ring-amber-600/20';
};
</script>

<template>
    <Head :title="donor.name" />

    <AdminLayout>
        <template #header>Donor details</template>

        <PageHeader :title="donor.name" :subtitle="`${donor.email} · ${donor.phone}`">
            <template #actions>
                <Link
                    v-if="has_donation_history"
                    :href="`/admin/donors/${donorId}/export`"
                    class="rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted"
                >
                    Export donations CSV
                </Link>
                <Link :href="back_url" class="rounded-lg border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted">Back</Link>
            </template>
        </PageHeader>

        <p v-if="page.props.flash?.status" class="mb-4 text-sm text-emerald-700">{{ page.props.flash.status }}</p>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-1">
                <div class="rounded-xl border border-border bg-card p-6 text-center shadow-none">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-muted text-2xl font-semibold text-foreground">
                        {{ initials(donor.name) }}
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">{{ donor.name }}</h2>
                    <p class="text-sm text-muted-foreground">{{ donor.email }}</p>
                    <p class="text-sm text-muted-foreground">{{ donor.phone }}</p>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <StatCard label="Paid total" :value="formatMoney(donor.paid_amount)" />
                        <StatCard label="Paid donations" :value="donor.paid_donations" />
                    </div>

                    <div class="mt-4 flex flex-wrap justify-center gap-2 text-xs">
                        <span class="rounded-full bg-muted px-2 py-1">{{ donor.total_attempts }} attempts</span>
                        <span v-if="donor.pending_attempts" class="rounded-full bg-amber-50 px-2 py-1 text-amber-700">{{ donor.pending_attempts }} pending</span>
                        <span v-if="donor.failed_attempts" class="rounded-full bg-rose-50 px-2 py-1 text-rose-700">{{ donor.failed_attempts }} failed</span>
                        <span class="rounded-full bg-sky-50 px-2 py-1 text-sky-700">{{ crm.open_tasks_count }} open tasks</span>
                        <span v-if="crm.overdue_tasks_count" class="rounded-full bg-rose-50 px-2 py-1 text-rose-700">{{ crm.overdue_tasks_count }} overdue</span>
                    </div>

                    <div class="mt-6 rounded-lg border border-border p-3 text-left text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-foreground">WhatsApp campaigns</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ donor.whatsapp_opt_out ? `Opted out${donor.whatsapp_opted_out_at ? ` · ${donor.whatsapp_opted_out_at}` : ''}` : 'Eligible (not opted out)' }}
                                </p>
                            </div>
                            <button type="button" class="admin-btn-secondary text-xs" @click="toggleWhatsappOptOut">
                                {{ donor.whatsapp_opt_out ? 'Allow again' : 'Opt out' }}
                            </button>
                        </div>
                    </div>

                    <dl v-if="profile.pan_number || profile.location" class="mt-6 space-y-2 border-t border-border pt-4 text-left text-sm">
                        <div v-if="profile.pan_number" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">PAN</dt>
                            <dd class="font-medium">{{ profile.pan_number }}</dd>
                        </div>
                        <div v-if="profile.location" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Location</dt>
                            <dd class="font-medium">{{ profile.location }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 text-left shadow-none">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">Relationship owner</h3>
                    <p class="mb-3 text-xs text-muted-foreground">Staff responsible for follow-ups with this donor.</p>
                    <template v-if="canManageCrm">
                        <FormSelect
                            v-model="ownerForm.owner_user_id"
                            label="Owner"
                            :options="staffOptions"
                            :error="ownerForm.errors.owner_user_id"
                        />
                        <button
                            type="button"
                            class="admin-btn-primary mt-3 w-full"
                            :disabled="ownerForm.processing"
                            @click="saveOwner"
                        >
                            Save owner
                        </button>
                    </template>
                    <p v-else class="text-sm text-foreground">
                        {{ crm.owner?.name || 'Unassigned' }}
                    </p>
                </div>
            </div>

            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-foreground">CRM notes</h3>
                        <span class="text-xs text-muted-foreground">{{ notes.length }} notes</span>
                    </div>

                    <form v-if="canManageCrm" class="mb-4 space-y-3" @submit.prevent="addNote">
                        <FormTextarea
                            v-model="noteForm.body"
                            label="Add note"
                            :rows="3"
                            :error="noteForm.errors.body"
                            hint="Call outcomes, preferences, pledges, context for next ask."
                        />
                        <button type="submit" class="admin-btn-primary" :disabled="noteForm.processing">
                            Add note
                        </button>
                    </form>

                    <ul class="space-y-3">
                        <li
                            v-for="note in notes"
                            :key="note.id"
                            class="rounded-lg border border-border bg-muted/50 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="whitespace-pre-wrap text-sm text-foreground">{{ note.body }}</p>
                                    <p class="mt-2 text-[11px] text-muted-foreground">
                                        {{ note.author || 'Unknown' }} · {{ note.created_at }}
                                        <span v-if="note.created_at_human"> ({{ note.created_at_human }})</span>
                                    </p>
                                </div>
                                <button
                                    v-if="canManageCrm"
                                    type="button"
                                    class="shrink-0 text-xs font-medium text-rose-600 hover:text-rose-800"
                                    @click="deleteNote(note.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                        <li v-if="! notes.length" class="text-sm text-muted-foreground">No CRM notes yet.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-foreground">Follow-up tasks</h3>
                        <span class="text-xs text-muted-foreground">{{ crm.open_tasks_count }} open</span>
                    </div>

                    <form v-if="canManageCrm" class="mb-5 grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-2" @submit.prevent="addTask">
                        <div class="sm:col-span-2">
                            <FormInput
                                v-model="taskForm.title"
                                label="Task title"
                                :error="taskForm.errors.title"
                                required
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <FormTextarea
                                v-model="taskForm.body"
                                label="Details"
                                :rows="2"
                                :error="taskForm.errors.body"
                            />
                        </div>
                        <FormSelect
                            v-model="taskForm.assigned_to"
                            label="Assignee"
                            :options="staffOptions"
                            :error="taskForm.errors.assigned_to"
                        />
                        <FormInput
                            v-model="taskForm.due_at"
                            label="Due at"
                            type="datetime-local"
                            :error="taskForm.errors.due_at"
                        />
                        <div class="sm:col-span-2">
                            <button type="submit" class="admin-btn-primary" :disabled="taskForm.processing">
                                Create task
                            </button>
                        </div>
                    </form>

                    <ul class="space-y-4">
                        <li
                            v-for="task in tasks"
                            :key="task.id"
                            class="rounded-lg border border-border p-3"
                        >
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="taskStatusClass(task)"
                                >
                                    {{ task.is_overdue ? 'Overdue' : task.status }}
                                </span>
                                <span v-if="task.due_at" class="text-xs text-muted-foreground">Due {{ task.due_at }}</span>
                                <span v-if="task.assignee" class="text-xs text-muted-foreground">· {{ task.assignee.name }}</span>
                            </div>

                            <template v-if="canManageCrm">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <FormInput
                                            v-model="ensureTaskEdit(task).title"
                                            label="Title"
                                            required
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <FormTextarea
                                            v-model="ensureTaskEdit(task).body"
                                            label="Details"
                                            :rows="2"
                                        />
                                    </div>
                                    <FormSelect
                                        v-model="ensureTaskEdit(task).assigned_to"
                                        label="Assignee"
                                        :options="staffOptions"
                                    />
                                    <FormInput
                                        v-model="ensureTaskEdit(task).due_at"
                                        label="Due at"
                                        type="datetime-local"
                                    />
                                    <FormSelect
                                        v-model="ensureTaskEdit(task).status"
                                        label="Status"
                                        :options="[
                                            { value: 'open', label: 'Open' },
                                            { value: 'done', label: 'Done' },
                                            { value: 'cancelled', label: 'Cancelled' },
                                        ]"
                                    />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="admin-btn-primary text-xs" @click="saveTask(task.id)">
                                        Save task
                                    </button>
                                    <button type="button" class="text-xs font-medium text-rose-600 hover:text-rose-800" @click="deleteTask(task.id)">
                                        Delete
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <p class="font-medium text-foreground">{{ task.title }}</p>
                                <p v-if="task.body" class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ task.body }}</p>
                            </template>
                        </li>
                        <li v-if="! tasks.length" class="text-sm text-muted-foreground">No follow-up tasks yet.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-none">
                    <h3 class="mb-4 text-sm font-semibold text-foreground">Cause breakdown (paid)</h3>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(row, index) in causeBreakdown" :key="index" class="flex justify-between gap-4">
                            <span>{{ row.cause }}</span>
                            <span class="text-muted-foreground">{{ row.count }} items · {{ formatMoney(row.amount) }}</span>
                        </li>
                        <li v-if="! causeBreakdown.length" class="text-muted-foreground">No paid cause breakdown.</li>
                    </ul>
                </div>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
                    <div class="border-b border-border px-5 py-4">
                        <h3 class="text-sm font-semibold text-foreground">All donation attempts</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-border text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Order</th>
                                    <th class="px-4 py-3 font-medium">Cause</th>
                                    <th class="px-4 py-3 font-medium text-right">Amount</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Date</th>
                                    <th class="px-4 py-3 font-medium text-right"> </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="order in donationsList" :key="order.id">
                                    <td class="px-4 py-3 font-mono text-xs">{{ order.uuid }}</td>
                                    <td class="px-4 py-3">{{ order.cause }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatMoney(order.total_amount) }}</td>
                                    <td class="px-4 py-3"><StatusBadge :status="order.status" /></td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ order.created_at }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="`/admin/donations/${order.uuid}`" class="text-sm font-medium hover:underline">View</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-border px-4 py-3">
                        <Pagination :links="donations.links" :meta="donations.meta" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
