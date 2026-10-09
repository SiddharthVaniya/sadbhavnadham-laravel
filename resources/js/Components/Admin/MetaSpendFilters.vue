<script setup>
import { reactive, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    showMarketer: { type: Boolean, default: false },
    showMatch: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'reset']);

const form = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ?? '',
    meta_ad_account_id: props.filters.meta_ad_account_id ?? '',
    app_id: props.filters.app_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    campaign: props.filters.campaign ?? '',
    adset: props.filters.adset ?? '',
    theme: props.filters.theme ?? '',
    cause: props.filters.cause ?? '',
    match: props.filters.match ?? 'all',
});

watch(
    () => props.filters,
    (next) => {
        form.q = next.q ?? '';
        form.user_id = next.user_id ?? '';
        form.meta_ad_account_id = next.meta_ad_account_id ?? '';
        form.app_id = next.app_id ?? '';
        form.from_date = next.from_date ?? '';
        form.to_date = next.to_date ?? '';
        form.campaign = next.campaign ?? '';
        form.adset = next.adset ?? '';
        form.theme = next.theme ?? '';
        form.cause = next.cause ?? '';
        form.match = next.match ?? 'all';
    },
    { deep: true },
);

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

const apply = () => emit('apply', { ...form });

const reset = () => {
    form.q = '';
    form.user_id = '';
    form.meta_ad_account_id = '';
    form.app_id = '';
    form.from_date = '';
    form.to_date = '';
    form.campaign = '';
    form.adset = '';
    form.theme = '';
    form.cause = '';
    form.match = 'all';
    emit('reset', { ...form });
};
</script>

<template>
    <form class="grid gap-3 md:grid-cols-3 xl:grid-cols-4" @submit.prevent="apply">
        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Search</label>
            <Input v-model="form.q" placeholder="Ad / campaign / ad set" autocomplete="off" />
        </div>

        <div v-if="showMarketer" class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Marketer</label>
            <select v-model="form.user_id" :class="selectClass">
                <option value="">All marketers</option>
                <option v-for="m in options.marketers || []" :key="m.id" :value="String(m.id)">
                    {{ m.name }} ({{ m.code }})
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">App name (account)</label>
            <select v-model="form.meta_ad_account_id" :class="selectClass">
                <option value="">All app accounts</option>
                <option v-for="a in options.accounts || []" :key="a.id" :value="String(a.id)">
                    {{ a.label }} — {{ a.app_id }}
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">App ID</label>
            <select v-model="form.app_id" :class="selectClass">
                <option value="">All apps</option>
                <option v-for="app in options.app_ids || []" :key="app.value" :value="app.value">
                    {{ app.label }}
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">From date</label>
            <Input v-model="form.from_date" type="date" />
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">To date</label>
            <Input v-model="form.to_date" type="date" />
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Campaign</label>
            <select v-model="form.campaign" :class="selectClass">
                <option value="">All campaigns</option>
                <option v-for="c in options.campaigns || []" :key="c" :value="c">{{ c }}</option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Ad set</label>
            <select v-model="form.adset" :class="selectClass">
                <option value="">All ad sets</option>
                <option v-for="a in options.adsets || []" :key="a" :value="a">{{ a }}</option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Theme</label>
            <select v-model="form.theme" :class="selectClass">
                <option value="">All themes</option>
                <option v-for="t in options.themes || []" :key="t" :value="t">{{ t }}</option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Cause</label>
            <select v-model="form.cause" :class="selectClass">
                <option value="">All causes</option>
                <option v-for="c in options.causes || []" :key="c" :value="c">{{ c }}</option>
            </select>
        </div>

        <div v-if="showMatch" class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Match status</label>
            <select v-model="form.match" :class="selectClass">
                <option value="all">Matched + unmatched</option>
                <option value="matched">Matched only</option>
                <option value="unmatched">Unmatched only</option>
            </select>
        </div>

        <div class="flex items-end gap-2 md:col-span-2">
            <Button type="submit">Apply filters</Button>
            <Button type="button" variant="outline" @click="reset">Reset</Button>
        </div>
    </form>
</template>
