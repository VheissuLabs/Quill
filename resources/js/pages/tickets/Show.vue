<script setup lang="ts">
import { Head, InfiniteScroll, Link, router, useHttp } from '@inertiajs/vue3';
import { CircleCheck, Link2, Unlink } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import CommentForm from '@/components/CommentForm.vue';
import CommentThread from '@/components/CommentThread.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import TicketStatusBadge from '@/components/TicketStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/dates';
import { mutate } from '@/lib/optimistic';
import { store as storeDomain } from '@/routes/clients/domains';
import { lookup as issueLookup, show as showIssue } from '@/routes/issues';
import { index, show, update } from '@/routes/tickets';
import { show as showAttachment } from '@/routes/tickets/attachments';
import {
    destroy as unlinkIssue,
    store as storeIssue,
} from '@/routes/tickets/issue';
import {
    destroy as destroyReply,
    store as storeReply,
} from '@/routes/tickets/replies';
import { store as retryDelivery } from '@/routes/tickets/replies/delivery';
import type {
    CommentMention,
    CommentPage,
    FeedbackComment,
    Organization,
    TicketClientOption,
    TicketDetail,
    TicketListItem,
    TicketStatusOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    ticket: TicketDetail;
    comments: CommentPage;
    mentionables: CommentMention[];
    statuses: TicketStatusOption[];
    clients: TicketClientOption[];
    projects: { id: number; name: string }[];
    otherTickets: TicketListItem[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        ticket: TicketDetail;
    }) => ({
        breadcrumbs: [
            {
                title: 'Tickets',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.ticket.reference,
                href: show([props.currentOrganization.slug, props.ticket.id]),
            },
        ],
    }),
});

const none = 'none';
const routeArgs = (): [string, number] => [
    props.currentOrganization.slug,
    props.ticket.id,
];
const commentToDelete = ref<FeedbackComment | null>(null);
const projectId = ref<string>(none);
const issueQuery = ref('');
const issueResults = ref<
    { id: number; reference: string; title: string; statusLabel: string }[]
>([]);
const issueRequest = useHttp<
    Record<string, never>,
    typeof issueResults.value
>();

const patch = (data: Record<string, string | number | null>) => {
    mutate(update(routeArgs()), {
        data,
        errorMessage: 'That change could not be saved.',
    });
};

const changeClient = (value: string) =>
    patch({ client_id: value === none ? null : Number(value) });

const routeDomain = () => {
    if (!props.ticket.client || !props.ticket.suggestedDomain) {
        return;
    }

    router.post(
        storeDomain([props.currentOrganization.slug, props.ticket.client.slug])
            .url,
        { domain: props.ticket.suggestedDomain },
        { preserveScroll: true },
    );
};

const convert = () => {
    router.post(
        storeIssue(routeArgs()).url,
        { project_id: Number(projectId.value) },
        { preserveScroll: true },
    );
};

const linkIssue = (issueId: number) => {
    router.post(
        storeIssue(routeArgs()).url,
        { issue_id: issueId },
        {
            preserveScroll: true,
            onSuccess: () => {
                issueQuery.value = '';
                issueResults.value = [];
            },
        },
    );
};

const searchIssues = useDebounceFn(async () => {
    if (issueQuery.value.trim().length < 2) {
        issueResults.value = [];

        return;
    }

    issueRequest.cancel();

    try {
        issueResults.value = await issueRequest.get(
            issueLookup(props.currentOrganization.slug, {
                query: { q: issueQuery.value.trim() },
            }).url,
        );
    } catch {
        issueResults.value = [];
    }
}, 200);

const retry = (comment: FeedbackComment) => {
    router.post(
        retryDelivery([...routeArgs(), comment.id]).url,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="`${ticket.reference} ${ticket.subject}`" />

    <div class="grid gap-10 p-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-8">
            <div class="space-y-2">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-sm tabular-nums"
                >
                    {{ ticket.reference }}
                    <TicketStatusBadge
                        :status="ticket.status"
                        :label="ticket.statusLabel"
                    />
                </p>
                <h1 class="text-xl font-semibold" data-test="ticket-subject">
                    {{ ticket.subject }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    From {{ ticket.requesterName ?? ticket.requesterEmail }}
                    <template v-if="ticket.requesterName">
                        &lt;{{ ticket.requesterEmail }}&gt;
                    </template>
                    · opened {{ formatDate(ticket.createdAt) }}
                    <template v-if="ticket.createdByName">
                        by {{ ticket.createdByName }}
                    </template>
                </p>
            </div>

            <div
                v-if="ticket.readyToReply"
                class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/40"
                data-test="ticket-ready-to-reply"
            >
                <CircleCheck class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                <p>
                    {{ ticket.issue?.reference }} is done. Let
                    {{ ticket.requesterName ?? ticket.requesterEmail }} know it
                    is fixed.
                </p>
            </div>

            <section class="space-y-5" data-test="ticket-thread">
                <CommentForm
                    :action="storeReply(routeArgs()).url"
                    :mentionable="mentionables"
                    :can-comment-internally="true"
                    :organization-name="currentOrganization.name"
                    :placeholder="`Reply to ${ticket.requesterName ?? ticket.requesterEmail}`"
                    submit-label="Send"
                />

                <InfiniteScroll
                    v-if="comments.data.length > 0"
                    data="comments"
                    as="ol"
                    class="block space-y-6"
                    only-next
                >
                    <CommentThread
                        v-for="comment in comments.data"
                        :key="comment.id"
                        :comment="comment"
                        :action="storeReply(routeArgs()).url"
                        :can-comment="false"
                        :mentionable="mentionables"
                        :attachment-href="
                            (id: number) =>
                                showAttachment([currentOrganization.slug, id])
                                    .url
                        "
                        @delete="commentToDelete = $event"
                        @retry="retry"
                    />
                </InfiniteScroll>
            </section>
        </div>

        <aside
            class="space-y-6 text-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto lg:pr-1"
        >
            <div class="space-y-1.5">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >
                    Status
                </p>
                <Select
                    :model-value="ticket.status"
                    @update:model-value="patch({ status: String($event) })"
                >
                    <SelectTrigger
                        class="w-full"
                        data-test="ticket-status-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                >
                    Client
                </p>
                <Select
                    :model-value="
                        ticket.client ? String(ticket.client.id) : none
                    "
                    @update:model-value="changeClient(String($event))"
                >
                    <SelectTrigger
                        class="w-full"
                        data-test="ticket-client-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="none">Unassigned</SelectItem>
                        <SelectItem
                            v-for="client in clients"
                            :key="client.id"
                            :value="String(client.id)"
                        >
                            {{ client.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <button
                    v-if="ticket.client && ticket.suggestedDomain"
                    type="button"
                    class="text-muted-foreground hover:text-foreground text-xs underline"
                    data-test="ticket-route-domain"
                    @click="routeDomain"
                >
                    Send future mail from {{ ticket.suggestedDomain }} to
                    {{ ticket.client.name }}
                </button>
            </div>

            <div class="space-y-2 border-t pt-5">
                <Heading variant="small" title="Issue" />

                <div v-if="ticket.issue" class="space-y-2">
                    <Link
                        :href="
                            showIssue([
                                currentOrganization.slug,
                                ticket.issue.id,
                            ])
                        "
                        class="hover:bg-muted/50 block rounded-lg border p-3"
                    >
                        <span class="text-muted-foreground block text-xs">
                            {{ ticket.issue.reference }}
                        </span>
                        <span class="font-medium">{{
                            ticket.issue.title
                        }}</span>
                        <IssueStatusBadge
                            class="mt-2"
                            :status="ticket.issue.status"
                            :label="ticket.issue.statusLabel"
                        />
                    </Link>
                    <p
                        v-if="ticket.reporterName"
                        class="text-muted-foreground text-xs"
                    >
                        {{ ticket.reporterName }} follows up when it is done.
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="ticket-unlink-issue"
                        @click="
                            mutate(unlinkIssue(routeArgs()), {
                                errorMessage:
                                    'The issue could not be unlinked.',
                            })
                        "
                    >
                        <Unlink />
                        Unlink
                    </Button>
                </div>

                <p v-else-if="!ticket.client" class="text-muted-foreground">
                    Assign a client before turning this into an issue.
                </p>

                <div v-else-if="ticket.canConvert" class="space-y-4">
                    <div class="space-y-2">
                        <p class="text-muted-foreground text-xs">
                            Create a backlog issue
                        </p>
                        <div class="flex gap-2">
                            <Select v-model="projectId">
                                <SelectTrigger
                                    class="min-w-0 flex-1"
                                    data-test="ticket-convert-project"
                                >
                                    <SelectValue placeholder="Project" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="none" disabled
                                        >Project</SelectItem
                                    >
                                    <SelectItem
                                        v-for="project in projects"
                                        :key="project.id"
                                        :value="String(project.id)"
                                    >
                                        {{ project.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                size="sm"
                                :disabled="projectId === none"
                                data-test="ticket-convert-button"
                                @click="convert"
                            >
                                Convert
                            </Button>
                        </div>
                        <p
                            v-if="projects.length === 0"
                            class="text-muted-foreground text-xs"
                        >
                            {{ ticket.client.name }} has no projects yet.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <p class="text-muted-foreground text-xs">
                            Or link to an existing issue
                        </p>
                        <Input
                            v-model="issueQuery"
                            type="search"
                            placeholder="Search issues"
                            data-test="ticket-issue-search"
                            @input="searchIssues"
                        />
                        <ul
                            v-if="issueResults.length > 0"
                            class="divide-y rounded-lg border"
                        >
                            <li v-for="issue in issueResults" :key="issue.id">
                                <button
                                    type="button"
                                    class="hover:bg-muted/50 flex w-full items-start gap-2 px-3 py-2 text-left"
                                    @click="linkIssue(issue.id)"
                                >
                                    <Link2
                                        class="text-muted-foreground mt-0.5 size-3.5 shrink-0"
                                    />
                                    <span class="min-w-0">
                                        <span class="block truncate">{{
                                            issue.title
                                        }}</span>
                                        <span
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{ issue.reference }} ·
                                            {{ issue.statusLabel }}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div v-if="otherTickets.length > 0" class="space-y-2 border-t pt-5">
                <Heading variant="small" title="Earlier tickets" />
                <ul class="space-y-1">
                    <li v-for="other in otherTickets" :key="other.id">
                        <Link
                            :href="show([currentOrganization.slug, other.id])"
                            class="hover:bg-muted/50 -mx-2 flex items-center justify-between gap-2 rounded-md px-2 py-1.5"
                        >
                            <span class="min-w-0 truncate">
                                <span
                                    class="text-muted-foreground tabular-nums"
                                >
                                    {{ other.reference }}
                                </span>
                                {{ other.subject }}
                            </span>
                            <TicketStatusBadge
                                :status="other.status"
                                :label="other.statusLabel"
                            />
                        </Link>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    <ConfirmDeleteModal
        :open="commentToDelete !== null"
        title="Delete note"
        confirm-label="Delete"
        :href="
            commentToDelete
                ? destroyReply([...routeArgs(), commentToDelete.id])
                : null
        "
        @update:open="commentToDelete = null"
    >
        This internal note will be permanently removed.
    </ConfirmDeleteModal>
</template>
