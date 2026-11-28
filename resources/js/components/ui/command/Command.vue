<script setup lang="ts">
import type { ListboxRootEmits, ListboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { useTemplateRef } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ListboxRoot, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ListboxRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ListboxRootEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)

const root = useTemplateRef<{ highlightFirstItem: () => void }>("root")

defineExpose({
  highlightFirstItem: () => root.value?.highlightFirstItem(),
})
</script>

<template>
  <ListboxRoot
    ref="root"
    data-slot="command"
    v-bind="forwarded"
    :class="cn('bg-popover text-popover-foreground flex h-full w-full flex-col overflow-hidden rounded-md', props.class)"
  >
    <slot />
  </ListboxRoot>
</template>
