<script setup>
import { Button } from '@/Components/ui/button';

defineProps({
    processing: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'Save changes' },
});
</script>

<template>
    <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-border bg-background/95 px-4 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <slot name="leading" />
            <slot />
            <Button type="submit" :disabled="processing">
                {{ processing ? 'Saving…' : submitLabel }}
            </Button>
        </div>
    </div>
</template>
