<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  colspan?: number
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr>
    <td
      data-slot="table-empty"
      :colspan="props.colspan ?? 1"
      :class="cn('text-muted-foreground py-10 text-center text-sm', props.class)"
    >
      <slot />
    </td>
  </tr>
</template>
