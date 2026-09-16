<script setup>
import { computed } from 'vue';
import { Badge } from '@/Components/ui/badge';

const props = defineProps({
    status: { type: String, required: true },
});

const variant = computed(() => {
    const map = {
        paid: 'default',
        pending: 'secondary',
        failed: 'destructive',
    };

    return map[props.status] ?? 'outline';
});

const label = computed(() => {
    const map = {
        paid: 'Paid',
        pending: 'Pending',
        failed: 'Failed',
    };

    return map[props.status] ?? props.status;
});

const toneClass = computed(() => {
    if (props.status === 'paid') {
        return 'border-transparent bg-emerald-600 text-white hover:bg-emerald-600';
    }

    if (props.status === 'pending') {
        return 'border-transparent bg-amber-100 text-amber-900 hover:bg-amber-100';
    }

    return '';
});
</script>

<template>
    <Badge :variant="variant" :class="toneClass">
        {{ label }}
    </Badge>
</template>
