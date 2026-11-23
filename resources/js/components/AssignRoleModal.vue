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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updateMember } from '@/routes/organizations/members';
import type {
    Organization,
    OrganizationRoleItem,
    RoleCandidate,
} from '@/types';

type Props = {
    organization: Organization;
    role: OrganizationRoleItem;
    candidates: RoleCandidate[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const userId = ref('');
const processing = ref(false);

const handleOpenChange = (value: boolean) => {
    emit('update:open', value);

    if (!value) {
        userId.value = '';
    }
};

/**
 * A member holds exactly one role per organization, so giving them this role replaces the one they have.
 */
const assign = () => {
    router.visit(
        updateMember([props.organization.slug, Number(userId.value)]),
        {
            data: { role_id: props.role.id },
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => handleOpenChange(false),
        },
    );
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="assign">
                <DialogHeader>
                    <DialogTitle
                        >Give someone the {{ role.name }} role</DialogTitle
                    >
                    <DialogDescription>
                        Members hold one role each, so this replaces the role
                        they have now.
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="props.candidates.length === 0"
                    class="text-muted-foreground text-sm"
                    data-test="assign-role-nobody"
                >
                    Everyone in the organization already holds this role. Invite
                    someone by email to give them this role.
                </p>

                <div v-else class="grid gap-2">
                    <Label for="assign-role-member">Organization member</Label>
                    <Select v-model="userId">
                        <SelectTrigger
                            id="assign-role-member"
                            class="w-full"
                            data-test="assign-role-select"
                        >
                            <SelectValue placeholder="Select a member" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="candidate in props.candidates"
                                :key="candidate.id"
                                :value="String(candidate.id)"
                            >
                                {{ candidate.name }}
                                <span class="text-muted-foreground">
                                    ({{ candidate.role ?? 'no role' }})
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        v-if="props.candidates.length > 0"
                        type="submit"
                        data-test="assign-role-submit"
                        :disabled="processing || userId === ''"
                    >
                        Give role
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
