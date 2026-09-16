<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Activity, HeartHandshake, MapPinned, Radio } from '@lucide/vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/Components/ui/tabs';

const props = defineProps({
    liveVisitors: {
        type: Object,
        default: () => ({ count: 0, visitors: [] }),
    },
    todaysGifts: {
        type: Array,
        default: () => [],
    },
});

const mapEl = ref(null);
const mapReady = ref(false);
const selectedBasemap = ref('light');
const showLive = ref(true);
const showGifts = ref(true);
const feedTab = ref('live');

const basemaps = {
    light: {
        label: 'Clean',
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    },
    voyager: {
        label: 'Streets',
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    },
    dark: {
        label: 'Night',
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    },
};

let map = null;
let baseLayer = null;
let liveLayer = null;
let todayLayer = null;
let pulseTimer = null;

const visitors = computed(() => (props.liveVisitors?.visitors ?? []).filter((row) => row.lat != null && row.lng != null));
const gifts = computed(() => (props.todaysGifts ?? []).filter((row) => row.lat != null && row.lng != null));
const liveCount = computed(() => Number(props.liveVisitors?.count || visitors.value.length || 0));
const giftCount = computed(() => gifts.value.length);
const hasMarkers = computed(() => visitors.value.length > 0 || gifts.value.length > 0);

const relativeTime = (iso) => {
    if (! iso) {
        return '';
    }

    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return '';
    }

    const minutes = Math.max(0, Math.round((Date.now() - then) / 60000));

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    return new Date(iso).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
};

const popupHtml = (title, primary, secondary) => `
    <div class="dash-map-popup">
        <div class="dash-map-popup__eyebrow">${title}</div>
        <div class="dash-map-popup__title">${primary}</div>
        ${secondary ? `<div class="dash-map-popup__meta">${secondary}</div>` : ''}
    </div>
`;

const clearLayers = () => {
    liveLayer?.clearLayers();
    todayLayer?.clearLayers();
};

const fitToMarkers = (points) => {
    if (! map || ! points.length) {
        map?.setView([22.5, 78.9], 4.5);

        return;
    }

    if (points.length === 1) {
        map.setView(points[0], 7);

        return;
    }

    map.fitBounds(points, { padding: [48, 48], maxZoom: 7 });
};

const syncLayerVisibility = () => {
    if (! map || ! liveLayer || ! todayLayer) {
        return;
    }

    if (showLive.value) {
        if (! map.hasLayer(liveLayer)) {
            liveLayer.addTo(map);
        }
    } else if (map.hasLayer(liveLayer)) {
        map.removeLayer(liveLayer);
    }

    if (showGifts.value) {
        if (! map.hasLayer(todayLayer)) {
            todayLayer.addTo(map);
        }
    } else if (map.hasLayer(todayLayer)) {
        map.removeLayer(todayLayer);
    }
};

const renderMarkers = (options = { fit: false }) => {
    if (! map || ! liveLayer || ! todayLayer) {
        return;
    }

    clearLayers();

    const bounds = [];

    visitors.value.forEach((visitor) => {
        const latLng = [visitor.lat, visitor.lng];
        bounds.push(latLng);

        L.circleMarker(latLng, {
            radius: 7,
            color: '#0f766e',
            weight: 2,
            fillColor: '#14b8a6',
            fillOpacity: 0.9,
            className: 'live-visitor-pulse',
        })
            .bindPopup(
                popupHtml(
                    'Live on site',
                    visitor.label || [visitor.city, visitor.country_name].filter(Boolean).join(', ') || 'Location updating',
                    visitor.path || '/',
                ),
            )
            .addTo(liveLayer);
    });

    gifts.value.forEach((gift) => {
        const latLng = [gift.lat, gift.lng];
        bounds.push(latLng);

        L.circleMarker(latLng, {
            radius: 7,
            color: '#b45309',
            weight: 2,
            fillColor: '#f59e0b',
            fillOpacity: 0.92,
        })
            .bindPopup(
                popupHtml(
                    'Donation today',
                    gift.donor_name || 'Donor',
                    [gift.label, relativeTime(gift.paid_at)].filter(Boolean).join(' · '),
                ),
            )
            .addTo(todayLayer);
    });

    syncLayerVisibility();

    if (options.fit) {
        fitToMarkers(bounds);
    }
};

const applyBasemap = (key) => {
    if (! map) {
        return;
    }

    const style = basemaps[key] || basemaps.light;

    if (baseLayer) {
        map.removeLayer(baseLayer);
    }

    baseLayer = L.tileLayer(style.url, {
        attribution: style.attribution,
        subdomains: 'abcd',
        maxZoom: 18,
    }).addTo(map);
};

const focusVisitor = (visitor) => {
    if (! map || visitor?.lat == null || visitor?.lng == null) {
        return;
    }

    map.setView([visitor.lat, visitor.lng], 8, { animate: true });
};

const focusGift = (gift) => {
    if (! map || gift?.lat == null || gift?.lng == null) {
        return;
    }

    map.setView([gift.lat, gift.lng], 8, { animate: true });
};

onMounted(() => {
    if (! mapEl.value) {
        return;
    }

    map = L.map(mapEl.value, {
        zoomControl: false,
        attributionControl: true,
        scrollWheelZoom: true,
        worldCopyJump: true,
    }).setView([22.5, 78.9], 4.5);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    applyBasemap(selectedBasemap.value);

    liveLayer = L.layerGroup().addTo(map);
    todayLayer = L.layerGroup().addTo(map);
    mapReady.value = true;
    renderMarkers({ fit: true });

    pulseTimer = window.setInterval(() => {
        if (! showLive.value) {
            return;
        }

        liveLayer?.eachLayer((layer) => {
            if (typeof layer.setStyle !== 'function') {
                return;
            }

            const current = layer.options.fillOpacity ?? 0.9;
            layer.setStyle({ fillOpacity: current > 0.55 ? 0.35 : 0.95 });
        });
    }, 1000);

    window.setTimeout(() => map?.invalidateSize(), 120);
});

watch([visitors, gifts], () => {
    if (mapReady.value) {
        renderMarkers({ fit: false });
    }
}, { deep: true });

watch(selectedBasemap, (value) => {
    applyBasemap(value);
});

watch([showLive, showGifts], () => {
    syncLayerVisibility();
});

onBeforeUnmount(() => {
    if (pulseTimer) {
        window.clearInterval(pulseTimer);
    }
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card">
        <div class="grid xl:h-[460px] xl:grid-cols-[minmax(0,1.7fr)_minmax(280px,0.9fr)]">
            <div class="relative h-[280px] overflow-hidden border-b border-border sm:h-[320px] xl:h-full xl:rounded-l-xl xl:border-b-0 xl:border-r">
                <div ref="mapEl" class="absolute inset-0 z-0 h-full w-full bg-muted/20" />

                <div class="pointer-events-none absolute left-3 top-3 z-[1100] flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="showLive ? 'default' : 'outline'"
                        class="pointer-events-auto h-8 gap-1.5 bg-background/95 shadow-sm backdrop-blur"
                        :class="showLive ? '!bg-teal-700 !text-white hover:!bg-teal-800' : ''"
                        @click="showLive = ! showLive"
                    >
                        <span class="size-1.5 rounded-full bg-teal-300" />
                        Live
                        <Badge variant="secondary" class="ml-0.5 h-5 px-1.5 tabular-nums">{{ liveCount }}</Badge>
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="showGifts ? 'default' : 'outline'"
                        class="pointer-events-auto h-8 gap-1.5 bg-background/95 shadow-sm backdrop-blur"
                        :class="showGifts ? '!bg-amber-700 !text-white hover:!bg-amber-800' : ''"
                        @click="showGifts = ! showGifts"
                    >
                        <span class="size-1.5 rounded-full bg-amber-300" />
                        Donations
                        <Badge variant="secondary" class="ml-0.5 h-5 px-1.5 tabular-nums">{{ giftCount }}</Badge>
                    </Button>
                </div>

                <div class="pointer-events-none absolute right-3 top-3 z-[1100]">
                    <Select v-model="selectedBasemap">
                        <SelectTrigger class="pointer-events-auto h-8 w-[118px] bg-background/95 text-xs shadow-sm backdrop-blur">
                            <SelectValue placeholder="Style" />
                        </SelectTrigger>
                        <SelectContent position="popper" side="bottom" align="end" :side-offset="6" class="z-[9999]">
                            <SelectItem v-for="(style, key) in basemaps" :key="key" :value="key">
                                {{ style.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div
                    v-if="! hasMarkers"
                    class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-background/35 px-8 text-center backdrop-blur-[1px]"
                >
                    <div class="max-w-sm rounded-xl border border-border/80 bg-card/95 px-5 py-4 shadow-sm">
                        <MapPinned class="mx-auto mb-2 size-5 text-muted-foreground" />
                        <p class="text-sm font-medium text-foreground">Waiting for activity</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                            Live visitors and today’s paid donations appear here at city level when location is available.
                        </p>
                    </div>
                </div>

                <div class="pointer-events-none absolute bottom-3 left-3 z-[1100] hidden rounded-lg border border-border/80 bg-background/90 px-2.5 py-1.5 text-[11px] text-muted-foreground shadow-sm backdrop-blur sm:block">
                    City-level approximate · not GPS
                </div>
            </div>

            <aside class="flex h-[280px] min-h-0 flex-col overflow-hidden bg-card xl:h-full xl:rounded-r-xl">
                <div class="grid shrink-0 grid-cols-2 gap-px border-b border-border bg-border">
                    <div class="bg-card px-4 py-3">
                        <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            <Radio class="size-3.5" />
                            On site
                        </div>
                        <p class="mt-1 text-xl font-semibold tracking-tight tabular-nums xl:text-2xl">{{ liveCount }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Active sessions</p>
                    </div>
                    <div class="bg-card px-4 py-3">
                        <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            <HeartHandshake class="size-3.5" />
                            Today
                        </div>
                        <p class="mt-1 text-xl font-semibold tracking-tight tabular-nums xl:text-2xl">{{ giftCount }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Mapped donations</p>
                    </div>
                </div>

                <Tabs v-model="feedTab" class="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden">
                    <div class="shrink-0 border-b border-border px-3 pt-3 pb-0">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="live" class="gap-1.5">
                                <Activity class="size-3.5" />
                                Visitors
                            </TabsTrigger>
                            <TabsTrigger value="gifts" class="gap-1.5">
                                <HeartHandshake class="size-3.5" />
                                Donations
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="live" class="mt-0 min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 py-2">
                        <button
                            v-for="(visitor, index) in visitors"
                            :key="`${visitor.lat}-${visitor.lng}-${index}`"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-lg px-2.5 py-2.5 text-left transition hover:bg-muted/70"
                            @click="focusVisitor(visitor)"
                        >
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-teal-500 ring-4 ring-teal-500/15" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-foreground">
                                    {{ visitor.label || visitor.city || 'Visitor' }}
                                </span>
                                <span class="mt-0.5 block truncate font-mono text-[11px] text-muted-foreground">
                                    {{ visitor.path || '/' }}
                                </span>
                            </span>
                        </button>
                        <p v-if="! visitors.length" class="px-3 py-10 text-center text-sm text-muted-foreground">
                            No live visitors right now.
                        </p>
                    </TabsContent>

                    <TabsContent value="gifts" class="mt-0 min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 py-2">
                        <button
                            v-for="gift in gifts"
                            :key="gift.id"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-lg px-2.5 py-2.5 text-left transition hover:bg-muted/70"
                            @click="focusGift(gift)"
                        >
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-amber-500 ring-4 ring-amber-500/15" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-foreground">{{ gift.donor_name }}</span>
                                    <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">
                                        {{ relativeTime(gift.paid_at) }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-muted-foreground">{{ gift.label }}</span>
                            </span>
                        </button>
                        <p v-if="! gifts.length" class="px-3 py-10 text-center text-sm text-muted-foreground">
                            No paid donations mapped today yet.
                        </p>
                    </TabsContent>
                </Tabs>

                <div class="hidden shrink-0 border-t border-border px-4 py-2.5 text-[11px] leading-relaxed text-muted-foreground xl:block">
                    Teal = live website visitors (IP / city). Amber = paid donations today. Click a row to focus the map.
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
:deep(.leaflet-container) {
    font: inherit;
    background: hsl(var(--muted) / 0.25);
    z-index: 0;
    cursor: grab;
}

:deep(.leaflet-dragging .leaflet-container) {
    cursor: grabbing;
}

:deep(.leaflet-control-zoom) {
    border: 1px solid hsl(var(--border)) !important;
    border-radius: 0.5rem !important;
    overflow: hidden;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
}

:deep(.leaflet-control-zoom a) {
    width: 30px !important;
    height: 30px !important;
    line-height: 30px !important;
    color: hsl(var(--foreground)) !important;
    background: hsl(var(--background) / 0.95) !important;
}

:deep(.leaflet-control-attribution) {
    font-size: 10px;
    background: hsl(var(--background) / 0.85);
    margin: 0 !important;
}

:deep(.leaflet-popup-content-wrapper) {
    border-radius: 0.75rem;
    border: 1px solid hsl(var(--border));
    box-shadow: 0 8px 24px rgb(0 0 0 / 0.08);
    padding: 0;
}

:deep(.leaflet-popup-content) {
    margin: 0;
    min-width: 160px;
}

:deep(.leaflet-popup-tip) {
    box-shadow: none;
}

:deep(.dash-map-popup) {
    padding: 0.75rem 0.9rem;
}

:deep(.dash-map-popup__eyebrow) {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: hsl(var(--muted-foreground));
}

:deep(.dash-map-popup__title) {
    margin-top: 0.2rem;
    font-size: 13px;
    font-weight: 600;
    color: hsl(var(--foreground));
}

:deep(.dash-map-popup__meta) {
    margin-top: 0.25rem;
    font-size: 11px;
    color: hsl(var(--muted-foreground));
}
</style>
