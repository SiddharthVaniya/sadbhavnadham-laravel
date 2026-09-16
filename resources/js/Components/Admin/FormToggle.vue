<script setup>
import { useId } from 'vue';

defineProps({
    label: { type: String, required: true },
    description: { type: String, default: '' },
    modelValue: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const labelId = useId();
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/40"
        :class="compact ? 'px-3 py-2.5' : 'px-4 py-3.5'"
    >
        <div class="min-w-0 flex-1">
            <div :id="labelId" class="text-sm font-medium text-foreground">{{ label }}</div>
            <div
                v-if="description && !compact"
                class="mt-0.5 text-sm leading-snug text-muted-foreground"
            >
                {{ description }}
            </div>
            <div
                v-else-if="description && compact"
                class="truncate text-xs text-muted-foreground"
                :title="description"
            >
                {{ description }}
            </div>
        </div>
        <button
            type="button"
            role="switch"
            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="modelValue ? 'bg-primary' : 'bg-muted-foreground/30'"
            :aria-checked="modelValue ? 'true' : 'false'"
            :aria-labelledby="labelId"
            :disabled="disabled"
            @click="$emit('update:modelValue', ! modelValue)"
        >
            <span
                class="absolute top-0.5 h-5 w-5 rounded-full bg-background shadow-sm transition"
                :class="modelValue ? 'left-5' : 'left-0.5'"
            />
        </button>
    </div>
</template>
