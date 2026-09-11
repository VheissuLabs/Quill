<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronUp, FolderKanban, Globe, Lock, Plus } from '@lucide/vue';
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

defineProps<Props>();

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
                    What people are asking for across your boards.
                </p>
            </div>
            <Button
                v-if="permissions.canManageProjects"
                variant="outline"
                as-child
            >
                <Link :href="projectsIndex(currentOrganization.slug)">
                    Manage projects
                </Link>
            </Button>
        </div>

        <template v-if="projects.length > 0">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border p-5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Boards
                    </dt>
                    <dd class="mt-2 text-3xl font-semibold tabular-nums">
                        {{ stats.projectsCount }}
                    </dd>
                </div>
                <div class="rounded-xl border p-5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Open requests
                    </dt>
                    <dd class="mt-2 text-3xl font-semibold tabular-nums">
                        {{ stats.openFeaturesCount }}
                    </dd>
                </div>
                <div class="rounded-xl border p-5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Votes cast
                    </dt>
                    <dd class="mt-2 text-3xl font-semibold tabular-nums">
                        {{ stats.votesCount }}
                    </dd>
                </div>
            </dl>

            <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
                <section class="space-y-4">
                    <h2 class="font-semibold">Boards</h2>
                    <div class="grid gap-4 md:grid-cols-2">
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
                            class="bg-card hover:border-foreground/20 flex flex-col gap-3 rounded-xl border p-5 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-3">
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
                                        v-if="project.visibility === 'public'"
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
                            <p
                                class="text-muted-foreground mt-auto text-sm tabular-nums"
                            >
                                <span class="text-foreground font-medium">
                                    {{ project.openFeaturesCount }} open
                                </span>
                                · {{ project.featuresCount }} total
                            </p>
                        </Link>
                    </div>
                </section>

                <section class="space-y-4">
                    <h2 class="font-semibold">Recent requests</h2>
                    <ol
                        v-if="recentFeatures.length > 0"
                        class="divide-y rounded-xl border"
                    >
                        <li
                            v-for="feature in recentFeatures"
                            :key="feature.id"
                            class="flex items-start gap-3 p-4"
                        >
                            <span
                                class="text-muted-foreground flex w-10 shrink-0 flex-col items-center text-xs tabular-nums"
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
