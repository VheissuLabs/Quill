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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/teams/members';
import type { Team, TeamMember } from '@/types';

type Props = {
    organization: string;
    team: Team;
    availableMembers: TeamMember[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const userId = ref('');
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        userId.value = '';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="store.form([props.organization, props.team.slug])"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Add a team member</DialogTitle>
                    <DialogDescription>
                        Only members of the organization can join a team.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="user_id">Organization member</Label>
                    <Select
                        v-model="userId"
                        name="user_id"
                        data-test="add-team-member-select"
                    >
                        <SelectTrigger id="user_id" class="w-full">
                            <SelectValue placeholder="Select a member" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="member in props.availableMembers"
                                :key="member.id"
                                :value="String(member.id)"
                            >
                                {{ member.name }}
                                <span class="text-muted-foreground">
                                    ({{ member.email }})
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="props.availableMembers.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Every organization member is already on this team.
                    </p>
                    <InputError :message="errors.user_id" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="add-team-member-submit"
                        :disabled="processing || userId === ''"
                    >
                        Add member
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
