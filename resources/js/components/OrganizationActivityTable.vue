<script setup lang="ts">
    import { Link } from '@inertiajs/vue3'
    import { Button } from '@/components/ui/button'
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

        <p
            v-if="props.activity.data.length === 0"
            data-test="organization-activity-empty"
            class="px-4 py-10 text-center text-sm text-muted-foreground"
        >
            Nothing has happened yet.
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-muted-foreground">
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left font-medium">What</th>
                        <th class="px-4 py-2 text-left font-medium">Who</th>
                        <th class="px-4 py-2 text-right font-medium">When</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in props.activity.data"
                        :key="entry.id"
                        data-test="organization-activity-row"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-2">{{ entry.summary }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ entry.causerName ?? 'System' }}
                        </td>
                        <td
                            class="px-4 py-2 text-right whitespace-nowrap text-muted-foreground"
                            :title="entry.happenedAt"
                        >
                            {{ entry.happenedAtDiff }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
