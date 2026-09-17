<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ListboxContent } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <ListboxContent
    data-slot="command-list"
    :class="cn('max-h-80 scroll-py-1 overflow-x-hidden overflow-y-auto p-1', props.class)"
  >
    <slot />
  </ListboxContent>
</template>
