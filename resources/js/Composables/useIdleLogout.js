import { onMounted, onUnmounted, unref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const ACTIVITY_EVENTS = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart', 'visibilitychange'];

/**
 * Signs the user out after idleMinutes of browser idle time.
 * Server session lifetime should match (SESSION_LIFETIME).
 *
 * @param {string} logoutUrl
 * @param {import('vue').Ref<number>|number} idleMinutes
 */
export function useIdleLogout(logoutUrl = '/admin/logout', idleMinutes = 30) {
    let timer = null;
    let loggingOut = false;

    const idleMs = () => Math.max(1, Number(unref(idleMinutes) || 30)) * 60 * 1000;

    const clearTimer = () => {
        if (timer) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const performLogout = () => {
        if (loggingOut) {
            return;
        }

        loggingOut = true;
        clearTimer();
        router.post(logoutUrl, {}, {
            preserveScroll: false,
            onFinish: () => {
                loggingOut = false;
            },
        });
    };

    const resetTimer = () => {
        if (document.visibilityState === 'hidden') {
            return;
        }

        clearTimer();
        timer = window.setTimeout(performLogout, idleMs());
    };

    const onActivity = () => {
        resetTimer();
    };

    onMounted(() => {
        ACTIVITY_EVENTS.forEach((eventName) => {
            window.addEventListener(eventName, onActivity, { passive: true });
        });
        resetTimer();
    });

    onUnmounted(() => {
        clearTimer();
        ACTIVITY_EVENTS.forEach((eventName) => {
            window.removeEventListener(eventName, onActivity);
        });
    });

    watch(() => unref(idleMinutes), () => {
        resetTimer();
    });
}
