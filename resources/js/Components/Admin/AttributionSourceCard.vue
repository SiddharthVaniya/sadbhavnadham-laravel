<script setup>
import { computed } from 'vue';

const props = defineProps({
    source: { type: Object, default: () => ({}) },
    emptyMessage: { type: String, default: 'No UTM or referrer captured.' },
});

const resolved = computed(() => ({
    channel: null,
    channel_label: 'Unknown',
    attr_source_label: null,
    utm_source: null,
    utm_medium: null,
    utm_campaign: null,
    utm_content: null,
    utm_term: null,
    attr_source: null,
    attr_medium: null,
    attr_platform: null,
    attr_placement: null,
    partner_code: null,
    partner_name: null,
    meta_campaign_id: null,
    meta_adset_id: null,
    meta_ad_id: null,
    referrer: null,
    referrer_host: null,
    landing_path: null,
    device_type: null,
    ip_address: null,
    ip_country_code: null,
    ip_country_name: null,
    ip_region_name: null,
    ip_city: null,
    ip_lat: null,
    ip_lng: null,
    campaign_name: null,
    ...props.source,
}));

const hasMarketingSource = computed(() => Boolean(
    resolved.value.attr_source_label
    || resolved.value.utm_source
    || resolved.value.utm_medium
    || resolved.value.utm_campaign
    || resolved.value.utm_content
    || resolved.value.utm_term
    || resolved.value.referrer
    || resolved.value.landing_path
    || resolved.value.campaign_name
    || resolved.value.partner_code
    || resolved.value.meta_campaign_id
    || resolved.value.ip_address
    || resolved.value.ip_city
    || resolved.value.ip_country_code,
));
</script>

<template>
    <section class="min-w-0 rounded-xl border border-border bg-card p-4 shadow-none sm:p-5">
        <h3 class="text-sm font-semibold text-foreground">Source</h3>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Channel</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.channel_label }}</dd>
            </div>
            <div v-if="resolved.campaign_name" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Campaign</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.campaign_name }}</dd>
            </div>
            <div v-if="resolved.attr_source_label" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Traffic source</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.attr_source_label }}</dd>
            </div>
            <div v-if="resolved.partner_code" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Partner</dt>
                <dd class="min-w-0 break-words text-right font-medium">
                    {{ resolved.partner_name || 'Unregistered' }}
                    <span class="font-mono text-xs text-muted-foreground">({{ resolved.partner_code }})</span>
                </dd>
            </div>
            <div v-if="resolved.meta_campaign_id" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Meta campaign ID</dt>
                <dd class="min-w-0 break-all text-right font-mono text-xs font-medium">{{ resolved.meta_campaign_id }}</dd>
            </div>
            <div v-if="resolved.meta_adset_id" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Meta ad set ID</dt>
                <dd class="min-w-0 break-all text-right font-mono text-xs font-medium">{{ resolved.meta_adset_id }}</dd>
            </div>
            <div v-if="resolved.meta_ad_id" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Meta ad ID</dt>
                <dd class="min-w-0 break-all text-right font-mono text-xs font-medium">{{ resolved.meta_ad_id }}</dd>
            </div>
            <div v-if="resolved.utm_source" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">UTM source</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.utm_source }}</dd>
            </div>
            <div v-if="resolved.utm_medium" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">UTM medium</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.utm_medium }}</dd>
            </div>
            <div v-if="resolved.utm_campaign" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">UTM campaign</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.utm_campaign }}</dd>
            </div>
            <div v-if="resolved.utm_content" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Employee / content</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.utm_content }}</dd>
            </div>
            <div v-if="resolved.utm_term" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">UTM term (ad set)</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.utm_term }}</dd>
            </div>
            <div v-if="resolved.attr_placement" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Placement</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ resolved.attr_placement }}</dd>
            </div>
            <div v-if="resolved.referrer_host || resolved.referrer" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Referrer</dt>
                <dd class="min-w-0 max-w-[65%] break-all text-right font-medium" :title="resolved.referrer || ''">
                    {{ resolved.referrer_host || resolved.referrer }}
                </dd>
            </div>
            <div v-if="resolved.landing_path" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Landing</dt>
                <dd class="min-w-0 max-w-[65%] break-all text-right font-medium" :title="resolved.landing_path">
                    {{ resolved.landing_path }}
                </dd>
            </div>
            <div v-if="resolved.device_type" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">Device</dt>
                <dd class="min-w-0 break-words text-right font-medium capitalize">{{ resolved.device_type }}</dd>
            </div>
            <div v-if="resolved.ip_address" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">IP</dt>
                <dd class="min-w-0 break-all text-right font-mono text-xs font-medium">{{ resolved.ip_address }}</dd>
            </div>
            <div v-if="resolved.ip_city || resolved.ip_region_name || resolved.ip_country_name || resolved.ip_country_code" class="flex min-w-0 items-start justify-between gap-3">
                <dt class="shrink-0 text-muted-foreground">IP location</dt>
                <dd class="min-w-0 break-words text-right font-medium">
                    {{
                        [resolved.ip_city, resolved.ip_region_name, resolved.ip_country_name || resolved.ip_country_code]
                            .filter(Boolean)
                            .join(', ')
                    }}
                </dd>
            </div>
        </dl>
        <p v-if="! hasMarketingSource" class="mt-3 text-xs text-muted-foreground">
            {{ emptyMessage }}
        </p>
    </section>
</template>
