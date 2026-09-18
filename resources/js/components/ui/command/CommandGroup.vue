<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ListboxGroup, ListboxGroupLabel } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"], heading?: string }>()
</script>

<template>
  <ListboxGroup
    data-slot="command-group"
    :class="cn('text-foreground overflow-hidden p-1', props.class)"
  >
    <ListboxGroupLabel
      v-if="heading"
      class="text-muted-foreground px-2 py-1.5 text-xs font-medium"
    >
      {{ heading }}
    </ListboxGroupLabel>
    <slot />
  </ListboxGroup>
</template>
