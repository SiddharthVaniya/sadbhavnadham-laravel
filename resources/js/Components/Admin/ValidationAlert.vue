<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';

const page = usePage();

const messages = computed(() => {
    const errors = page.props.errors ?? {};

    return Object.entries(errors)
        .flatMap(([, value]) => {
            if (Array.isArray(value)) {
                return value.filter(Boolean);
            }

            return value ? [String(value)] : [];
        })
        .filter(Boolean);
});

const hasErrors = computed(() => messages.value.length > 0);
</script>

<template>
    <Alert v-if="hasErrors" variant="destructive" class="mb-4 border-destructive/30 bg-destructive/5">
        <AlertCircle class="size-4" />
        <AlertTitle>Please check the form</AlertTitle>
        <AlertDescription>
            <ul class="mt-1.5 list-disc space-y-1 pl-4">
                <li v-for="(message, index) in messages" :key="`${index}-${message}`">
                    {{ message }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
