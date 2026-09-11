<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { IssueStatus } from '@/types';

defineProps<{
    status: IssueStatus;
    label: string;
}>();

const statusClasses: Record<IssueStatus, string> = {
    backlog: 'text-muted-foreground',
    todo: 'border-transparent bg-secondary text-secondary-foreground',
    in_progress:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
    done: 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
    cancelled: 'text-muted-foreground line-through',
};
</script>

<template>
    <Badge variant="outline" :class="statusClasses[status]">
        {{ label }}
    </Badge>
</template>
