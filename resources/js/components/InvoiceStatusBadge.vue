<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { InvoiceStatus } from '@/types';

defineProps<{
    status: InvoiceStatus;
    label: string;
}>();

const statusClasses: Record<InvoiceStatus, string> = {
    draft: 'text-muted-foreground',
    sent: 'border-transparent bg-secondary text-secondary-foreground',
    partial:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
    paid: 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
    overdue:
        'border-transparent bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-200',
    void: 'text-muted-foreground line-through',
};
</script>

<template>
    <Badge variant="outline" :class="statusClasses[status]">
        {{ label }}
    </Badge>
</template>
