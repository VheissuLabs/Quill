<script setup lang="ts">
    import { computed } from 'vue'
    import type { HTMLAttributes } from 'vue'
    import InputError from '@/components/InputError.vue'
    import { Label } from '@/components/ui/label'
    import { cn } from '@/lib/utils'

    const props = defineProps<{
        label?: string
        error?: string
        for?: string
        labelClass?: HTMLAttributes['class']
        class?: HTMLAttributes['class']
    }>()

    const slots = defineSlots<{
        default(): unknown
        label?(): unknown
        labelAction?(): unknown
        help?(): unknown
    }>()

    const hasLabel = computed(
        () => Boolean(props.label) || Boolean(slots.label),
    )
</script>

<template>
    <div :class="cn('grid gap-2', props.class)">
        <div
            v-if="hasLabel && $slots.labelAction"
            class="flex items-center justify-between"
        >
            <Label :for="props.for" :class="labelClass">
                <slot name="label">{{ label }}</slot>
            </Label>
            <slot name="labelAction" />
        </div>

        <Label v-else-if="hasLabel" :for="props.for" :class="labelClass">
            <slot name="label">{{ label }}</slot>
        </Label>

        <slot />

        <slot name="help" />

        <InputError :message="error" />
    </div>
</template>
