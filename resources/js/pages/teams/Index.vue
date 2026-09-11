<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Users } from '@lucide/vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/teams';
import type {
    Organization,
    OrganizationPermissions,
    TeamListItem,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    teams: TeamListItem[];
    permissions: OrganizationPermissions;
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});
</script>

<template>
    <Head title="Teams" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Teams"
                description="The departments of your organization"
            />

            <CreateTeamModal
                v-if="permissions.canManageTeams"
                :organization="currentOrganization.slug"
            >
                <Button data-test="teams-new-team-button">
                    <Plus /> New team
                </Button>
            </CreateTeamModal>
        </div>

        <div class="space-y-3">
            <div
                v-for="team in teams"
                :key="team.id"
                data-test="team-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div>
                    <div class="font-medium">{{ team.name }}</div>
                    <span class="text-muted-foreground text-sm">
                        {{ team.membersCount }}
                        {{ team.membersCount === 1 ? 'member' : 'members' }}
                    </span>
                </div>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                data-test="team-edit-button"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        edit([
                                            currentOrganization.slug,
                                            team.slug,
                                        ])
                                    "
                                >
                                    <Pencil
                                        v-if="permissions.canManageTeams"
                                        class="h-4 w-4"
                                    />
                                    <Eye v-else class="h-4 w-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>
                                {{
                                    permissions.canManageTeams
                                        ? 'Edit team'
                                        : 'View team'
                                }}
                            </p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <EmptyState
                v-if="teams.length === 0"
                :icon="Users"
                title="No teams yet"
                :description="
                    permissions.canManageTeams
                        ? 'Teams are your departments: Marketing, Development, Legal. Private boards can be restricted to them.'
                        : 'Teams will show up here once an admin creates one.'
                "
            >
                <CreateTeamModal
                    v-if="permissions.canManageTeams"
                    :organization="currentOrganization.slug"
                >
                    <Button variant="outline"><Plus /> New team</Button>
                </CreateTeamModal>
            </EmptyState>
        </div>
    </div>
</template>
