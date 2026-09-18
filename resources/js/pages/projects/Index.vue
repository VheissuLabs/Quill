<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ExternalLink,
    FolderKanban,
    Globe,
    Lock,
    Pencil,
    Plus,
} from '@lucide/vue';
import CreateProjectModal from '@/components/CreateProjectModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { show as showBoard } from '@/routes/portal/projects';
import { edit, index } from '@/routes/projects';
import type {
    Organization,
    OrganizationPermissions,
    ProjectListItem,
    SelectOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    projects: ProjectListItem[];
    visibilities: SelectOption[];
    permissions: OrganizationPermissions;
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Projects',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Projects"
                description="Each project has a board where feedback are requested and voted on"
            />

            <CreateProjectModal
                v-if="permissions.canManageProjects"
                :organization="currentOrganization.slug"
                :visibilities="visibilities"
            >
                <Button data-test="projects-new-project-button">
                    <Plus /> New project
                </Button>
            </CreateProjectModal>
        </div>

        <div class="space-y-3">
            <div
                v-for="project in projects"
                :key="project.id"
                data-test="project-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="
                                showBoard([
                                    currentOrganization.slug,
                                    project.slug,
                                ])
                            "
                            class="font-medium hover:underline"
                        >
                            {{ project.name }}
                        </Link>
                        <Badge variant="secondary" class="gap-1">
                            <Globe v-if="project.visibility === 'public'" />
                            <Lock v-else />
                            {{ project.visibilityLabel }}
                        </Badge>
                        <Badge v-if="project.clientName" variant="outline">
                            {{ project.clientName }}
                        </Badge>
                    </div>
                    <p
                        v-if="project.description"
                        class="text-muted-foreground mt-1 truncate text-sm"
                    >
                        {{ project.description }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ project.feedbackCount }}
                        {{
                            project.feedbackCount === 1
                                ? 'feedback request'
                                : 'feedback requests'
                        }}
                    </p>
                </div>

                <TooltipProvider>
                    <div class="flex shrink-0 items-center gap-2">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="project-board-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            showBoard([
                                                currentOrganization.slug,
                                                project.slug,
                                            ])
                                        "
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Open board</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-if="permissions.canManageProjects">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="project-edit-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            edit([
                                                currentOrganization.slug,
                                                project.slug,
                                            ])
                                        "
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit project</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <EmptyState
                v-if="projects.length === 0"
                :icon="FolderKanban"
                title="No projects yet"
                :description="
                    permissions.canManageProjects
                        ? 'Every project gets a board where feedback are requested and voted on. Create the first one.'
                        : 'Projects will show up here once an admin creates one you can see.'
                "
            >
                <CreateProjectModal
                    v-if="permissions.canManageProjects"
                    :organization="currentOrganization.slug"
                    :visibilities="visibilities"
                >
                    <Button variant="outline"><Plus /> New project</Button>
                </CreateProjectModal>
            </EmptyState>
        </div>
    </div>
</template>
