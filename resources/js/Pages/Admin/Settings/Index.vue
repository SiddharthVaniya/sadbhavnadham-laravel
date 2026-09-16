<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
    settings: { type: Array, default: () => [] },
});

const rows = ref(props.settings.map((s) => ({ ...s, draft_value: s.value ?? '', saving: false, saved: false, error: '' })));

const notificationRows = computed(() => rows.value.filter((s) => s.group !== 'delivery_retry'));
const retryRows = computed(() => rows.value.filter((s) => s.group === 'delivery_retry'));

const toggle = async (setting) => {
    const response = await fetch(setting.toggle_url, {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    });

    if (response.ok) {
        const data = await response.json();
        setting.enabled = data.enabled;
    }
};

const saveValue = async (setting) => {
    setting.saving = true;
    setting.saved = false;
    setting.error = '';

    try {
        const response = await fetch(setting.update_url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ value: setting.draft_value ?? '' }),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok) {
            setting.value = data.value;
            setting.draft_value = data.value;
            setting.saved = true;
            return;
        }

        setting.error = data?.errors?.value?.[0] ?? data?.message ?? 'Could not save. Check the value and try again.';
    } finally {
        setting.saving = false;
    }
};

const placeholderFor = (setting) => {
    if (setting.key === 'aisensy_birthday_campaign') {
        return 'Birthday campaign name';
    }

    if (setting.key === 'aisensy_otp_campaign') {
        return 'OTP campaign name';
    }

    if (setting.is_integer) {
        return String(setting.min ?? 1);
    }

    return 'Optional account id';
};
</script>

<template>
    <Head title="Settings" />
    <AdminLayout>
        <template #header>Settings</template>
        <PageHeader title="Notification settings" subtitle="Toggle channels and configure delivery retries">
            <template #actions>
                <Link href="/admin/settings/branding" class="admin-btn-secondary">Branding</Link>
            </template>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-none">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Notification</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium text-center">Status / Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="setting in notificationRows" :key="setting.id">
                        <td class="px-4 py-3 font-medium">{{ setting.label }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ setting.description }}</td>
                        <td class="px-4 py-3">
                            <div v-if="setting.is_toggle" class="flex justify-center">
                                <button
                                    type="button"
                                    class="relative inline-flex h-6 w-11 rounded-full transition"
                                    :class="setting.enabled ? 'bg-foreground' : 'bg-muted-foreground/40'"
                                    @click="toggle(setting)"
                                >
                                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-background transition" :class="setting.enabled ? 'left-5' : 'left-0.5'" />
                                </button>
                            </div>
                            <div v-else class="flex flex-col gap-2">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input
                                        v-model="setting.draft_value"
                                        type="text"
                                        class="admin-input w-full min-w-0"
                                        :placeholder="placeholderFor(setting)"
                                    >
                                    <button
                                        type="button"
                                        class="admin-btn-secondary shrink-0"
                                        :disabled="setting.saving"
                                        @click="saveValue(setting)"
                                    >
                                        {{ setting.saving ? 'Saving…' : 'Save' }}
                                    </button>
                                    <span v-if="setting.saved" class="text-xs text-emerald-700">Saved</span>
                                </div>
                                <p v-if="setting.error" class="text-xs text-red-600">{{ setting.error }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="retryRows.length" class="mt-8">
            <h2 class="text-base font-semibold text-foreground">Auto-retry limits</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Controls how long and how many times the system retries missed emails or WhatsApp messages after payment.
                Change these only if delivery is failing often.
            </p>
            <div class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-none">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/40 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Setting</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium text-center">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="setting in retryRows" :key="setting.id">
                            <td class="px-4 py-3 font-medium">{{ setting.label }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ setting.description }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-center">
                                        <input
                                            v-model="setting.draft_value"
                                            type="number"
                                            class="admin-input w-full min-w-0 sm:max-w-32"
                                            :min="setting.min ?? 1"
                                            :max="setting.max ?? undefined"
                                            :placeholder="placeholderFor(setting)"
                                        >
                                        <button
                                            type="button"
                                            class="admin-btn-secondary shrink-0"
                                            :disabled="setting.saving"
                                            @click="saveValue(setting)"
                                        >
                                            {{ setting.saving ? 'Saving…' : 'Save' }}
                                        </button>
                                        <span v-if="setting.saved" class="text-xs text-emerald-700">Saved</span>
                                    </div>
                                    <p v-if="setting.error" class="text-center text-xs text-red-600">{{ setting.error }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-sm text-muted-foreground">
            Birthday WhatsApp uses the AiSensy birthday media campaign. Template params send the donor name; media is the personalized birthday image.
        </p>
        <p class="mt-2 text-sm text-muted-foreground">
            Returning-donor OTP uses email by default. Set AiSensy OTP Campaign for WhatsApp (Authentication template + Copy Code button). We send the OTP in templateParams and the button parameter — both must match the live campaign.
        </p>
    </AdminLayout>
</template>
