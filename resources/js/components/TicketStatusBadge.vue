<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { TicketStatus } from '@/types';

defineProps<{
    status: TicketStatus;
    label: string;
}>();

const variants: Record<TicketStatus, 'default' | 'secondary' | 'outline'> = {
    open: 'default',
    awaiting_customer: 'secondary',
    resolved: 'outline',
    closed: 'outline',
};
</script>

<template>
    <Badge :variant="variants[status]" data-test="ticket-status">
        {{ label }}
    </Badge>
</template>
