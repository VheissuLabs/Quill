<script setup lang="ts">
import { ChevronDown, History } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
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

const opened = ref<ActivityEntry | null>(null);

const eventLabel = (event: string) =>
    ({
        created: 'created',
        updated: 'changed',
        deleted: 'deleted',
        comment_deleted: 'deleted a comment on',
    })[event] ?? event.replace(/_/g, ' ');

/**
 * A description or a comment is too long to read in a list, and printing both halves of the
 * change makes the line useless. The line says what moved; the modal says what it said.
 */
const LONG = 40;

const isLong = (value: string | null) => (value?.length ?? 0) > LONG;

const hasLongValue = (entry: ActivityEntry) =>
    entry.changes.some((change) => isLong(change.from) || isLong(change.to));

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });

const openedTitle = computed(() =>
    opened.value
        ? `${opened.value.causerName ?? 'System'} ${eventLabel(opened.value.event)}`
        : '',
);
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
                class="hover:bg-accent/40 -ml-px cursor-pointer rounded-r border-l pl-3 transition-colors"
                @click="opened = entry"
            >
                <p
                    v-if="
                        entry.event === 'updated' &&
                        entry.changes.length === 1 &&
                        !hasLongValue(entry)
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
                <p v-else-if="entry.event === 'updated'">
                    <span class="font-medium">{{
                        entry.causerName ?? 'System'
                    }}</span>
                    changed
                    {{
                        entry.changes
                            .map((change) => change.field.toLowerCase())
                            .join(', ')
                    }}
                </p>
                <p v-else>
                    <span class="font-medium">{{
                        entry.causerName ?? 'System'
                    }}</span>
                    {{ eventLabel(entry.event) }}
                    <template v-if="entry.event !== 'comment_deleted'">
                        the {{ subject }}
                    </template>
                    <template v-else>this {{ subject }}</template>
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

        <Dialog
            :open="opened !== null"
            @update:open="(value) => !value && (opened = null)"
        >
            <DialogScrollContent
                class="sm:max-w-2xl"
                data-test="activity-modal"
            >
                <DialogHeader>
                    <DialogTitle>{{ openedTitle }}</DialogTitle>
                    <DialogDescription v-if="opened">
                        {{ formatDate(opened.createdAt) }} ·
                        {{ formatTime(opened.createdAt) }}
                    </DialogDescription>
                </DialogHeader>

                <dl v-if="opened" class="space-y-4 text-sm">
                    <div
                        v-for="change in opened.changes"
                        :key="change.field"
                        class="space-y-1"
                    >
                        <dt class="text-muted-foreground text-xs font-medium">
                            {{ change.field }}
                        </dt>
                        <dd v-if="change.from !== null" class="space-y-1">
                            <p
                                class="text-muted-foreground/80 bg-muted/50 rounded-md p-2 text-xs whitespace-pre-wrap line-through"
                            >
                                {{ change.from }}
                            </p>
                        </dd>
                        <dd
                            class="bg-muted/50 rounded-md p-2 whitespace-pre-wrap"
                        >
                            {{ change.to ?? 'none' }}
                        </dd>
                    </div>
                    <p
                        v-if="opened.changes.length === 0"
                        class="text-muted-foreground"
                    >
                        Nothing was recorded for this entry.
                    </p>
                </dl>
            </DialogScrollContent>
        </Dialog>
    </section>
</template>
