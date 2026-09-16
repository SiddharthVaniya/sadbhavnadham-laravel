<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Info, X } from '@lucide/vue';

const page = usePage();

const visible = ref(false);
const message = ref('');
const tone = ref('success');
const progress = ref(100);

let dismissTimer = null;
let progressTimer = null;

const durationMs = computed(() => {
    if (tone.value === 'error') {
        return 6500;
    }

    if (tone.value === 'warning') {
        return 5500;
    }

    return 4200;
});

const toneStyles = computed(() => {
    if (tone.value === 'error') {
        return {
            wrap: 'border-red-200/80 bg-white text-red-950 shadow-red-500/10 dark:border-red-900/50 dark:bg-zinc-950 dark:text-red-100',
            icon: 'text-red-600 dark:text-red-400',
            bar: 'bg-red-500',
        };
    }

    if (tone.value === 'warning') {
        return {
            wrap: 'border-amber-200/80 bg-white text-amber-950 shadow-amber-500/10 dark:border-amber-900/50 dark:bg-zinc-950 dark:text-amber-100',
            icon: 'text-amber-600 dark:text-amber-400',
            bar: 'bg-amber-500',
        };
    }

    return {
        wrap: 'border-emerald-200/80 bg-white text-emerald-950 shadow-emerald-500/10 dark:border-emerald-900/50 dark:bg-zinc-950 dark:text-emerald-100',
        icon: 'text-emerald-600 dark:text-emerald-400',
        bar: 'bg-emerald-500',
    };
});

const icon = computed(() => {
    if (tone.value === 'error') {
        return AlertCircle;
    }

    if (tone.value === 'warning') {
        return Info;
    }

    return CheckCircle2;
});

const readFlash = (flash) => {
    if (! flash) {
        return null;
    }

    if (flash.error || flash.tone === 'error') {
        return { message: flash.error || flash.status, tone: 'error' };
    }

    if (flash.warning || flash.tone === 'warning') {
        return { message: flash.warning || flash.status, tone: 'warning' };
    }

    const text = flash.status || flash.success;

    if (! text) {
        return null;
    }

    return { message: text, tone: 'success' };
};

const clearTimers = () => {
    if (dismissTimer) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }

    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }
};

const dismiss = () => {
    visible.value = false;
    clearTimers();
};

const show = (payload) => {
    if (! payload?.message) {
        return;
    }

    clearTimers();
    message.value = String(payload.message);
    tone.value = payload.tone || 'success';
    progress.value = 100;
    visible.value = true;

    const startedAt = Date.now();
    const total = durationMs.value;

    progressTimer = setInterval(() => {
        const elapsed = Date.now() - startedAt;
        progress.value = Math.max(0, 100 - (elapsed / total) * 100);

        if (progress.value <= 0) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }, 50);

    dismissTimer = setTimeout(dismiss, total);
};

watch(
    () => page.props.flash,
    (flash) => {
        const payload = readFlash(flash);

        if (payload) {
            show(payload);
        }
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(clearTimers);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-3 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-2 opacity-0"
        >
            <div
                v-if="visible"
                class="pointer-events-none fixed inset-x-3 top-3 z-[200] flex justify-end sm:inset-x-auto sm:right-4 sm:top-4"
                role="status"
                aria-live="polite"
            >
                <div
                    class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border shadow-lg shadow-black/5"
                    :class="toneStyles.wrap"
                >
                    <div class="flex items-start gap-3 px-3.5 py-3">
                        <component :is="icon" class="mt-0.5 size-4 shrink-0" :class="toneStyles.icon" />
                        <p class="min-w-0 flex-1 text-sm leading-5 font-medium">
                            {{ message }}
                        </p>
                        <button
                            type="button"
                            class="rounded-md p-1 text-muted-foreground transition hover:bg-black/5 hover:text-foreground dark:hover:bg-white/10"
                            aria-label="Dismiss notification"
                            @click="dismiss"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <div class="h-0.5 bg-black/5 dark:bg-white/10">
                        <div
                            class="h-full transition-[width] duration-75 ease-linear"
                            :class="toneStyles.bar"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
