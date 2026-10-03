<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Send, X } from '@lucide/vue';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    open: { type: Boolean, default: false },
    donation: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);

const emitLast = () => {
    const notes = data.value.notes ?? [];
    const last = notes[notes.length - 1];
    emit('saved', last
        ? { speaker: last.speaker, name: last.telecaller_name, message: last.message, at: last.created_at }
        : null);
};

const speaker = ref('telecaller');
const message = ref('');
const outcome = ref('');
const followUpAt = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const data = ref({ notes: [], outcomes: [] });
const scroller = ref(null);

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const baseUrl = computed(() => `/admin/donations/${props.donation?.uuid}/telecaller-notes`);

const request = async (method, body) => {
    const response = await fetch(baseUrl.value, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(json.message || 'Something went wrong.');
    }

    return json;
};

const scrollToEnd = async () => {
    await nextTick();
    if (scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        data.value = await request('GET');
        await scrollToEnd();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
};

watch(() => props.open, (isOpen) => {
    if (isOpen && props.donation) {
        speaker.value = 'telecaller';
        message.value = '';
        outcome.value = '';
        followUpAt.value = '';
        data.value = { notes: [], outcomes: [] };
        load();
    }
});

const send = async () => {
    if (!message.value.trim() || saving.value) {
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        data.value = await request('POST', {
            speaker: speaker.value,
            message: message.value,
            outcome: outcome.value || null,
            follow_up_at: followUpAt.value || null,
        });
        message.value = '';
        outcome.value = '';
        followUpAt.value = '';
        emitLast();
        await scrollToEnd();
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
};

const onKeydown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
};

const donorName = computed(() => data.value.donor_name || props.donation?.donor_name || 'Donor');
const isDonor = (note) => note.speaker === 'donor';
const showDay = (index) => index === 0
    || data.value.notes[index].created_date !== data.value.notes[index - 1].created_date;
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
            <div class="flex h-[80vh] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-background shadow-xl">
                <div class="flex items-start justify-between border-b px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold">Telecaller conversation</h2>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ donorName }}
                            <template v-if="data.donor_phone"> · {{ data.donor_phone }}</template>
                            <template v-if="data.amount"> · ₹{{ Number(data.amount).toLocaleString('en-IN') }}</template>
                            <template v-if="data.status"> · {{ data.status }}</template>
                        </p>
                    </div>
                    <Button variant="ghost" size="icon-sm" aria-label="Close" @click="emit('close')">
                        <X class="size-4" />
                    </Button>
                </div>

                <div ref="scroller" class="flex-1 space-y-2 overflow-y-auto bg-muted/30 px-4 py-3">
                    <p v-if="loading" class="text-center text-sm text-muted-foreground">Loading…</p>
                    <p v-else-if="!data.notes.length" class="text-center text-sm text-muted-foreground">
                        No conversation yet. Add the first note below.
                    </p>
                    <template v-for="(note, index) in data.notes" :key="note.id">
                        <div v-if="showDay(index)" class="py-1 text-center text-[11px] text-muted-foreground">{{ note.created_date }}</div>
                        <div class="flex" :class="isDonor(note) ? 'justify-start' : 'justify-end'">
                            <div
                                class="max-w-[80%] rounded-2xl px-3 py-2 text-sm"
                                :class="isDonor(note) ? 'rounded-bl-sm bg-white text-foreground shadow-sm' : 'rounded-br-sm bg-emerald-600 text-white'"
                            >
                                <div class="text-[11px] font-semibold opacity-80">
                                    {{ isDonor(note) ? donorName : (note.telecaller_name || 'Telecaller') }}
                                </div>
                                <div class="whitespace-pre-wrap break-words">{{ note.message }}</div>
                                <div v-if="note.outcome_label || note.follow_up_at" class="mt-1 flex flex-wrap gap-1">
                                    <span v-if="note.outcome_label" class="rounded-full bg-black/10 px-2 py-0.5 text-[10px] font-medium">{{ note.outcome_label }}</span>
                                    <span v-if="note.follow_up_at" class="rounded-full bg-black/10 px-2 py-0.5 text-[10px] font-medium">Follow-up: {{ note.follow_up_at }}</span>
                                </div>
                                <div class="mt-0.5 text-right text-[10px] opacity-70">{{ note.created_at }}</div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-2 border-t px-4 py-3">
                    <p v-if="error" class="text-xs text-rose-600">{{ error }}</p>
                    <div class="flex gap-1 rounded-lg bg-muted p-1 text-xs font-medium">
                        <button
                            type="button"
                            class="flex-1 rounded-md px-2 py-1"
                            :class="speaker === 'telecaller' ? 'bg-emerald-600 text-white' : 'text-muted-foreground'"
                            @click="speaker = 'telecaller'"
                        >
                            I said
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-md px-2 py-1"
                            :class="speaker === 'donor' ? 'bg-foreground text-background' : 'text-muted-foreground'"
                            @click="speaker = 'donor'"
                        >
                            Donor said
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <select v-model="outcome" class="h-8 flex-1 rounded-md border bg-background px-2 text-xs">
                            <option value="">Outcome (optional)</option>
                            <option v-for="o in data.outcomes" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <input v-model="followUpAt" type="datetime-local" class="h-8 flex-1 rounded-md border bg-background px-2 text-xs" title="Follow-up (optional)">
                    </div>
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="message"
                            rows="2"
                            maxlength="2000"
                            class="flex-1 resize-none rounded-md border bg-background px-3 py-2 text-sm"
                            :placeholder="speaker === 'telecaller' ? 'What did you tell the donor?' : 'What did the donor say?'"
                            @keydown="onKeydown"
                        />
                        <Button size="icon" :disabled="saving || !message.trim()" aria-label="Send" @click="send">
                            <Send class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
