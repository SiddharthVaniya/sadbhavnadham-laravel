<script setup>
import { Card, CardContent, CardDescription, CardHeader } from '@/Components/ui/card';

defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <Card class="shadow-none">
        <CardHeader class="flex flex-row items-start justify-between gap-3 space-y-0 pb-2">
            <CardDescription class="pt-0.5 text-[13px] font-medium tracking-tight text-muted-foreground">
                {{ label }}
            </CardDescription>
            <div class="rounded-lg bg-muted p-2 text-foreground">
                <slot name="icon" />
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <p class="text-[1.65rem] font-semibold leading-none tracking-tight tabular-nums text-foreground sm:text-3xl">
                {{ value }}
            </p>
            <p v-if="hint" class="mt-2 text-xs leading-snug text-muted-foreground">
                {{ hint }}
            </p>
        </CardContent>
    </Card>
</template>
