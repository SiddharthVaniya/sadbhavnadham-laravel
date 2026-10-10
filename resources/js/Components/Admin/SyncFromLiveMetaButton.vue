<script setup>
import { nextTick, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const page = usePage();

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
    disabled: { type: Boolean, default: false },
});

const processing = ref(false);

const syncResultAlert = () => {
    const flash = page.props.flash ?? {};

    if (flash.error) {
        window.alert(String(flash.error));
        return;
    }

    const status = flash.status ? String(flash.status) : '';

    if (status === '') {
        return;
    }

    const failedMatch = status.match(/(\d+)\s+failed\b/i);

    if (failedMatch && Number(failedMatch[1]) > 0) {
        window.alert(status);
    }
};

const sync = () => {
    processing.value = true;

    router.post('/admin/meta/sync', {
        redirect: props.redirect,
        from: props.from,
        to: props.to,
        from_date: props.from ?? props.extra.from_date ?? null,
        to_date: props.to ?? props.extra.to_date ?? null,
        meta_ad_account_id: props.metaAdAccountId ?? props.extra.meta_ad_account_id ?? null,
        date: props.date,
        ...props.extra,
    }, {
        preserveScroll: true,
        onSuccess: async () => {
            await nextTick();
            syncResultAlert();
        },
        onError: (errors) => {
            const lines = Object.values(errors ?? {})
                .flat()
                .filter((line) => typeof line === 'string' && line.trim() !== '');

            window.alert(lines.length ? lines.join('\n') : 'Meta sync failed. Please try again.');
        },
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
        :disabled="processing || disabled"
        @click="sync"
    >
        {{ processing ? 'Syncing Meta…' : label }}
    </Button>
</template>
