<script setup lang="ts">
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
import { leave as leaveOrganizationAction } from '@/routes/organizations';
import type { Organization } from '@/types';

type Props = {
    organization: Organization | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

const leaveOrganization = () => {
    if (!props.organization) {
        return;
    }

    router.visit(leaveOrganizationAction(props.organization.slug), {
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
                <DialogTitle>Leave organization</DialogTitle>
                <DialogDescription>
                    Are you sure you want to leave
                    <strong>{{ props.organization?.name }}</strong
                    >?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary"> Cancel </Button>
                </DialogClose>

                <Button
                    data-test="leave-organization-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="leaveOrganization"
                >
                    Leave organization
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
