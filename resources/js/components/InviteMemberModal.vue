<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/organizations/invitations';
import type { RoleOption, Organization } from '@/types';

type Props = {
    organization: Organization;
    availableRoles: RoleOption[];
    open: boolean;
    defaultRole?: number | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const defaultRole = () =>
    String(props.defaultRole ?? props.availableRoles[0]?.value ?? '');
const inviteRole = ref(defaultRole());
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        inviteRole.value = defaultRole();
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(props.organization.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Invite an organization member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to join this organization.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            data-test="invite-email"
                            type="email"
                            placeholder="colleague@example.com"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <Select
                            v-model="inviteRole"
                            name="role_id"
                            data-test="invite-role"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in props.availableRoles"
                                    :key="role.value"
                                    :value="String(role.value)"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role_id" />
                        <p
                            v-if="props.availableRoles.length === 0"
                            class="text-muted-foreground text-sm"
                            data-test="invite-no-roles"
                        >
                            This organization has no roles to grant yet. Create
                            one before inviting people.
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="invite-submit"
                        :disabled="
                            processing || props.availableRoles.length === 0
                        "
                    >
                        Send invitation
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
