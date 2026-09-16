<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    action: { type: String, required: true },
    duration: { type: String, required: true },
    durationOptions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    variant: { type: String, default: 'tracking' },
});

const isDonations = computed(() => props.variant === 'donations' || props.variant === 'donation-list');
const isDonationList = computed(() => props.variant === 'donation-list');
const isCampaigns = computed(() => props.variant === 'campaigns');
const isTracking = computed(() => ! isDonations.value && ! isCampaigns.value);
const showDeviceFilter = computed(() => isDonationList.value || isDonations.value || isCampaigns.value || isTracking.value);
const isCustomRange = computed(() => form.duration === 'custom');

const resolveInitialDuration = () => {
    if (props.filters?.from_date && props.filters?.to_date) {
        return 'custom';
    }

    return props.duration;
};

const emptyFilters = () => {
    if (isDonationList.value) {
        return {
            from_date: '',
            to_date: '',
            utm_campaign: '',
            utm_medium: '',
            utm_content: '',
            cause: '',
            title: '',
            city: '',
            state: '',
            device_type: '',
        };
    }

    if (isDonations.value || isCampaigns.value) {
        return {
            from_date: '',
            to_date: '',
            device_type: '',
        };
    }

    return {
        from_date: '',
        to_date: '',
        utm_source: '',
        utm_medium: '',
        utm_campaign: '',
        utm_content: '',
        utm_id: '',
        utm_term: '',
        device_type: '',
        result: '',
        page_path: '',
        referrer: '',
        aid: '',
    };
};

const form = reactive({
    duration: resolveInitialDuration(),
    ...emptyFilters(),
    ...Object.fromEntries(
        Object.keys(emptyFilters()).map((key) => [key, props.filters?.[key] || '']),
    ),
});

const seedCustomDates = () => {
    if (form.from_date || form.to_date) {
        return;
    }

    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    form.from_date = start.toISOString().slice(0, 10);
    form.to_date = now.toISOString().slice(0, 10);
};

const queryParams = () => {
    const params = { duration: form.duration };

    Object.keys(emptyFilters()).forEach((key) => {
        if (form[key]) {
            params[key] = form[key];
        }
    });

    if (form.duration !== 'custom') {
        delete params.from_date;
        delete params.to_date;
    }

    return params;
};

const applyFilters = () => {
    router.get(props.action, queryParams(), {
        preserveState: ! isCampaigns.value,
        preserveScroll: true,
        replace: true,
    });
};

const onDurationChange = () => {
    if (form.duration === 'custom') {
        seedCustomDates();
        return;
    }

    form.from_date = '';
    form.to_date = '';
    applyFilters();
};

const clearFilters = () => {
    form.duration = '30d';
    Object.assign(form, emptyFilters());
    applyFilters();
};

const hasActiveFilters = computed(() => {
    if (form.duration !== '30d') {
        return true;
    }

    return Object.keys(emptyFilters()).some((key) => Boolean(form[key]));
});

const optionsFor = (key) => {
    const options = [...(props.filterOptions?.[key] || [])];
    const selected = form[key];

    if (selected && ! options.includes(selected)) {
        options.unshift(selected);
    }

    return options;
};

const deviceLabel = (value) => {
    const labels = {
        mobile: 'Mobile',
        tablet: 'Tablet',
        desktop: 'PC',
        unknown: 'Unknown',
    };

    return labels[value] || value;
};
</script>

<template>
    <form
        class="mb-6 grid gap-3 rounded-2xl border border-border bg-card p-4 md:grid-cols-2 xl:grid-cols-4"
        :class="{ 'xl:grid-cols-4': isDonations || isCampaigns }"
        @submit.prevent="applyFilters"
    >
        <div>
            <label class="admin-label !mb-1 !text-xs">Period</label>
            <select v-model="form.duration" class="admin-input !py-2" @change="onDurationChange">
                <option v-for="(label, value) in durationOptions" :key="value" :value="value">
                    {{ label }}
                </option>
            </select>
        </div>
        <div v-if="isCustomRange">
            <label class="admin-label !mb-1 !text-xs">Start date</label>
            <input v-model="form.from_date" type="date" class="admin-input !py-2">
        </div>
        <div v-if="isCustomRange">
            <label class="admin-label !mb-1 !text-xs">End date</label>
            <input v-model="form.to_date" type="date" class="admin-input !py-2">
        </div>
        <div v-if="showDeviceFilter && ! isTracking">
            <label class="admin-label !mb-1 !text-xs">Device</label>
            <select v-model="form.device_type" class="admin-input !py-2">
                <option value="">All devices</option>
                <option v-for="option in optionsFor('device_type')" :key="option" :value="option">
                    {{ deviceLabel(option) }}
                </option>
            </select>
        </div>
        <div v-if="isTracking">
            <label class="admin-label !mb-1 !text-xs">Source</label>
            <select v-model="form.utm_source" class="admin-input !py-2">
                <option value="">All sources</option>
                <option v-for="option in optionsFor('utm_source')" :key="option" :value="option">{{ option }}</option>
            </select>
        </div>
        <template v-if="isDonationList">
            <div>
                <label class="admin-label !mb-1 !text-xs">Campaign</label>
                <select v-model="form.utm_campaign" class="admin-input !py-2">
                    <option value="">All campaigns</option>
                    <option v-for="option in optionsFor('utm_campaign')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Medium</label>
                <select v-model="form.utm_medium" class="admin-input !py-2">
                    <option value="">All mediums</option>
                    <option v-for="option in optionsFor('utm_medium')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Ad</label>
                <select v-model="form.utm_content" class="admin-input !py-2">
                    <option value="">All ads</option>
                    <option v-for="option in optionsFor('utm_content')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Cause</label>
                <select v-model="form.cause" class="admin-input !py-2">
                    <option value="">All causes</option>
                    <option v-for="option in optionsFor('cause')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Title</label>
                <select v-model="form.title" class="admin-input !py-2">
                    <option value="">All titles</option>
                    <option v-for="option in optionsFor('title')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">City</label>
                <select v-model="form.city" class="admin-input !py-2">
                    <option value="">All cities</option>
                    <option v-for="option in optionsFor('city')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">State</label>
                <select v-model="form.state" class="admin-input !py-2">
                    <option value="">All states</option>
                    <option v-for="option in optionsFor('state')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
        </template>
        <template v-if="isTracking">
            <div>
                <label class="admin-label !mb-1 !text-xs">Medium</label>
                <select v-model="form.utm_medium" class="admin-input !py-2">
                    <option value="">All mediums</option>
                    <option v-for="option in optionsFor('utm_medium')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Campaign</label>
                <select v-model="form.utm_campaign" class="admin-input !py-2">
                    <option value="">All campaigns</option>
                    <option v-for="option in optionsFor('utm_campaign')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Content</label>
                <select v-model="form.utm_content" class="admin-input !py-2">
                    <option value="">All content</option>
                    <option v-for="option in optionsFor('utm_content')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Campaign ID</label>
                <select v-model="form.utm_id" class="admin-input !py-2">
                    <option value="">All campaign IDs</option>
                    <option v-for="option in optionsFor('utm_id')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Ad set ID</label>
                <select v-model="form.utm_term" class="admin-input !py-2">
                    <option value="">All ad set IDs</option>
                    <option v-for="option in optionsFor('utm_term')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Ad ID</label>
                <select v-model="form.aid" class="admin-input !py-2">
                    <option value="">All ad IDs</option>
                    <option v-for="option in optionsFor('aid')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Device</label>
                <select v-model="form.device_type" class="admin-input !py-2">
                    <option value="">All devices</option>
                    <option v-for="option in optionsFor('device_type')" :key="option" :value="option">
                        {{ deviceLabel(option) }}
                    </option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Result</label>
                <select v-model="form.result" class="admin-input !py-2">
                    <option value="">All results</option>
                    <option value="donated">Donated</option>
                    <option value="unique">Unique click</option>
                    <option value="repeat">Repeat click</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Page</label>
                <select v-model="form.page_path" class="admin-input !py-2">
                    <option value="">All pages</option>
                    <option v-for="option in optionsFor('page_path')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <div>
                <label class="admin-label !mb-1 !text-xs">Referrer</label>
                <select v-model="form.referrer" class="admin-input !py-2">
                    <option value="">All referrers</option>
                    <option v-for="option in optionsFor('referrer')" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
        </template>
        <div class="flex flex-wrap items-end gap-2">
            <Button type="submit" size="sm">Filter</Button>
            <Button
                v-if="hasActiveFilters"
                type="button"
                size="sm"
                variant="outline"
                @click="clearFilters"
            >
                Clear
            </Button>
        </div>
    </form>
</template>
