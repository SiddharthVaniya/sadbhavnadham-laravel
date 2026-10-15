<script setup>
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { computed, useId } from 'vue';
import { Button } from '@/Components/ui/button';
import { Calendar } from '@/Components/ui/calendar';
import { Label } from '@/Components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { cn } from '@/lib/utils';

const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: 'Pick a date' },
    disabled: { type: Boolean, default: false },
    /** Y-m-d inclusive */
    min: { type: String, default: '' },
    /** Y-m-d inclusive */
    max: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const fieldId = useId();
const timeZone = getLocalTimeZone();
const formatter = new DateFormatter('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const toCalendarDate = (value) => {
    if (! value || typeof value !== 'string') {
        return undefined;
    }

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
};

const selectedDate = computed({
    get: () => toCalendarDate(props.modelValue),
    set: (value) => {
        emit('update:modelValue', value ? value.toString() : '');
    },
});

const minValue = computed(() => toCalendarDate(props.min));
const maxValue = computed(() => toCalendarDate(props.max));
const defaultPlaceholder = computed(() => selectedDate.value ?? today(timeZone));

const displayValue = computed(() => {
    if (! selectedDate.value) {
        return props.placeholder;
    }

    return formatter.format(selectedDate.value.toDate(timeZone));
});
</script>

<template>
    <div class="space-y-2">
        <Label :for="fieldId">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <Popover v-slot="{ close }">
            <PopoverTrigger as-child>
                <Button
                    :id="fieldId"
                    type="button"
                    variant="outline"
                    :disabled="disabled"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="hint || error ? `${fieldId}-desc` : undefined"
                    :class="cn(
                        'h-9 w-full justify-start gap-2 px-3 font-normal',
                        ! selectedDate && 'text-muted-foreground',
                    )"
                >
                    <CalendarIcon class="size-4 shrink-0 opacity-70" />
                    <span class="truncate">{{ displayValue }}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0" align="start">
                <Calendar
                    v-model="selectedDate"
                    :default-placeholder="defaultPlaceholder"
                    layout="month-and-year"
                    :min-value="minValue"
                    :max-value="maxValue"
                    initial-focus
                    @update:model-value="() => close()"
                />
                <div v-if="selectedDate" class="border-t border-border px-3 py-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 w-full text-muted-foreground"
                        @click="selectedDate = undefined; close()"
                    >
                        Clear
                    </Button>
                </div>
            </PopoverContent>
        </Popover>

        <p v-if="hint" :id="`${fieldId}-desc`" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p
            v-if="error"
            :id="hint ? undefined : `${fieldId}-desc`"
            class="text-xs text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
