<script setup lang="ts">
import {
    CircleDashed,
    SignalHigh,
    SignalLow,
    SignalMedium,
    TriangleAlert,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { IssuePriority } from '@/types';

defineProps<{
    priority: IssuePriority;
    label: string;
    showLabel?: boolean;
}>();

const icons: Record<IssuePriority, LucideIcon> = {
    none: CircleDashed,
    low: SignalLow,
    medium: SignalMedium,
    high: SignalHigh,
    urgent: TriangleAlert,
};

const classes: Record<IssuePriority, string> = {
    none: 'text-muted-foreground/50',
    low: 'text-muted-foreground',
    medium: 'text-foreground',
    high: 'text-foreground',
    urgent: 'text-red-600 dark:text-red-400',
};
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <span
                    :class="
                        cn(
                            'inline-flex items-center gap-1 text-xs',
                            classes[priority],
                        )
                    "
                >
                    <component :is="icons[priority]" class="size-3.5" />
                    <span v-if="showLabel">{{ label }}</span>
                    <span v-else class="sr-only">Priority: {{ label }}</span>
                </span>
            </TooltipTrigger>
            <TooltipContent>Priority: {{ label }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
