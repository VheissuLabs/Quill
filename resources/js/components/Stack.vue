<script setup lang="ts">
    import { computed } from 'vue'
    import type { HTMLAttributes } from 'vue'
    import { cn } from '@/lib/utils'

    type Gap = '1' | '2' | '3' | '4' | '6' | '8'

    const props = withDefaults(
        defineProps<{
            gap?: Gap
            class?: HTMLAttributes['class']
        }>(),
        { gap: '6' },
    )

    // Spelled out so Tailwind can see the class names.
    const gaps: Record<Gap, string> = {
        '1': 'gap-1',
        '2': 'gap-2',
        '3': 'gap-3',
        '4': 'gap-4',
        '6': 'gap-6',
        '8': 'gap-8',
    }

    const gapClass = computed(() => gaps[props.gap])
</script>

<template>
    <div :class="cn('grid', gapClass, props.class)">
        <slot />
    </div>
</template>
