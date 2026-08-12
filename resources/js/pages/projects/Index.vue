<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import {
        Table,
        TableBody,
        TableCell,
        TableEmpty,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table'
    import { index, show } from '@/routes/projects'

    type ProjectRow = {
        id: string
        name: string
        slug: string
        ownerName: string | null
        ownerType: string | null
        createdAt: string | null
    }

    const props = defineProps<{
        teamName: string | null
        projects: ProjectRow[]
    }>()

    defineOptions({
        layout: () => ({
            breadcrumbs: [{ title: 'Projects', href: index().url }],
        }),
    })
</script>

<template>
    <Head title="Projects" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            data-test="projects-index"
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <h1 class="text-sm font-medium">Projects</h1>
                    <p
                        v-if="props.teamName"
                        data-test="projects-index-team"
                        class="text-xs text-muted-foreground"
                    >
                        {{ props.teamName }} and its clients
                    </p>
                </div>
                <span class="text-xs text-muted-foreground">
                    {{ props.projects.length }}
                    {{ props.projects.length === 1 ? 'project' : 'projects' }}
                </span>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Project</TableHead>
                        <TableHead>Owner</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="text-right">Created</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty
                        v-if="props.projects.length === 0"
                        :colspan="4"
                        data-test="projects-index-empty"
                    >
                        <template v-if="props.teamName">
                            {{ props.teamName }} has no projects yet.
                        </template>
                        <template v-else>
                            Switch to a team in this organization to see its
                            projects.
                        </template>
                    </TableEmpty>

                    <template v-else>
                        <TableRow
                            v-for="project in props.projects"
                            :key="project.id"
                            data-test="projects-index-row"
                        >
                            <TableCell>
                                <Link
                                    :href="show(project.slug)"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ project.name }}
                                </Link>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ project.ownerName ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground capitalize">
                                {{ project.ownerType ?? '—' }}
                            </TableCell>
                            <TableCell
                                class="text-right whitespace-nowrap text-muted-foreground"
                            >
                                {{ project.createdAt ?? '—' }}
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
