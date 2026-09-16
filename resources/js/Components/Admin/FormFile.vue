<script setup>
import { computed, useId } from 'vue';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    accept: { type: String, default: 'image/*' },
    multiple: { type: Boolean, default: false },
    previewUrl: { type: String, default: '' },
});

defineEmits(['change']);

const fieldId = useId();

const resolvedPreviewUrl = computed(() => {
    if (! props.previewUrl) {
        return '';
    }

    if (/^(https?:|blob:|data:)/.test(props.previewUrl)) {
        return props.previewUrl;
    }

    return `/${props.previewUrl.replace(/^\//, '')}`;
});
</script>

<template>
    <div class="space-y-2">
        <Label :for="fieldId">{{ label }}</Label>
        <div class="rounded-lg border border-dashed border-border bg-muted/40 p-4">
            <Input
                :id="fieldId"
                type="file"
                :accept="accept"
                :multiple="multiple"
                class="h-auto cursor-pointer border-0 bg-transparent px-0 shadow-none file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                :aria-invalid="error ? 'true' : undefined"
                @change="$emit('change', $event)"
            />
            <img
                v-if="resolvedPreviewUrl"
                :src="resolvedPreviewUrl"
                alt=""
                class="mt-3 max-h-24 rounded-md border border-border object-contain"
            >
        </div>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
