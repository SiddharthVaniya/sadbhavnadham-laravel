<script setup>
import { useId } from 'vue';
import { Label } from '@/Components/ui/label';

defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

const fieldId = useId();
</script>

<template>
    <div class="space-y-2">
        <Label :for="fieldId">{{ label }}</Label>
        <select
            :id="fieldId"
            :value="modelValue"
            class="admin-input"
            :class="error ? 'admin-input--error' : ''"
            :aria-invalid="error ? 'true' : undefined"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <slot />
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
