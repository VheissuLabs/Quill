<script setup lang="ts">
    import { Link } from '@inertiajs/vue3'
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
    import type { ActivityEntry, Paginated } from '@/types'

    const props = defineProps<{
        activity: Paginated<ActivityEntry>
    }>()
</script>

<template>
    <div
        data-test="organization-activity"
        class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
    >
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h2 class="text-sm font-medium">Organization activity</h2>
            <span class="text-xs text-muted-foreground">
                {{ props.activity.total }}
                {{ props.activity.total === 1 ? 'entry' : 'entries' }}
            </span>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>What</TableHead>
                    <TableHead>Who</TableHead>
                    <TableHead class="text-right">When</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableEmpty
                    v-if="props.activity.data.length === 0"
                    :colspan="3"
                    data-test="organization-activity-empty"
                >
                    Nothing has happened yet.
                </TableEmpty>

                <template v-else>
                    <TableRow
                        v-for="entry in props.activity.data"
                        :key="entry.id"
                        data-test="organization-activity-row"
                    >
                        <TableCell>{{ entry.summary }}</TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ entry.causerName ?? 'System' }}
                        </TableCell>
                        <TableCell
                            class="text-right whitespace-nowrap text-muted-foreground"
                            :title="entry.happenedAt"
                        >
                            {{ entry.happenedAtDiff }}
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>

        <div
            v-if="props.activity.last_page > 1"
            class="flex items-center justify-between border-t px-4 py-3"
        >
            <span class="text-xs text-muted-foreground">
                Page {{ props.activity.current_page }} of
                {{ props.activity.last_page }}
            </span>

            <div class="flex gap-2">
                <Button
                    v-if="props.activity.prev_page_url"
                    as-child
                    variant="secondary"
                    size="sm"
                    data-test="organization-activity-previous"
                >
                    <Link
                        :href="props.activity.prev_page_url"
                        preserve-scroll
                        preserve-state
                    >
                        Previous
                    </Link>
                </Button>

                <Button
                    v-if="props.activity.next_page_url"
                    as-child
                    variant="secondary"
                    size="sm"
                    data-test="organization-activity-next"
                >
                    <Link
                        :href="props.activity.next_page_url"
                        preserve-scroll
                        preserve-state
                    >
                        Next
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
