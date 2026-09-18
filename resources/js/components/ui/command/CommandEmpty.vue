<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="command-empty"
    class="py-6 text-center text-sm"
    :class="cn('text-muted-foreground', props.class)"
  >
    <slot />
  </div>
</template>
