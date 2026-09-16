<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    dense: { type: Boolean, default: false },
});
</script>

<template>
    <Card class="shadow-none">
        <CardHeader v-if="$slots.header || title" :class="dense ? 'px-4 py-3' : ''">
            <slot name="header">
                <CardTitle v-if="title" class="text-base">{{ title }}</CardTitle>
                <CardDescription v-if="description">{{ description }}</CardDescription>
            </slot>
        </CardHeader>
        <CardContent :class="dense ? 'space-y-4 px-4 pb-4' : 'space-y-5'">
            <slot />
        </CardContent>
    </Card>
</template>
