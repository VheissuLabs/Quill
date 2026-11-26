<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ChevronDown, UserPlus, X } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { ref } from 'vue';
import AddTeamMemberModal from '@/components/AddTeamMemberModal.vue';
import BoardColumnsEditor from '@/components/BoardColumnsEditor.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import SectionCard from '@/components/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { destroy as removeMember } from '@/routes/teams/members';
import { update as updateModules } from '@/routes/teams/modules';
import { mutate } from '@/lib/optimistic';
import type {
    Organization,
    OrganizationPermissions,
    SelectOption,
    Team,
    TeamBoardColumn,
    TeamMember,
    TeamMergeTarget,
} from '@/types';
import EmailLink from '@/components/EmailLink.vue';

type Props = {
    currentOrganization: Organization;
    team: Team;
    modules: { sprints: boolean };
    organizationModules: { sprints: boolean };
    members: TeamMember[];
    availableMembers: TeamMember[];
    columns: TeamBoardColumn[];
    columnStatuses: SelectOption[];
    otherTeams: TeamMergeTarget[];
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

/**
 * Each section remembers whether it is open in this browser, matching organization settings.
 */
const settingsOpen = useLocalStorage('quill.team-settings.open', true);
const membersOpen = useLocalStorage('quill.team-members.open', true);
const columnsOpen = useLocalStorage('quill.team-columns.open', true);
const modulesOpen = useLocalStorage('quill.team-modules.open', true);
const deleteOpen = useLocalStorage('quill.team-delete.open', true);

const toggleSprints = (enabled: boolean) => {
    mutate(updateModules([props.currentOrganization.slug, props.team.slug]), {
        data: { sprints_enabled: enabled },
        errorMessage: 'That module could not be updated.',
        optimistic: () => ({ modules: { sprints: enabled } }),
    });
};

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

    <div class="grid max-w-7xl items-start gap-6 p-6 lg:grid-cols-2">
        <Collapsible v-model:open="settingsOpen">
            <SectionCard
                title="Team settings"
                description="The name of this team"
            >
                <template #action>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="team-settings-toggle"
                            :aria-label="settingsOpen ? 'Collapse' : 'Expand'"
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="settingsOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </template>

                <CollapsibleContent class="space-y-6">
                    <Form
                        v-if="permissions.canManageTeams"
                        v-bind="
                            update.form([currentOrganization.slug, team.slug])
                        "
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
                </CollapsibleContent>
            </SectionCard>
        </Collapsible>

        <Collapsible v-model:open="membersOpen">
            <SectionCard
                title="Team members"
                description="Organization members who belong to this team"
            >
                <template #action>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="team-members-toggle"
                            :aria-label="membersOpen ? 'Collapse' : 'Expand'"
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="membersOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </template>

                <CollapsibleContent class="space-y-6">
                    <Button
                        v-if="permissions.canManageTeams"
                        data-test="add-team-member-button"
                        @click="addMemberDialogOpen = true"
                    >
                        <UserPlus /> Add member
                    </Button>
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
                                    <div class="font-medium">
                                        {{ member.name }}
                                    </div>
                                    <div class="text-muted-foreground text-sm">
                                        <EmailLink :email="member.email" />
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
                </CollapsibleContent>
            </SectionCard>
        </Collapsible>

        <Collapsible
            v-if="
                currentOrganization.modules.development.enabled &&
                currentOrganization.modules.development.capabilities.workflow
            "
            v-model:open="columnsOpen"
        >
            <SectionCard
                title="Board columns"
                description="The stages this team moves work through. Each column counts as Todo, In progress or Done."
            >
                <template #action>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="team-columns-toggle"
                            :aria-label="columnsOpen ? 'Collapse' : 'Expand'"
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="columnsOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </template>

                <CollapsibleContent class="space-y-6">
                    <BoardColumnsEditor
                        :organization="currentOrganization.slug"
                        :team="team"
                        :columns="columns"
                        :statuses="columnStatuses"
                        :can-manage="permissions.canManageTeams"
                    />
                </CollapsibleContent>
            </SectionCard>
        </Collapsible>

        <Collapsible
            v-if="permissions.canManageTeams && organizationModules.sprints"
            v-model:open="modulesOpen"
        >
            <SectionCard
                title="Modules"
                description="Switch off anything this team does not use. The organization's own settings still apply on top."
            >
                <template #action>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="team-modules-toggle"
                            :aria-label="modulesOpen ? 'Collapse' : 'Expand'"
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="modulesOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </template>

                <CollapsibleContent class="space-y-6">
                    <div
                        class="flex items-start justify-between gap-6 rounded-lg border p-4"
                    >
                        <div class="space-y-1">
                            <Label
                                for="team-sprints"
                                class="text-sm font-medium"
                            >
                                Sprints
                            </Label>
                            <p class="text-muted-foreground text-sm">
                                Plan this team's work in time-boxed sprints.
                                With this off, the team keeps its board and
                                backlog.
                            </p>
                        </div>
                        <Switch
                            id="team-sprints"
                            data-test="team-sprints-toggle"
                            :model-value="modules.sprints"
                            @update:model-value="toggleSprints"
                        />
                    </div>
                </CollapsibleContent>
            </SectionCard>
        </Collapsible>

        <Collapsible
            v-if="permissions.canManageTeams"
            v-model:open="deleteOpen"
        >
            <SectionCard
                title="Delete team"
                description="Boards restricted to this team become visible to every organization member"
            >
                <template #action>
                    <CollapsibleTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="team-delete-toggle"
                            :aria-label="deleteOpen ? 'Collapse' : 'Expand'"
                        >
                            <ChevronDown
                                class="transition-transform"
                                :class="deleteOpen && 'rotate-180'"
                            />
                        </Button>
                    </CollapsibleTrigger>
                </template>

                <CollapsibleContent class="space-y-6">
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
                </CollapsibleContent>
            </SectionCard>
        </Collapsible>
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

    <DeleteTeamModal
        v-model:open="deleteDialogOpen"
        :organization="currentOrganization"
        :team="team"
        :other-teams="otherTeams"
    />
</template>
