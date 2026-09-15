<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { ref } from 'vue';
import AddTeamMemberModal from '@/components/AddTeamMemberModal.vue';
import BoardColumnsEditor from '@/components/BoardColumnsEditor.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { destroy, edit, index, update } from '@/routes/teams';
import { destroy as removeMember } from '@/routes/teams/members';
import type {
    Organization,
    OrganizationPermissions,
    SelectOption,
    Team,
    TeamBoardColumn,
    TeamMember,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    team: Team;
    members: TeamMember[];
    availableMembers: TeamMember[];
    columns: TeamBoardColumn[];
    columnStatuses: SelectOption[];
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization; team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.team.name,
                href: edit([props.currentOrganization.slug, props.team.slug]),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const addMemberDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};
</script>

<template>
    <Head :title="team.name" />

    <div class="flex flex-col gap-10 p-6">
        <div class="max-w-xl space-y-6">
            <Heading variant="small" title="Team settings" />

            <Form
                v-if="permissions.canManageTeams"
                v-bind="update.form([currentOrganization.slug, team.slug])"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Team name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button
                    type="submit"
                    data-test="team-save-button"
                    :disabled="processing"
                >
                    Save
                </Button>
            </Form>
            <p v-else class="font-medium">{{ team.name }}</p>
        </div>

        <div class="max-w-xl space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Team members"
                    description="Organization members who belong to this team"
                />

                <Button
                    v-if="permissions.canManageTeams"
                    data-test="add-team-member-button"
                    @click="addMemberDialogOpen = true"
                >
                    <UserPlus /> Add member
                </Button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="team-member-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-10 w-10">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium">{{ member.name }}</div>
                            <div class="text-muted-foreground text-sm">
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <TooltipProvider v-if="permissions.canManageTeams">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-member-remove-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmRemoveMember(member)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Remove from team</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <p
                    v-if="members.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    This team has no members yet.
                </p>
            </div>
        </div>

        <div
            v-if="
                currentOrganization.modules.development.enabled &&
                currentOrganization.modules.development.capabilities.workflow
            "
            class="max-w-2xl space-y-6"
        >
            <Heading
                variant="small"
                title="Board columns"
                description="The stages this team moves work through. Each column counts as Todo, In progress or Done."
            />
            <BoardColumnsEditor
                :organization="currentOrganization.slug"
                :team="team"
                :columns="columns"
                :statuses="columnStatuses"
                :can-manage="permissions.canManageTeams"
            />
        </div>

        <div v-if="permissions.canManageTeams" class="max-w-xl space-y-6">
            <Heading
                variant="small"
                title="Delete team"
                description="Boards restricted to this team become visible to every organization member"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                >
                    Delete team
                </Button>
            </div>
        </div>
    </div>

    <AddTeamMemberModal
        v-if="permissions.canManageTeams"
        v-model:open="addMemberDialogOpen"
        :organization="currentOrganization.slug"
        :team="team"
        :available-members="availableMembers"
    />

    <ConfirmDeleteModal
        v-model:open="removeMemberDialogOpen"
        title="Remove from team"
        confirm-label="Remove member"
        :href="
            memberToRemove
                ? removeMember([
                      currentOrganization.slug,
                      team.slug,
                      memberToRemove.id,
                  ])
                : null
        "
    >
        Remove <strong>{{ memberToRemove?.name }}</strong> from {{ team.name }}?
        They stay a member of the organization.
    </ConfirmDeleteModal>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete team"
        confirm-label="Delete team"
        :href="destroy([currentOrganization.slug, team.slug])"
    >
        This will delete <strong>{{ team.name }}</strong
        >. Its members stay in the organization.
    </ConfirmDeleteModal>
</template>
