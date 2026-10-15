<script setup>
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
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
    /** When true, modelValue is `Y-m-d H:i` and a time picker is shown. */
    includeTime: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const fieldId = useId();
const timeZone = getLocalTimeZone();
const dateFormatter = new DateFormatter('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});
const dateTimeFormatter = new DateFormatter('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

const pad2 = (value) => String(value).padStart(2, '0');

const splitModelValue = (value) => {
    if (! value || typeof value !== 'string') {
        return { date: '', hour: '00', minute: '00' };
    }

    const trimmed = value.trim();
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?/);

    if (! match) {
        return { date: '', hour: '00', minute: '00' };
    }

    return {
        date: match[1],
        hour: match[2] ?? '00',
        minute: match[3] ?? '00',
    };
};

const toCalendarDate = (value) => {
    const datePart = typeof value === 'string' && value.includes(' ')
        ? value.slice(0, 10)
        : value;

    if (! datePart || typeof datePart !== 'string') {
        return undefined;
    }

    try {
        return parseDate(datePart.slice(0, 10));
    } catch {
        return undefined;
    }
};

const currentTimeParts = () => {
    const now = new Date();

    return {
        hour: pad2(now.getHours()),
        minute: pad2(now.getMinutes()),
    };
};

const parts = computed(() => splitModelValue(props.modelValue));
const hour = ref(parts.value.hour);
const minute = ref(parts.value.minute);

watch(
    () => props.modelValue,
    (value) => {
        const next = splitModelValue(value);
        hour.value = next.hour;
        minute.value = next.minute;
    },
);

const emitCombined = (datePart, nextHour = hour.value, nextMinute = minute.value) => {
    if (! datePart) {
        emit('update:modelValue', '');

        return;
    }

    if (! props.includeTime) {
        emit('update:modelValue', datePart);

        return;
    }

    const safeHour = pad2(Math.min(23, Math.max(0, Number.parseInt(nextHour, 10) || 0)));
    const safeMinute = pad2(Math.min(59, Math.max(0, Number.parseInt(nextMinute, 10) || 0)));

    emit('update:modelValue', `${datePart} ${safeHour}:${safeMinute}`);
};

const selectedDate = computed({
    get: () => toCalendarDate(parts.value.date),
    set: (value) => {
        if (! value) {
            emitCombined('');

            return;
        }

        const time = props.includeTime && ! parts.value.date
            ? currentTimeParts()
            : { hour: hour.value, minute: minute.value };

        if (props.includeTime && ! parts.value.date) {
            hour.value = time.hour;
            minute.value = time.minute;
        }

        emitCombined(value.toString(), time.hour, time.minute);
    },
});

const minValue = computed(() => toCalendarDate(props.min));
const maxValue = computed(() => toCalendarDate(props.max));
const defaultPlaceholder = computed(() => selectedDate.value ?? today(timeZone));

const displayValue = computed(() => {
    if (! selectedDate.value) {
        return props.placeholder;
    }

    if (! props.includeTime) {
        return dateFormatter.format(selectedDate.value.toDate(timeZone));
    }

    const [year, month, day] = parts.value.date.split('-').map(Number);
    const date = new Date(
        year,
        month - 1,
        day,
        Number.parseInt(hour.value, 10) || 0,
        Number.parseInt(minute.value, 10) || 0,
    );

    return dateTimeFormatter.format(date);
});

const onTimeChange = () => {
    if (! parts.value.date) {
        return;
    }

    emitCombined(parts.value.date, hour.value, minute.value);
};

const hourOptions = Array.from({ length: 24 }, (_, index) => pad2(index));
const minuteOptions = Array.from({ length: 60 }, (_, index) => pad2(index));
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
                    @update:model-value="() => { if (! includeTime) close(); }"
                />
                <div v-if="includeTime" class="flex items-center gap-2 border-t border-border px-3 py-2">
                    <label class="text-xs font-medium text-muted-foreground" :for="`${fieldId}-hour`">Time</label>
                    <select
                        :id="`${fieldId}-hour`"
                        v-model="hour"
                        class="h-8 rounded-md border border-border bg-background px-2 text-sm"
                        @change="onTimeChange"
                    >
                        <option v-for="option in hourOptions" :key="`h-${option}`" :value="option">{{ option }}</option>
                    </select>
                    <span class="text-sm text-muted-foreground">:</span>
                    <select
                        :id="`${fieldId}-minute`"
                        v-model="minute"
                        class="h-8 rounded-md border border-border bg-background px-2 text-sm"
                        @change="onTimeChange"
                    >
                        <option v-for="option in minuteOptions" :key="`m-${option}`" :value="option">{{ option }}</option>
                    </select>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="ml-auto h-8"
                        :disabled="! selectedDate"
                        @click="close()"
                    >
                        Done
                    </Button>
                </div>
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
