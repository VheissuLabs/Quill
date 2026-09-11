<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center rounded-xl border border-dashed px-6 py-14 text-center',
                props.class,
            )
        "
    >
        <div
            class="bg-muted mb-4 flex size-12 items-center justify-center rounded-xl"
        >
            <component :is="icon" class="text-muted-foreground size-6" />
        </div>
        <p class="font-medium">{{ title }}</p>
        <p
            v-if="description"
            class="text-muted-foreground mt-1 max-w-sm text-sm"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
