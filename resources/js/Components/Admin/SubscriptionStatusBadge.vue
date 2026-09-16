<script setup>
import { computed } from 'vue';
import { Badge } from '@/Components/ui/badge';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: '' },
});

const variant = computed(() => {
    const map = {
        active: 'default',
        authenticated: 'secondary',
        pending: 'outline',
        created: 'secondary',
        halted: 'outline',
        cancelled: 'destructive',
        completed: 'secondary',
    };

    return map[props.status] ?? 'secondary';
});

const toneClass = computed(() => {
    const map = {
        active: 'border-transparent bg-emerald-50 text-emerald-700 hover:bg-emerald-50',
        authenticated: 'border-transparent bg-sky-50 text-sky-700 hover:bg-sky-50',
        pending: 'border-transparent bg-amber-50 text-amber-700 hover:bg-amber-50',
        halted: 'border-transparent bg-orange-50 text-orange-700 hover:bg-orange-50',
        cancelled: '',
        completed: '',
        created: '',
    };

    return map[props.status] ?? '';
});
</script>

<template>
    <Badge :variant="variant" :class="toneClass">
        {{ label || status }}
    </Badge>
</template>
