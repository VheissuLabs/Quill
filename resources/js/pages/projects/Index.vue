<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import { index, show } from '@/routes/projects'

    type ProjectRow = {
        id: string
        name: string
        slug: string
        ownerName: string | null
        ownerType: string | null
        defaultForClients: string[]
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

            <p
                v-if="props.projects.length === 0"
                data-test="projects-index-empty"
                class="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                <template v-if="props.teamName">
                    {{ props.teamName }} has no projects yet.
                </template>
                <template v-else>
                    Switch to a team in this organization to see its projects.
                </template>
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left font-medium">
                                Project
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Owner
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Work lands here from
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Created
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="project in props.projects"
                            :key="project.id"
                            data-test="projects-index-row"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="show(project.slug)"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ project.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                <template v-if="project.ownerName">
                                    {{ project.ownerType }}
                                    {{ project.ownerName }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{
                                    project.defaultForClients.length > 0
                                        ? project.defaultForClients.join(', ')
                                        : '—'
                                }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap text-muted-foreground"
                            >
                                {{ project.createdAt ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
