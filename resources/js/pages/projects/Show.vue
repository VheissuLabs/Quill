<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import CreateIssueModal from '@/components/CreateIssueModal.vue'
    import { Button } from '@/components/ui/button'
    import {
        Table,
        TableBody,
        TableCell,
        TableEmpty,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table'
    import { usePermissions } from '@/composables/usePermissions'
    import { show } from '@/routes/projects'
    import { show as showIssue } from '@/routes/projects/issues'
    import type { IssueListItem, IssueType } from '@/types'

    type Props = {
        project: {
            id: string
            name: string
            slug: string
            ownerName: string | null
            ownerType: string | null
            description: string | null
            defaultForClients: string[]
        }
        issues: IssueListItem[]
        closedIssueCount: number
        issueTypes: IssueType[]
    }

    const props = defineProps<Props>()

    defineOptions({
        layout: (props: Props) => ({
            breadcrumbs: [
                {
                    title: props.project.name,
                    href: show(props.project.slug).url,
                },
            ],
        }),
    })

    const { can } = usePermissions()

    const createIssueDialogOpen = ref(false)
</script>

<template>
    <Head :title="props.project.name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h1 data-test="project-name" class="text-lg font-medium">
                {{ props.project.name }}
            </h1>

            <p
                v-if="props.project.ownerName"
                data-test="project-owner"
                class="mt-1 text-sm text-muted-foreground"
            >
                Owned by the {{ props.project.ownerType }}
                {{ props.project.ownerName }}
            </p>

            <p
                v-if="props.project.description"
                class="mt-3 text-sm whitespace-pre-wrap"
            >
                {{ props.project.description }}
            </p>

            <p
                v-if="props.project.defaultForClients.length > 0"
                data-test="project-default-for"
                class="mt-3 text-sm text-muted-foreground"
            >
                Work from
                {{ props.project.defaultForClients.join(', ') }}
                lands here by default.
            </p>
        </div>

        <div
            data-test="project-issues"
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <h2 class="text-sm font-medium">Issues</h2>
                    <p
                        v-if="props.closedIssueCount > 0"
                        data-test="project-issues-closed-count"
                        class="text-xs text-muted-foreground"
                    >
                        {{ props.closedIssueCount }} closed
                    </p>
                </div>

                <Button
                    v-if="can('issue:create')"
                    data-test="file-issue-button"
                    @click="createIssueDialogOpen = true"
                >
                    File an issue
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Title</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="text-right">Client</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableEmpty
                        v-if="props.issues.length === 0"
                        :colspan="4"
                        data-test="project-issues-empty"
                    >
                        No open issues.
                    </TableEmpty>

                    <template v-else>
                        <TableRow
                            v-for="issue in props.issues"
                            :key="issue.number"
                            data-test="project-issues-row"
                        >
                            <TableCell class="text-muted-foreground">
                                #{{ issue.number }}
                            </TableCell>
                            <TableCell>
                                <Link
                                    :href="
                                        showIssue([
                                            props.project.slug,
                                            issue.number,
                                        ])
                                    "
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ issue.title }}
                                </Link>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ issue.type }}
                            </TableCell>
                            <TableCell class="text-right text-muted-foreground">
                                {{ issue.clientName ?? '—' }}
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
    </div>

    <CreateIssueModal
        v-if="can('issue:create')"
        :project="props.project.slug"
        :issue-types="props.issueTypes"
        :open="createIssueDialogOpen"
        @update:open="createIssueDialogOpen = $event"
    />
</template>
