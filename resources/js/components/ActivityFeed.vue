<script setup lang="ts">
import { ChevronDown, History } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { formatDate } from '@/lib/dates';
import type { ActivityEntry } from '@/types';

defineProps<{
    entries: ActivityEntry[];
    subject: string;
}>();

/**
 * Collapsed by default; the choice is remembered in this browser.
 */
const open = useLocalStorage('quill.activity-feed.open', false);

const eventLabel = (event: string) =>
    ({ created: 'created', updated: 'changed', deleted: 'deleted' })[event] ??
    event;

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
</script>

<template>
    <section class="space-y-3" data-test="activity-feed">
        <button
            type="button"
            class="text-muted-foreground hover:text-foreground flex w-full items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
            :aria-expanded="open"
            data-test="activity-toggle"
            @click="open = !open"
        >
            <History class="size-3.5" />
            Activity
            <span class="tabular-nums">({{ entries.length }})</span>
            <ChevronDown
                class="ml-auto size-3.5 transition-transform"
                :class="!open && '-rotate-90'"
            />
        </button>

        <ol v-if="open && entries.length > 0" class="space-y-3 text-sm">
            <li
                v-for="entry in entries"
                :key="entry.id"
                data-test="activity-entry"
                class="border-l pl-3"
            >
                <p
                    v-if="
                        entry.event === 'updated' && entry.changes.length === 1
                    "
                >
                    <span class="font-medium">{{
                        entry.causerName ?? 'System'
                    }}</span>
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
                </p>
                <template v-else-if="entry.event === 'updated'">
                    <p>
                        <span class="font-medium">{{
                            entry.causerName ?? 'System'
                        }}</span>
                        made {{ entry.changes.length }} changes
                    </p>
                    <ul
                        class="text-muted-foreground mt-0.5 space-y-0.5 text-xs"
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
                </template>
                <p v-else>
                    <span class="font-medium">{{
                        entry.causerName ?? 'System'
                    }}</span>
                    {{ eventLabel(entry.event) }} the {{ subject }}
                </p>
                <p
                    class="text-muted-foreground mt-0.5 text-[11px] tabular-nums"
                >
                    {{ formatDate(entry.createdAt) }} ·
                    {{ formatTime(entry.createdAt) }}
                </p>
            </li>
        </ol>
        <p v-else-if="open" class="text-muted-foreground text-xs">
            Nothing yet.
        </p>
    </section>
</template>
