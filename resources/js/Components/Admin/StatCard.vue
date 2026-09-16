<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    /** @deprecated Kept for older pages; shadcn cards use a single clean tone. */
    tone: { type: String, default: 'default' },
});
</script>

<template>
    <Card :size="compact ? 'sm' : 'default'" class="shadow-none">
        <CardHeader :class="compact ? 'px-3.5 py-3' : 'pb-2'">
            <CardDescription class="text-[11px] font-medium uppercase tracking-wide">
                {{ label }}
            </CardDescription>
            <CardTitle class="tabular-nums tracking-tight" :class="compact ? 'text-lg' : 'text-2xl'">
                {{ value }}
            </CardTitle>
        </CardHeader>
        <CardContent v-if="hint" :class="compact ? 'px-3.5 pb-3 pt-0' : 'pt-0'">
            <p class="text-xs text-muted-foreground" :class="compact ? 'text-[11px]' : ''">
                {{ hint }}
            </p>
        </CardContent>
    </Card>
</template>
