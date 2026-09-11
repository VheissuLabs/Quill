<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Timer } from '@lucide/vue';
import { computed } from 'vue';
import CreateSprintModal from '@/components/CreateSprintModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { index, show } from '@/routes/sprints';
import { index as teamsIndex } from '@/routes/teams';
import type {
    Organization,
    OrganizationPermissions,
    SprintListItem,
    SprintStatus,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    sprints: SprintListItem[];
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Sprints',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam);

const groups = computed(() =>
    (
        [
            ['active', 'Active'],
            ['planned', 'Planned'],
            ['completed', 'Completed'],
        ] as [SprintStatus, string][]
    )
        .map(([status, title]) => ({
            status,
            title,
            sprints: props.sprints.filter((sprint) => sprint.status === status),
        }))
        .filter((group) => group.sprints.length > 0),
);

const progress = (sprint: SprintListItem) =>
    sprint.issuesCount === 0
        ? 0
        : Math.round((sprint.doneIssuesCount / sprint.issuesCount) * 100);

const formatRange = (start: string, end: string) => {
    const options: Intl.DateTimeFormatOptions = {
        month: 'short',
        day: 'numeric',
    };

    return `${new Date(start).toLocaleDateString(undefined, options)} – ${new Date(end).toLocaleDateString(undefined, options)}`;
};
</script>

<template>
    <Head title="Sprints" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Sprints"
                :description="
                    currentTeam
                        ? `Time boxes ${currentTeam.name} commits to`
                        : 'Time boxes your teams commit to'
                "
            />

            <CreateSprintModal
                v-if="permissions.canManageSprints && currentTeam"
                :organization="currentOrganization.slug"
                :team="currentTeam.name"
            >
                <Button data-test="sprints-new-sprint-button">
                    <Plus /> New sprint
                </Button>
            </CreateSprintModal>
        </div>

        <template v-if="sprints.length > 0">
            <section
                v-for="group in groups"
                :key="group.status"
                class="space-y-3"
            >
                <h2
                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >
                    {{ group.title }}
                </h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="sprint in group.sprints"
                        :key="sprint.id"
                        :href="show([currentOrganization.slug, sprint.id])"
                        data-test="sprint-card"
                        class="bg-card hover:border-foreground/20 flex flex-col gap-3 rounded-xl border p-5 transition-colors"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ sprint.name }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ sprint.team.name }} ·
                                    {{
                                        formatRange(
                                            sprint.startsAt,
                                            sprint.endsAt,
                                        )
                                    }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    sprint.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ sprint.statusLabel }}
                            </Badge>
                        </div>
                        <div class="mt-auto space-y-1.5">
                            <div
                                class="bg-muted h-1.5 overflow-hidden rounded-full"
                            >
                                <div
                                    class="bg-foreground h-full rounded-full transition-[width]"
                                    :style="{ width: `${progress(sprint)}%` }"
                                />
                            </div>
                            <p
                                class="text-muted-foreground text-xs tabular-nums"
                            >
                                {{ sprint.doneIssuesCount }} of
                                {{ sprint.issuesCount }} done
                            </p>
                        </div>
                    </Link>
                </div>
            </section>
        </template>

        <EmptyState
            v-else
            :icon="Timer"
            title="No sprints yet"
            :description="
                !currentTeam
                    ? 'Sprints belong to teams. Create a team first, then plan its first sprint.'
                    : permissions.canManageSprints
                      ? `Create a sprint for ${currentTeam.name} and plan issues from the backlog into it.`
                      : 'Sprints will show up here once an admin creates one.'
            "
        >
            <Button
                v-if="!currentTeam && permissions.canManageTeams"
                variant="outline"
                as-child
            >
                <Link :href="teamsIndex(currentOrganization.slug)">
                    Go to teams
                </Link>
            </Button>
            <CreateSprintModal
                v-else-if="currentTeam && permissions.canManageSprints"
                :organization="currentOrganization.slug"
                :team="currentTeam.name"
            >
                <Button variant="outline"><Plus /> New sprint</Button>
            </CreateSprintModal>
        </EmptyState>
    </div>
</template>
