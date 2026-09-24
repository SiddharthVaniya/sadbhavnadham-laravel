<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
    causes: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    description: '',
    usage: 'multiple_use',
    fixed_amount: false,
    payment_amount: '',
    cause_id: '',
    cause_package_id: '',
});

const filteredPackages = computed(() => {
    if (!form.cause_id) {
        return [];
    }

    const cause = props.causes.find((item) => String(item.id) === String(form.cause_id));

    return cause?.packages ?? [];
});

watch(() => form.cause_id, () => {
    form.cause_package_id = '';
});

const canSubmit = computed(() => form.name.trim() !== '' && !form.processing);

const submit = () => {
    form.transform((data) => ({
        ...data,
        payment_amount: data.fixed_amount ? data.payment_amount : null,
        cause_id: data.cause_id || null,
        cause_package_id: data.cause_id && data.cause_package_id ? data.cause_package_id : null,
    })).post('/admin/qr-codes');
};
</script>

<template>
    <Head title="Create QR Code" />
    <AdminLayout>
        <template #header>Create QR code</template>

        <PageHeader title="Create QR code" subtitle="Creates a UPI QR in Razorpay and saves it here">
            <template #actions>
                <Link href="/admin/qr-codes" class="rounded-lg border border-border px-3 py-2 text-sm">Back</Link>
            </template>
        </PageHeader>

        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-border bg-card p-5 shadow-none" @submit.prevent="submit">
            <p v-if="form.errors.razorpay" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                {{ form.errors.razorpay }}
            </p>

            <div>
                <label class="admin-label">Name</label>
                <input v-model="form.name" type="text" class="admin-input" placeholder="Temple counter / Event stall" required>
                <p v-if="form.errors.name" class="mt-1 text-sm text-rose-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="admin-label">Description (optional)</label>
                <textarea v-model="form.description" class="admin-input" rows="3" placeholder="Where this QR will be used" />
                <p v-if="form.errors.description" class="mt-1 text-sm text-rose-700">{{ form.errors.description }}</p>
            </div>

            <div>
                <label class="admin-label">Usage</label>
                <select v-model="form.usage" class="admin-input">
                    <option value="multiple_use">Multiple use (recommended)</option>
                    <option value="single_use">Single use</option>
                </select>
            </div>

            <label class="flex items-start gap-2 text-sm">
                <input v-model="form.fixed_amount" type="checkbox" class="mt-1">
                <span>
                    Fixed amount only
                    <span class="block text-xs text-muted-foreground">Uncheck to accept any amount</span>
                </span>
            </label>

            <div v-if="form.fixed_amount">
                <label class="admin-label">Amount (₹)</label>
                <input v-model="form.payment_amount" type="number" min="1" step="1" class="admin-input" placeholder="501">
                <p v-if="form.errors.payment_amount" class="mt-1 text-sm text-rose-700">{{ form.errors.payment_amount }}</p>
            </div>

            <div class="border-t border-border pt-4">
                <h3 class="text-sm font-semibold text-foreground">Cause mapping (optional)</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    When set, QR payments auto-create a donation line item for this cause.
                </p>
            </div>

            <div>
                <label class="admin-label">Cause</label>
                <select v-model="form.cause_id" class="admin-input">
                    <option value="">No cause (manual later)</option>
                    <option v-for="cause in causes" :key="cause.id" :value="String(cause.id)">
                        {{ cause.title }}
                    </option>
                </select>
                <p v-if="form.errors.cause_id" class="mt-1 text-sm text-rose-700">{{ form.errors.cause_id }}</p>
            </div>

            <div v-if="form.cause_id">
                <label class="admin-label">Package (optional)</label>
                <select v-model="form.cause_package_id" class="admin-input">
                    <option value="">Any / custom amount</option>
                    <option v-for="pkg in filteredPackages" :key="pkg.id" :value="String(pkg.id)">
                        {{ pkg.title }} — ₹ {{ Number(pkg.amount || 0).toLocaleString('en-IN') }}
                    </option>
                </select>
                <p v-if="form.errors.cause_package_id" class="mt-1 text-sm text-rose-700">{{ form.errors.cause_package_id }}</p>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="admin-btn-primary" :disabled="!canSubmit">
                    {{ form.processing ? 'Creating…' : 'Create in Razorpay' }}
                </button>
                <Link href="/admin/qr-codes" class="rounded-lg border border-border px-4 py-2 text-sm">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
