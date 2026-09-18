<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, History } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { formatDate } from '@/lib/dates';
import { show } from '@/routes/issues';
import type { IssueActivityEntry } from '@/types';

defineProps<{
    entries: IssueActivityEntry[];
    organization: string;
}>();

/**
 * Open by default; collapsing it is remembered in this browser.
 */
const open = useLocalStorage('quill.team-activity-feed.open', true);

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
        <button
            type="button"
            class="text-muted-foreground hover:text-foreground flex w-full items-center gap-1.5 px-1 text-xs font-medium tracking-wide uppercase"
            :aria-expanded="open"
            data-test="team-activity-toggle"
            @click="open = !open"
        >
            <History class="size-3.5" />
            Recent activity
            <span class="tabular-nums">({{ entries.length }})</span>
            <ChevronDown
                class="ml-auto size-3.5 transition-transform"
                :class="!open && '-rotate-90'"
            />
        </button>

        <ol
            v-if="open && entries.length > 0"
            class="bg-card divide-y rounded-xl border text-sm"
        >
            <li
                v-for="entry in entries"
                :key="entry.id"
                data-test="team-activity-entry"
                class="space-y-1 p-3"
            >
                <p
                    class="text-muted-foreground flex flex-wrap items-baseline gap-x-1.5 leading-snug"
                >
                    <span class="text-foreground font-medium">
                        {{ entry.causerName ?? 'System' }}
                    </span>
                    <template
                        v-if="
                            entry.event === 'updated' &&
                            entry.changes.length === 1
                        "
                    >
                        <span>
                            changed {{ entry.changes[0].field.toLowerCase() }}
                        </span>
                        <template v-if="entry.changes[0].from !== null">
                            <span>from</span>
                            <span class="text-foreground/70 line-through">
                                {{ entry.changes[0].from }}
                            </span>
                        </template>
                        <span>to</span>
                        <span class="text-foreground font-medium">
                            {{ entry.changes[0].to ?? 'none' }}
                        </span>
                        <span>on</span>
                    </template>
                    <span v-else-if="entry.event === 'updated'">
                        made {{ entry.changes.length }} changes to
                    </span>
                    <span v-else>{{ eventLabel(entry.event) }}</span>
                    <Link
                        :href="show([organization, entry.issue.id])"
                        class="hover:text-foreground tabular-nums"
                    >
                        {{ entry.issue.reference }}
                    </Link>
                </p>
                <Link
                    :href="show([organization, entry.issue.id])"
                    class="block leading-snug font-medium hover:underline"
                >
                    {{ entry.issue.title }}
                </Link>
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
            v-else-if="open"
            class="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-xs"
        >
            Nothing yet. Changes to issues will show up here.
        </p>
    </section>
</template>
