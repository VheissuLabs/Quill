<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    title: string;
    confirmLabel: string;
    href: NonNullable<InertiaLinkProps['href']>;
    destructive?: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

const confirm = () => {
    router.visit(props.href, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription>
                    <slot />
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary"> Cancel </Button>
                </DialogClose>

                <Button
                    data-test="confirm-action"
                    :variant="props.destructive ? 'destructive' : 'default'"
                    :disabled="processing"
                    @click="confirm"
                >
                    {{ props.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
