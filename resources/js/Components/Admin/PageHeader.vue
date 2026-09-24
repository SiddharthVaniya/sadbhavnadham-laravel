<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex min-w-0 flex-wrap items-start justify-between gap-3"
        :class="compact ? 'mb-3' : 'mb-6'"
    >
        <div class="min-w-0 flex-1 basis-[12rem]">
            <h2
                class="break-words font-semibold tracking-tight text-foreground"
                :class="compact ? 'text-xl' : 'text-2xl'"
            >
                {{ title }}
            </h2>
            <p
                v-if="subtitle"
                class="break-words text-sm text-muted-foreground"
                :class="compact ? 'mt-0.5' : 'mt-1'"
            >
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
