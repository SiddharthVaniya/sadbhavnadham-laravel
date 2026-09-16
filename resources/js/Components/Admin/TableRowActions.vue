<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, Clipboard, Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    editHref: { type: String, default: '' },
    copyUrl: { type: String, default: '' },
    copyLabel: { type: String, default: 'Copy link' },
    showEdit: { type: Boolean, default: true },
    showDelete: { type: Boolean, default: true },
});

defineEmits(['delete']);

const copied = ref(false);

const copyLink = async () => {
    if (! props.copyUrl) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.copyUrl);
        } else {
            const input = document.createElement('textarea');
            input.value = props.copyUrl;
            input.setAttribute('readonly', '');
            input.style.position = 'absolute';
            input.style.left = '-9999px';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        window.prompt('Copy this link:', props.copyUrl);
    }
};
</script>

<template>
    <div class="inline-flex items-center gap-1">
        <Button
            v-if="copyUrl"
            type="button"
            variant="ghost"
            size="icon-sm"
            :class="copied ? 'text-emerald-600' : 'text-muted-foreground'"
            :title="copied ? 'Copied!' : copyLabel"
            :aria-label="copied ? 'Copied' : copyLabel"
            @click="copyLink"
        >
            <Check v-if="copied" class="size-4" />
            <Clipboard v-else class="size-4" />
        </Button>
        <Button v-if="showEdit && editHref" as-child variant="ghost" size="icon-sm" class="text-muted-foreground">
            <Link :href="editHref" title="Edit" aria-label="Edit">
                <Pencil class="size-4" />
            </Link>
        </Button>
        <Button
            v-if="showDelete"
            type="button"
            variant="ghost"
            size="icon-sm"
            class="text-destructive hover:text-destructive"
            title="Delete"
            aria-label="Delete"
            @click="$emit('delete')"
        >
            <Trash2 class="size-4" />
        </Button>
    </div>
</template>
