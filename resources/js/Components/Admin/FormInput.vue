<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const fieldId = useId();
const showPassword = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => {
    if (! isPassword.value) {
        return props.type;
    }

    return showPassword.value ? 'text' : 'password';
});
</script>

<template>
    <div class="space-y-2">
        <Label :for="fieldId">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <div class="relative">
            <Input
                :id="fieldId"
                :type="inputType"
                :model-value="modelValue"
                :required="required"
                :readonly="readonly"
                class="h-9"
                :class="isPassword ? 'pr-10' : ''"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="hint || error ? `${fieldId}-desc` : undefined"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <Button
                v-if="isPassword"
                type="button"
                variant="ghost"
                size="icon-sm"
                class="absolute inset-y-0 right-0 my-auto text-muted-foreground hover:text-foreground"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :aria-pressed="showPassword"
                @click="showPassword = ! showPassword"
            >
                <EyeOff v-if="showPassword" class="size-4" />
                <Eye v-else class="size-4" />
            </Button>
        </div>
        <p v-if="hint" :id="`${fieldId}-desc`" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="error" :id="hint ? undefined : `${fieldId}-desc`" class="text-xs text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
