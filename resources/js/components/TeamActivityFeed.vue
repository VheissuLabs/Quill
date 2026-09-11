<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { formatDate } from '@/lib/dates';
import { show } from '@/routes/issues';
import type { IssueActivityEntry } from '@/types';

defineProps<{
    entries: IssueActivityEntry[];
    organization: string;
}>();

const eventLabel = (event: string) =>
    ({ created: 'created', deleted: 'deleted' })[event] ?? event;

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
</script>

<template>
    <section class="space-y-3" data-test="team-activity-feed">
        <h2
            class="text-muted-foreground flex items-center gap-1.5 px-1 text-xs font-medium tracking-wide uppercase"
        >
            <History class="size-3.5" />
            Recent activity
        </h2>

        <ol
            v-if="entries.length > 0"
            class="bg-card divide-y rounded-xl border text-sm"
        >
            <li
                v-for="entry in entries"
                :key="entry.id"
                data-test="team-activity-entry"
                class="space-y-1 p-3"
            >
                <p class="leading-snug">
                    <span class="font-medium">{{
                        entry.causerName ?? 'System'
                    }}</span>
                    <template
                        v-if="
                            entry.event === 'updated' &&
                            entry.changes.length === 1
                        "
                    >
                        changed {{ entry.changes[0].field.toLowerCase() }}
                        <template v-if="entry.changes[0].from !== null">
                            from
                            <span class="text-foreground/70 line-through">{{
                                entry.changes[0].from
                            }}</span>
                        </template>
                        to
                        <span class="font-medium">{{
                            entry.changes[0].to ?? 'none'
                        }}</span>
                    </template>
                    <template v-else-if="entry.event === 'updated'">
                        made {{ entry.changes.length }} changes to
                    </template>
                    <template v-else>
                        {{ eventLabel(entry.event) }}
                    </template>
                    <Link
                        :href="show([organization, entry.issue.id])"
                        class="text-foreground hover:underline"
                    >
                        <span class="text-muted-foreground">{{
                            entry.issue.reference
                        }}</span>
                        {{ entry.issue.title }}
                    </Link>
                </p>
                <ul
                    v-if="entry.event === 'updated' && entry.changes.length > 1"
                    class="text-muted-foreground space-y-0.5 text-xs"
                >
                    <li v-for="change in entry.changes" :key="change.field">
                        {{ change.field }}:
                        <template v-if="change.from !== null">
                            <span class="text-foreground/70 line-through">{{
                                change.from
                            }}</span>
                            →
                        </template>
                        <span class="text-foreground font-medium">{{
                            change.to ?? 'none'
                        }}</span>
                    </li>
                </ul>
                <p class="text-muted-foreground text-[11px] tabular-nums">
                    {{ formatDate(entry.createdAt) }} ·
                    {{ formatTime(entry.createdAt) }}
                </p>
            </li>
        </ol>
        <p
            v-else
            class="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-xs"
        >
            Nothing yet. Changes to issues will show up here.
        </p>
    </section>
</template>
