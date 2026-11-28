<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, Inbox, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import CreateTicketModal from '@/components/CreateTicketModal.vue';
import Heading from '@/components/Heading.vue';
import ShowMore from '@/components/ShowMore.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/dates';
import { destroyMany, index, show } from '@/routes/tickets';
import type {
    Organization,
    TicketClientOption,
    TicketFilters,
    TicketListItem,
    TicketStatusOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    tickets: { data: TicketListItem[]; hasMore: boolean };
    filters: TicketFilters;
    statuses: TicketStatusOption[];
    clients: TicketClientOption[];
    counts: { inbox: number; unassigned: number; ready: number; mine: number };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Tickets',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const all = 'all';

const selected = ref<number[]>([]);
const bulkDeleteOpen = ref(false);

watch(
    () => props.tickets.data,
    (tickets) => {
        const shown = new Set(tickets.map((ticket) => ticket.id));
        selected.value = selected.value.filter((id) => shown.has(id));
    },
);

const allSelected = computed(
    () =>
        props.tickets.data.length > 0 &&
        selected.value.length === props.tickets.data.length,
);

const selectAll = (checked: boolean | 'indeterminate') => {
    selected.value =
        checked === true ? props.tickets.data.map((ticket) => ticket.id) : [];
};

const select = (id: number, checked: boolean | 'indeterminate') => {
    selected.value =
        checked === true
            ? [...selected.value, id]
            : selected.value.filter((selectedId) => selectedId !== id);
};

const views = computed(
    () =>
        [
            { value: null, label: 'Inbox', count: props.counts.inbox },
            {
                value: 'unassigned',
                label: 'Unassigned',
                count: props.counts.unassigned,
            },
            {
                value: 'ready',
                label: 'Ready to reply',
                count: props.counts.ready,
            },
            {
                value: 'mine',
                label: 'Reported by me',
                count: props.counts.mine,
            },
        ] as const,
);

const applyFilters = (
    changes: Partial<Record<keyof TicketFilters, string | null>>,
) => {
    const next = { ...props.filters, ...changes };

    router.visit(
        index(props.currentOrganization.slug, {
            query: Object.fromEntries(
                Object.entries(next).map(([key, value]) => [
                    key,
                    value && value !== all ? value : undefined,
                ]),
            ),
        }),
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const statusFilter = computed({
    get: () => props.filters.status ?? all,
    set: (value: string) => applyFilters({ status: value }),
});

const clientFilter = computed({
    get: () => props.filters.client ?? all,
    set: (value: string) => applyFilters({ client: value }),
});
</script>

<template>
    <Head title="Tickets" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Tickets"
                description="Mail sent to support, sorted by the latest message."
            />

            <CreateTicketModal
                :organization="currentOrganization.slug"
                :clients="clients"
            >
                <Button data-test="tickets-new-ticket-button">
                    <Plus />
                    New ticket
                </Button>
            </CreateTicketModal>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-for="view in views"
                :key="view.label"
                :variant="filters.view === view.value ? 'secondary' : 'ghost'"
                size="sm"
                :data-test="`ticket-view-${view.value ?? 'inbox'}`"
                @click="applyFilters({ view: view.value })"
            >
                {{ view.label }}
                <Badge variant="outline" class="tabular-nums">
                    {{ view.count }}
                </Badge>
            </Button>

            <Select v-model="statusFilter">
                <SelectTrigger
                    class="ml-auto w-44"
                    data-test="ticket-status-filter"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="all">Open and waiting</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="clientFilter">
                <SelectTrigger class="w-44">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="all">All clients</SelectItem>
                    <SelectItem value="internal">
                        {{ currentOrganization.name }}
                    </SelectItem>
                    <SelectSeparator />
                    <SelectItem
                        v-for="client in clients"
                        :key="client.id"
                        :value="client.slug ?? String(client.id)"
                    >
                        {{ client.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="tickets.data.length > 0" class="space-y-4">
            <div class="rounded-xl border">
                <div
                    class="text-muted-foreground flex h-11 items-center gap-3 border-b px-4 text-sm"
                >
                    <Checkbox
                        aria-label="Select all tickets"
                        data-test="select-all-tickets"
                        :model-value="
                            allSelected
                                ? true
                                : selected.length > 0
                                  ? 'indeterminate'
                                  : false
                        "
                        @update:model-value="selectAll"
                    />
                    <span v-if="selected.length > 0" class="tabular-nums">
                        {{ selected.length }} selected
                    </span>
                    <span v-else>Select tickets</span>
                    <Button
                        v-if="selected.length > 0"
                        variant="ghost"
                        size="sm"
                        class="text-destructive ml-auto"
                        data-test="delete-selected-tickets"
                        @click="bulkDeleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </Button>
                </div>
                <ul class="divide-y">
                    <li
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        class="hover:bg-muted/50 flex items-center"
                    >
                        <div class="flex items-center self-stretch pl-4">
                            <Checkbox
                                :aria-label="`Select ${ticket.reference}`"
                                data-test="select-ticket"
                                :model-value="selected.includes(ticket.id)"
                                @update:model-value="select(ticket.id, $event)"
                            />
                        </div>
                        <Link
                            :href="show([currentOrganization.slug, ticket.id])"
                            class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3"
                            data-test="ticket-row"
                        >
                            <span
                                class="text-muted-foreground w-14 text-sm tabular-nums"
                            >
                                {{ ticket.reference }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">
                                    {{ ticket.subject }}
                                </span>
                                <span
                                    class="text-muted-foreground block truncate text-sm"
                                >
                                    {{
                                        ticket.requesterName ??
                                        ticket.requesterEmail
                                    }}
                                    <template v-if="ticket.requesterName">
                                        · {{ ticket.requesterEmail }}
                                    </template>
                                </span>
                            </span>
                            <span
                                v-if="ticket.readyToReply"
                                class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                <CircleCheck class="size-4" />
                                Ready to reply
                            </span>
                            <Badge v-if="ticket.issue" variant="outline">
                                {{ ticket.issue.reference }}
                            </Badge>
                            <Badge v-if="ticket.client" variant="secondary">
                                {{ ticket.client.name }}
                            </Badge>
                            <Badge
                                v-else-if="ticket.isInternal"
                                variant="secondary"
                            >
                                {{ currentOrganization.name }}
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="border-dashed"
                            >
                                Unassigned
                            </Badge>
                            <TicketStatusBadge
                                :status="ticket.status"
                                :label="ticket.statusLabel"
                            />
                            <span
                                class="text-muted-foreground w-28 text-right text-xs"
                            >
                                {{
                                    ticket.lastMessageAt
                                        ? formatDate(ticket.lastMessageAt)
                                        : ''
                                }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
            <ShowMore
                prop="tickets"
                :shown="tickets.data.length"
                :has-more="tickets.hasMore"
            />
        </div>

        <div
            v-else
            class="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-sm"
        >
            <Inbox class="size-6" />
            Nothing here. Mail sent to support shows up in this list.
        </div>

        <ConfirmDeleteModal
            v-model:open="bulkDeleteOpen"
            :title="
                selected.length === 1
                    ? 'Delete ticket'
                    : `Delete ${selected.length} tickets`
            "
            :confirm-label="
                selected.length === 1
                    ? 'Delete ticket'
                    : `Delete ${selected.length} tickets`
            "
            :href="
                destroyMany(currentOrganization.slug, {
                    query: { ids: selected },
                })
            "
        >
            This permanently deletes the selected tickets with their messages
            and attachments. Linked issues are kept.
        </ConfirmDeleteModal>
    </div>
</template>
