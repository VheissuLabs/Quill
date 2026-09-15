<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronUp,
    FolderKanban,
    Globe,
    Lightbulb,
    Lock,
    Plus,
    ThumbsUp,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import FeatureStatusBadge from '@/components/FeatureStatusBadge.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { dashboard } from '@/routes';
import { show as showFeature } from '@/routes/portal/features';
import { show as showBoard } from '@/routes/portal/projects';
import { index as projectsIndex } from '@/routes/projects';
import type {
    DashboardInvitation,
    DashboardProject,
    DashboardStats,
    Organization,
    OrganizationPermissions,
    RecentFeature,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    pendingInvitations?: DashboardInvitation[];
    projects: DashboardProject[];
    recentFeatures: RecentFeature[];
    stats: DashboardStats;
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();
const featureVotingEnabled = computed(
    () =>
        props.currentOrganization.modules.featureVoting.enabled &&
        props.currentOrganization.modules.featureVoting.capabilities.boards,
);

defineOptions({
    layout: (props: { currentOrganization?: Organization | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentOrganization
                    ? dashboard(props.currentOrganization.slug)
                    : '/',
            },
        ],
    }),
});

const statCards = computed(() => [
    {
        label: 'Boards',
        value: props.stats.projectsCount,
        icon: FolderKanban,
    },
    {
        label: 'Open requests',
        value: props.stats.openFeaturesCount,
        icon: Lightbulb,
    },
    {
        label: 'Votes cast',
        value: props.stats.votesCount,
        icon: ThumbsUp,
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ currentOrganization.name }}
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    <template v-if="featureVotingEnabled">
                        What people are asking for across your boards.
                    </template>
                    <template v-else>
                        Your organization can enable the tools it needs.
                    </template>
                </p>
            </div>
            <Button
                v-if="featureVotingEnabled && permissions.canManageProjects"
                variant="outline"
                as-child
            >
                <Link :href="projectsIndex(currentOrganization.slug)">
                    Manage projects
                </Link>
            </Button>
        </div>

        <template v-if="featureVotingEnabled && projects.length > 0">
            <div
                class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px] 2xl:grid-cols-[minmax(0,1fr)_440px]"
            >
                <div class="flex flex-col gap-8">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div
                            v-for="stat in statCards"
                            :key="stat.label"
                            class="bg-card flex items-center gap-4 rounded-xl border p-5 shadow-sm"
                        >
                            <div
                                class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg"
                            >
                                <component
                                    :is="stat.icon"
                                    class="text-muted-foreground size-5"
                                />
                            </div>
                            <div class="min-w-0">
                                <dt
                                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                                >
                                    {{ stat.label }}
                                </dt>
                                <dd
                                    class="mt-1 text-2xl font-semibold tabular-nums"
                                >
                                    {{ stat.value }}
                                </dd>
                            </div>
                        </div>
                    </dl>

                    <section class="space-y-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <h2 class="font-semibold">Boards</h2>
                            <span
                                class="text-muted-foreground text-sm tabular-nums"
                            >
                                {{ projects.length }}
                                {{ projects.length === 1 ? 'board' : 'boards' }}
                            </span>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            <Link
                                v-for="project in projects"
                                :key="project.id"
                                :href="
                                    showBoard([
                                        currentOrganization.slug,
                                        project.slug,
                                    ])
                                "
                                data-test="dashboard-project"
                                class="group bg-card hover:border-foreground/20 flex flex-col gap-3 rounded-xl border p-5 shadow-sm transition-all hover:shadow-md"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ project.name }}
                                        </p>
                                        <p
                                            v-if="project.clientName"
                                            class="text-muted-foreground truncate text-sm"
                                        >
                                            {{ project.clientName }}
                                        </p>
                                    </div>
                                    <Badge variant="secondary" class="gap-1">
                                        <Globe
                                            v-if="
                                                project.visibility === 'public'
                                            "
                                        />
                                        <Lock v-else />
                                        {{ project.visibilityLabel }}
                                    </Badge>
                                </div>
                                <p
                                    v-if="project.description"
                                    class="text-muted-foreground line-clamp-2 text-sm"
                                >
                                    {{ project.description }}
                                </p>
                                <div
                                    class="text-muted-foreground mt-auto flex items-center justify-between gap-3 text-sm tabular-nums"
                                >
                                    <p>
                                        <span
                                            class="text-foreground font-medium"
                                        >
                                            {{ project.openFeaturesCount }} open
                                        </span>
                                        · {{ project.featuresCount }} total
                                    </p>
                                    <span
                                        class="text-foreground flex items-center gap-1 text-xs font-medium opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                                    >
                                        Open board
                                        <ArrowRight class="size-3.5" />
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>

                <section class="space-y-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 class="font-semibold">Recent requests</h2>
                        <span class="text-muted-foreground text-sm">
                            Newest first
                        </span>
                    </div>
                    <ol
                        v-if="recentFeatures.length > 0"
                        class="bg-card divide-y overflow-hidden rounded-xl border shadow-sm"
                    >
                        <li
                            v-for="feature in recentFeatures"
                            :key="feature.id"
                            class="hover:bg-accent/40 flex items-start gap-3 p-4 transition-colors"
                        >
                            <span
                                class="bg-muted text-muted-foreground flex w-10 shrink-0 flex-col items-center rounded-md py-1.5 text-xs font-medium tabular-nums"
                            >
                                <ChevronUp class="size-3.5" />
                                {{ feature.votesCount }}
                            </span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <Link
                                    :href="
                                        showFeature([
                                            currentOrganization.slug,
                                            feature.project.slug,
                                            feature.id,
                                        ])
                                    "
                                    class="line-clamp-2 text-sm font-medium hover:underline"
                                >
                                    {{ feature.title }}
                                </Link>
                                <div
                                    class="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-xs"
                                >
                                    <span>{{ feature.project.name }}</span>
                                    <span>·</span>
                                    <span>{{
                                        formatDate(feature.createdAt)
                                    }}</span>
                                    <FeatureStatusBadge
                                        v-if="feature.status !== 'open'"
                                        :status="feature.status"
                                        :label="feature.statusLabel"
                                    />
                                </div>
                            </div>
                        </li>
                    </ol>
                    <p
                        v-else
                        class="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm"
                    >
                        No requests yet. Share a board link to get the first
                        one.
                    </p>
                </section>
            </div>
        </template>

        <EmptyState
            v-else-if="!featureVotingEnabled"
            :icon="FolderKanban"
            title="Feature voting is turned off"
            description="An organization owner can enable feature voting from organization settings."
        />

        <EmptyState
            v-else
            :icon="FolderKanban"
            title="No boards yet"
            :description="
                permissions.canManageProjects
                    ? 'Create a project to open a feature board. Every project gets a board where people request and vote.'
                    : 'Boards will show up here once an admin creates a project you can see.'
            "
        >
            <Button v-if="permissions.canManageProjects" as-child>
                <Link :href="projectsIndex(currentOrganization.slug)">
                    <Plus /> Create the first project
                </Link>
            </Button>
        </EmptyState>
    </div>
</template>
