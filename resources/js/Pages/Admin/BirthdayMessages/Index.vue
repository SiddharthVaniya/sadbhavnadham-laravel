<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import FormFile from '@/Components/Admin/FormFile.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const props = defineProps({
    settings: { type: Object, required: true },
    steps: { type: Array, default: () => [] },
    can_edit: { type: Boolean, default: false },
});

const localSettings = reactive({
    enabled: !!props.settings.enabled,
    aisensy_account_id: props.settings.aisensy_account_id ?? '',
});

const localSteps = ref(
    props.steps.map((step) => ({
        id: step.id,
        days_before: step.days_before,
        kind: step.kind,
        enabled: !!step.enabled,
        campaign_name: step.campaign_name ?? '',
        sort_order: step.sort_order ?? 0,
        image_path: step.image_path,
        image_url: step.image_url,
        image: null,
        remove_image: false,
        preview: step.image_url || '',
    })),
);

watch(
    () => props.steps,
    (next) => {
        localSteps.value = next.map((step) => ({
            id: step.id,
            days_before: step.days_before,
            kind: step.kind,
            enabled: !!step.enabled,
            campaign_name: step.campaign_name ?? '',
            sort_order: step.sort_order ?? 0,
            image_path: step.image_path,
            image_url: step.image_url,
            image: null,
            remove_image: false,
            preview: step.image_url || '',
        }));
    },
    { deep: true },
);

const form = useForm({
    settings: {
        enabled: false,
        aisensy_account_id: '',
    },
    steps: [],
});

const marketingSteps = computed(() =>
    localSteps.value.filter((step) => step.kind === 'marketing'),
);

const warmWishStep = computed(() =>
    localSteps.value.find((step) => step.kind === 'warm_wish') || null,
);

const addMarketingOffset = () => {
    localSteps.value.push({
        id: null,
        days_before: 1,
        kind: 'marketing',
        enabled: true,
        campaign_name: '',
        sort_order: (localSteps.value.length + 1) * 10,
        image_path: null,
        image_url: null,
        image: null,
        remove_image: false,
        preview: '',
    });
};

const removeStep = (indexInAll, step) => {
    if (step.kind === 'warm_wish') {
        return;
    }
    const idx = localSteps.value.findIndex((row) => row === step);
    if (idx >= 0) {
        localSteps.value.splice(idx, 1);
    }
};

const onImageChange = (step, event) => {
    const file = event?.target?.files?.[0] || null;
    step.image = file;
    step.remove_image = false;
    if (file) {
        step.preview = URL.createObjectURL(file);
    }
};

const clearImage = (step) => {
    step.image = null;
    step.remove_image = true;
    step.preview = '';
};

const save = () => {
    form.settings.enabled = !!localSettings.enabled;
    form.settings.aisensy_account_id = localSettings.aisensy_account_id;
    form.steps = localSteps.value.map((step) => ({
        id: step.id,
        days_before: Number(step.days_before) || 0,
        kind: step.kind,
        enabled: !!step.enabled,
        campaign_name: step.campaign_name || '',
        sort_order: Number(step.sort_order) || 0,
        remove_image: !!step.remove_image,
        image: step.image,
    }));

    form.post('/admin/birthday-messages', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Birthday messages" />

    <AdminLayout>
        <PageHeader
            title="Birthday messages"
            description="Three AiSensy templates from this panel: reminder (7 / 3 days before), birthday-day marketing if not donated, warm wish if donated."
        />

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Global</CardTitle>
                    <CardDescription>
                        Master switch and optional AiSensy account id. Daily cron runs at 09:00.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormToggle
                        v-model="localSettings.enabled"
                        label="Enable birthday WhatsApp"
                        description="When off, the daily command skips all steps (unless --force)."
                        :disabled="! can_edit"
                    />
                    <div class="space-y-2">
                        <Label>AiSensy account id (optional)</Label>
                        <Input
                            v-model="localSettings.aisensy_account_id"
                            class="max-w-sm"
                            placeholder="Leave blank for first active account"
                            :disabled="! can_edit"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle class="text-base">Reminders + birthday marketing</CardTitle>
                        <CardDescription>
                            days_before &gt; 0 = reminder (params: name + days). days_before = 0 = birthday marketing if the donor has not donated after the first reminder this year (params: name).
                            Suggested campaigns: happy_birthday_reminder_plant_tree (7 and 3), birthday_marketing_on_birthday (0).
                        </CardDescription>
                    </div>
                    <Button
                        v-if="can_edit"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addMarketingOffset"
                    >
                        Add offset
                    </Button>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div
                        v-for="(step, index) in marketingSteps"
                        :key="`m-${step.id ?? index}`"
                        class="rounded-lg border border-border p-4 space-y-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <FormToggle
                                v-model="step.enabled"
                                compact
                                label="Enabled"
                                :disabled="! can_edit"
                            />
                            <Button
                                v-if="can_edit && Number(step.days_before) !== 0"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="removeStep(index, step)"
                            >
                                Remove
                            </Button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label>Days before</Label>
                                <Input
                                    v-model.number="step.days_before"
                                    type="number"
                                    min="0"
                                    max="366"
                                    :disabled="! can_edit"
                                />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <Label>AiSensy campaign name</Label>
                                <Input
                                    v-model="step.campaign_name"
                                    :placeholder="Number(step.days_before) === 0 ? 'birthday_marketing_on_birthday' : 'happy_birthday_reminder_plant_tree'"
                                    :disabled="! can_edit"
                                />
                            </div>
                        </div>
                        <FormFile
                            v-if="can_edit"
                            label="Header image (optional)"
                            hint="JPG/PNG only if you override the template header. Leave empty when AiSensy already has the image."
                            :preview-url="step.preview"
                            @change="onImageChange(step, $event)"
                        />
                        <button
                            v-if="can_edit && (step.preview || step.image_path)"
                            type="button"
                            class="text-sm text-destructive"
                            @click="clearImage(step)"
                        >
                            Remove image
                        </button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="warmWishStep">
                <CardHeader>
                    <CardTitle class="text-base">Birthday day · warm wish (if donated)</CardTitle>
                    <CardDescription>
                        Compulsory branch on birthday when the donor paid after the first marketing/reminder this year.
                        No body variables — campaign name only (e.g. happy_birthday_current_day_warm_msg).
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormToggle
                        v-model="warmWishStep.enabled"
                        label="Enable warm wish"
                        :disabled="! can_edit"
                    />
                    <div class="space-y-2">
                        <Label>Warm wish AiSensy campaign</Label>
                        <Input
                            v-model="warmWishStep.campaign_name"
                            placeholder="happy_birthday_current_day_warm_msg"
                            :disabled="! can_edit"
                        />
                    </div>
                </CardContent>
            </Card>

            <div v-if="can_edit" class="flex justify-end">
                <Button type="button" :disabled="form.processing" @click="save">
                    {{ form.processing ? 'Saving…' : 'Save birthday messages' }}
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>
