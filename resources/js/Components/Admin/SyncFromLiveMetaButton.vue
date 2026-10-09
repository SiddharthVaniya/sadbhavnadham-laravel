<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    redirect: { type: String, default: 'meta' },
    from: { type: String, default: null },
    to: { type: String, default: null },
    metaAdAccountId: { type: [Number, String], default: null },
    date: { type: String, default: null },
    label: { type: String, default: 'Sync from live Meta' },
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'default' },
    extra: { type: Object, default: () => ({}) },
});

const processing = ref(false);

const sync = () => {
    processing.value = true;

    router.post('/admin/meta/sync', {
        redirect: props.redirect,
        from: props.from,
        to: props.to,
        meta_ad_account_id: props.metaAdAccountId,
        date: props.date,
        ...props.extra,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Button
        type="button"
        :variant="variant"
        :size="size"
        :disabled="processing"
        @click="sync"
    >
        {{ processing ? 'Syncing Meta…' : label }}
    </Button>
</template>
