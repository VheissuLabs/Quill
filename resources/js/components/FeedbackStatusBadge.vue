<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { FeedbackStatus } from '@/types';

defineProps<{
    status: FeedbackStatus;
    label: string;
}>();

const statusClasses: Record<FeedbackStatus, string> = {
    open: 'border-transparent bg-secondary text-secondary-foreground',
    planned:
        'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    in_progress:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
    completed:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
    declined: 'text-muted-foreground',
};
</script>

<template>
    <Badge variant="outline" :class="statusClasses[status]">
        {{ label }}
    </Badge>
</template>
