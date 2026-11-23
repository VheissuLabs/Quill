<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        defaultValue?: string | null;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
    }>(),
    {
        id: undefined,
        name: undefined,
        defaultValue: null,
        placeholder: 'Pick a date',
        required: false,
        disabled: false,
    },
);

const model = defineModel<string | null>();

const open = ref(false);
const value = ref<string>(model.value ?? props.defaultValue ?? '');

watch(model, (next) => {
    value.value = next ?? '';
});

const formatter = new DateFormatter('en-US', { dateStyle: 'medium' });

const selected = computed<DateValue | undefined>(() => {
    try {
        return value.value ? parseDate(value.value) : undefined;
    } catch {
        return undefined;
    }
});

const label = computed(() =>
    selected.value
        ? formatter.format(selected.value.toDate(getLocalTimeZone()))
        : props.placeholder,
);

const choose = (date: DateValue | undefined) => {
    value.value = date?.toString() ?? '';
    model.value = value.value;
    open.value = false;
};
</script>

<template>
    <div>
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    :id="id"
                    type="button"
                    variant="outline"
                    :disabled="disabled"
                    class="w-full justify-start font-normal"
                    :class="selected ? '' : 'text-muted-foreground'"
                >
                    <CalendarIcon />
                    {{ label }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0" align="start">
                <Calendar
                    :model-value="selected"
                    :default-placeholder="selected"
                    layout="month-and-year"
                    initial-focus
                    @update:model-value="
                        choose($event as DateValue | undefined)
                    "
                />
                <div v-if="selected && !required" class="border-t p-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        @click="choose(undefined)"
                    >
                        Clear
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
        <input v-if="name" type="hidden" :name="name" :value="value" />
    </div>
</template>
