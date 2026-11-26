<script setup lang="ts">
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    title: string;
    description?: string;
    destructive?: boolean;
}>();
</script>

<template>
    <Card :class="destructive ? 'border-red-200 dark:border-red-900/50' : ''">
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description || $slots.description">
                <slot name="description">{{ description }}</slot>
            </CardDescription>
            <CardAction v-if="$slots.action">
                <slot name="action" />
            </CardAction>
        </CardHeader>
        <CardContent v-if="$slots.default" class="space-y-6 empty:hidden">
            <slot />
        </CardContent>
        <CardFooter v-if="$slots.footer" class="justify-end gap-2">
            <slot name="footer" />
        </CardFooter>
    </Card>
</template>
