<script setup>
import { useId } from 'vue';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

defineProps({
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    hint: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const fieldId = useId();
</script>

<template>
    <div class="space-y-2">
        <Label :for="fieldId">{{ label }}</Label>
        <Textarea
            :id="fieldId"
            :model-value="modelValue"
            :rows="rows"
            class="min-h-[100px] resize-y"
            :aria-invalid="error ? 'true' : undefined"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
