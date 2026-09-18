<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Search } from "@lucide/vue"
import { ListboxFilter } from "reka-ui"
import { cn } from "@/lib/utils"

defineProps<{ class?: HTMLAttributes["class"], placeholder?: string }>()

const model = defineModel<string>({ default: "" })
</script>

<template>
  <div data-slot="command-input-wrapper" class="flex h-12 items-center gap-2 border-b px-3">
    <Search class="size-4 shrink-0 opacity-50" />
    <ListboxFilter
      v-model="model"
      data-slot="command-input"
      auto-focus
      :placeholder="placeholder"
      :class="cn('placeholder:text-muted-foreground flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden disabled:cursor-not-allowed disabled:opacity-50', $props.class)"
    />
  </div>
</template>
