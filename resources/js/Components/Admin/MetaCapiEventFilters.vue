<script setup>
import { reactive, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
});

const emit = defineEmits(['apply', 'reset']);

const form = reactive({
    meta_pixel_id: props.filters.meta_pixel_id ?? '',
    event_name: props.filters.event_name ?? '',
    partner_user_id: props.filters.partner_user_id ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    status: props.filters.status ?? '',
});

watch(
    () => props.filters,
    (next) => {
        form.meta_pixel_id = next.meta_pixel_id ?? '';
        form.event_name = next.event_name ?? '';
        form.partner_user_id = next.partner_user_id ?? '';
        form.from_date = next.from_date ?? '';
        form.to_date = next.to_date ?? '';
        form.status = next.status ?? '';
    },
    { deep: true },
);

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

const apply = () => emit('apply', { ...form });

const reset = () => {
    form.meta_pixel_id = '';
    form.event_name = '';
    form.partner_user_id = '';
    form.from_date = '';
    form.to_date = '';
    form.status = '';
    emit('reset', { ...form });
};
</script>

<template>
    <form class="grid gap-3 md:grid-cols-3 xl:grid-cols-4" @submit.prevent="apply">
        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Pixel</label>
            <select v-model="form.meta_pixel_id" :class="selectClass">
                <option value="">All pixels</option>
                <option v-for="p in options.pixels || []" :key="p.id" :value="String(p.id)">
                    {{ p.label }} ({{ p.pixel_id }})
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Event</label>
            <select v-model="form.event_name" :class="selectClass">
                <option value="">All events</option>
                <option v-for="e in options.events || []" :key="e.value" :value="e.value">
                    {{ e.label }}
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Marketer (sid)</label>
            <select v-model="form.partner_user_id" :class="selectClass">
                <option value="">All marketers</option>
                <option v-for="m in options.marketers || []" :key="m.id" :value="String(m.id)">
                    {{ m.code }} — {{ m.name }}
                </option>
            </select>
        </div>

        <div class="space-y-1">
            <label class="text-xs font-medium text-muted-foreground">Status</label>
            <select v-model="form.status" :class="selectClass">
                <option value="">All statuses</option>
                <option v-for="s in options.statuses || []" :key="s.value" :value="s.value">
                    {{ s.label }}
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

        <div class="flex items-end gap-2 md:col-span-2 xl:col-span-2">
            <Button type="submit">Apply filters</Button>
            <Button type="button" variant="outline" @click="reset">Reset</Button>
        </div>
    </form>
</template>
