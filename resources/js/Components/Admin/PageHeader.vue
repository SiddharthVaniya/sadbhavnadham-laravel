<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex flex-wrap items-start justify-between gap-3"
        :class="compact ? 'mb-3' : 'mb-6'"
    >
        <div>
            <h2 class="font-semibold tracking-tight text-foreground" :class="compact ? 'text-xl' : 'text-2xl'">{{ title }}</h2>
            <p v-if="subtitle" class="text-sm text-muted-foreground" :class="compact ? 'mt-0.5' : 'mt-1'">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
