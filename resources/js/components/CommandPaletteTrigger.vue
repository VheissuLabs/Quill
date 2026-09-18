<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed } from 'vue';
import { useCommandPalette } from '@/composables/useCommandPalette';

const { show } = useCommandPalette();

const isApple = computed(
    () =>
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent),
);
</script>

<template>
    <button
        type="button"
        data-test="command-palette-trigger"
        aria-label="Search"
        class="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
        @click="show"
    >
        <Search class="size-4 shrink-0" />
        <span class="hidden sm:inline">Search</span>
        <kbd
            class="bg-muted text-muted-foreground ml-2 hidden rounded px-1.5 py-0.5 text-[10px] font-medium tabular-nums sm:inline"
        >
            {{ isApple ? '⌘' : 'Ctrl ' }}K
        </kbd>
    </button>
</template>
