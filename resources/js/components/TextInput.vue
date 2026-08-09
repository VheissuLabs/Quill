<script setup lang="ts">
    import type { HTMLAttributes } from 'vue'
    import { useTemplateRef } from 'vue'
    import FormField from '@/components/FormField.vue'
    import { Input } from '@/components/ui/input'

    defineOptions({ inheritAttrs: false })

    const props = defineProps<{
        id?: string
        label?: string
        error?: string
        labelClass?: HTMLAttributes['class']
        fieldClass?: HTMLAttributes['class']
        class?: HTMLAttributes['class']
    }>()

    defineSlots<{
        label?(): unknown
        labelAction?(): unknown
        help?(): unknown
    }>()

    const inputRef = useTemplateRef('inputRef')

    defineExpose({
        $el: inputRef,
        focus: () => inputRef.value?.$el?.focus(),
    })
</script>

<template>
    <FormField
        :label="label"
        :error="error"
        :for="props.id"
        :label-class="labelClass"
        :class="fieldClass"
    >
        <template v-if="$slots.label" #label>
            <slot name="label" />
        </template>

        <template v-if="$slots.labelAction" #labelAction>
            <slot name="labelAction" />
        </template>

        <template v-if="$slots.help" #help>
            <slot name="help" />
        </template>

        <Input
            ref="inputRef"
            :id="props.id"
            :class="props.class"
            v-bind="$attrs"
        />
    </FormField>
</template>
