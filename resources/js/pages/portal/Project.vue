<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Lightbulb, ListTodo, MessageSquare, Plus, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import FeedbackStatusBadge from '@/components/FeedbackStatusBadge.vue';
import SubmitFeedbackModal from '@/components/SubmitFeedbackModal.vue';
import VoteButton from '@/components/VoteButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { show as showIssue } from '@/routes/issues';
import { show as showFeedback } from '@/routes/portal/feedback';
import { show as showBoard } from '@/routes/portal/projects';
import { useLiveFeedback } from '@/composables/useLiveFeedback';
import type {
    BoardFilters,
    BoardStatusOption,
    BoardTypeOption,
    BoardViewer,
    FeedbackSummary,
    PortalOrganization,
    PortalProject,
} from '@/types';

type Props = {
    organization: PortalOrganization;
    project: PortalProject;
    feedback: FeedbackSummary[];
    filters: BoardFilters;
    statuses: BoardStatusOption[];
    types: BoardTypeOption[];
    activeCount: number;
    viewer: BoardViewer;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        organization: PortalOrganization;
        project: PortalProject;
        viewer: BoardViewer;
    }) => ({
        organization: props.organization,
        project: props.project,
        isMember: props.viewer.isMember,
    }),
});

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const refreshBoard = () =>
    router.reload({ only: ['feedback'], showProgress: false });

useLiveFeedback(props.project.id, {
    voted: refreshBoard,
    commented: refreshBoard,
});

const applyFilters = (changes: {
    status?: string | null;
    type?: string | null;
    sort?: string;
    search?: string | null;
}) => {
    const status =
        changes.status === undefined ? props.filters.status : changes.status;
    const type = changes.type === undefined ? props.filters.type : changes.type;
    const sort = changes.sort ?? props.filters.sort;
    const term =
        changes.search === undefined ? props.filters.search : changes.search;

    router.visit(
        showBoard([props.organization.slug, props.project.slug], {
            query: {
                status: status ?? undefined,
                type: type ?? undefined,
                sort: sort === 'top' ? undefined : sort,
                q: term ?? undefined,
            },
        }),
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const search = ref(props.filters.search ?? '');

const runSearch = useDebounceFn(
    () => applyFilters({ search: search.value.trim() || null }),
    250,
);

watch(
    () => props.filters.search,
    (value) => {
        search.value = value ?? '';
    },
);

const activeStatusLabel = computed(
    () =>
        props.statuses.find((status) => status.value === props.filters.status)
            ?.label,
);

const shownCountLabel = computed(() => {
    const count = props.feedback.length;
    const noun = count === 1 ? 'request' : 'requests';

    return activeStatusLabel.value
        ? `${count} ${activeStatusLabel.value.toLowerCase()} ${noun}`
        : `${count} ${noun}`;
});

const sortButtonClass = (sort: BoardFilters['sort']) =>
    cn(
        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
        props.filters.sort === sort
            ? 'bg-background text-foreground shadow-xs'
            : 'text-muted-foreground hover:text-foreground',
    );

const typeRowClass = (type: FeedbackSummary['type'] | null) =>
    cn(
        'flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-sm transition-colors',
        props.filters.type === type
            ? 'bg-accent text-accent-foreground font-medium'
            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
    );

const statusRowClass = (status: FeedbackSummary['status'] | null) =>
    cn(
        'flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-sm transition-colors',
        props.filters.status === status
            ? 'bg-accent text-accent-foreground font-medium'
            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
    );
</script>

<template>
    <Head :title="`${project.name} feedback`" />

    <div class="space-y-10">
        <section
            class="flex flex-wrap items-end justify-between gap-6 border-b pb-8"
        >
            <div class="max-w-2xl min-w-0 space-y-3">
                <h1 class="text-4xl font-semibold tracking-tight">
                    {{ project.name }}
                </h1>
                <p class="text-muted-foreground text-lg leading-relaxed">
                    {{
                        project.description ??
                        'Tell us what you need next. Vote for the requests that matter most to you.'
                    }}
                </p>
            </div>

            <SubmitFeedbackModal
                v-if="viewer.canCreateFeedback"
                :organization="organization.slug"
                :project="project.slug"
            >
                <Button size="lg" data-test="request-feedback-button">
                    <Plus /> Submit feedback
                </Button>
            </SubmitFeedbackModal>
            <Button v-else-if="isGuest" size="lg" as-child>
                <Link :href="login()"> Log in to submit feedback </Link>
            </Button>
        </section>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_280px]">
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div
                        class="bg-muted inline-flex rounded-lg p-0.5"
                        role="group"
                        aria-label="Sort"
                    >
                        <button
                            type="button"
                            data-test="sort-top"
                            :class="sortButtonClass('top')"
                            @click="applyFilters({ sort: 'top' })"
                        >
                            Top
                        </button>
                        <button
                            type="button"
                            data-test="sort-new"
                            :class="sortButtonClass('new')"
                            @click="applyFilters({ sort: 'new' })"
                        >
                            New
                        </button>
                    </div>
                    <div class="relative">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search requests"
                            class="h-9 w-56 pl-8"
                            data-test="feedback-search"
                            @input="runSearch"
                        />
                    </div>

                    <span class="text-muted-foreground text-sm tabular-nums">
                        {{ shownCountLabel }}
                    </span>
                </div>

                <div v-if="feedback.length > 0" class="space-y-3">
                    <article
                        v-for="feedback in feedback"
                        :key="feedback.id"
                        data-test="feedback-row"
                        class="bg-card hover:border-foreground/20 flex gap-4 rounded-xl border p-4 transition-colors sm:p-5"
                    >
                        <VoteButton
                            v-if="feedback.acceptsVotes"
                            :organization="organization.slug"
                            :project="project.slug"
                            :feedback="feedback"
                            :can-vote="viewer.canCreateFeedback"
                            :is-guest="isGuest"
                        />

                        <div class="min-w-0 flex-1 space-y-2">
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1"
                            >
                                <Link
                                    :href="
                                        showFeedback([
                                            organization.slug,
                                            project.slug,
                                            feedback.id,
                                        ])
                                    "
                                    class="text-base font-medium hover:underline"
                                >
                                    {{ feedback.title }}
                                </Link>
                                <FeedbackStatusBadge
                                    v-if="feedback.status !== 'open'"
                                    :status="feedback.status"
                                    :label="feedback.statusLabel"
                                />
                            </div>
                            <div
                                v-if="feedback.description"
                                class="text-muted-foreground line-clamp-2 text-sm leading-relaxed [&_p]:inline"
                                v-html="feedback.description"
                            />
                            <div
                                class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                            >
                                <span
                                    v-if="feedback.type === 'bug'"
                                    data-test="feedback-type-badge"
                                    class="text-destructive border-destructive/30 rounded-full border px-2 py-0.5 font-medium"
                                >
                                    {{ feedback.typeLabel }}
                                </span>
                                <span v-if="feedback.authorName">
                                    {{ feedback.authorName }}
                                </span>
                                <span>{{
                                    formatDate(feedback.createdAt)
                                }}</span>
                                <Link
                                    :href="
                                        showFeedback([
                                            organization.slug,
                                            project.slug,
                                            feedback.id,
                                        ])
                                    "
                                    class="hover:text-foreground inline-flex items-center gap-1"
                                >
                                    <MessageSquare class="size-3.5" />
                                    {{ feedback.commentsCount }}
                                    {{
                                        feedback.commentsCount === 1
                                            ? 'comment'
                                            : 'comments'
                                    }}
                                </Link>
                                <Link
                                    v-if="feedback.issue"
                                    :href="
                                        showIssue([
                                            organization.slug,
                                            feedback.issue.id,
                                        ])
                                    "
                                    class="hover:text-foreground inline-flex items-center gap-1"
                                    data-test="tracked-issue-link"
                                >
                                    <ListTodo class="size-3.5" />
                                    Tracked as {{ feedback.issue.reference }}
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-else-if="filters.status"
                    :icon="Lightbulb"
                    :title="`Nothing ${activeStatusLabel?.toLowerCase()} yet`"
                    description="Try another status, or clear the filter to see every request."
                >
                    <Button
                        variant="outline"
                        @click="applyFilters({ status: null })"
                    >
                        Show all requests
                    </Button>
                </EmptyState>

                <EmptyState
                    v-else
                    :icon="Lightbulb"
                    title="No feedback yet"
                    :description="
                        viewer.canCreateFeedback
                            ? 'Be the first to ask for something. Others can vote on it.'
                            : isGuest
                              ? 'Log in to submit feedback and vote on what gets built next.'
                              : 'Requests will show up here once people start asking.'
                    "
                >
                    <SubmitFeedbackModal
                        v-if="viewer.canCreateFeedback"
                        :organization="organization.slug"
                        :project="project.slug"
                    >
                        <Button variant="outline">
                            <Plus /> Submit feedback
                        </Button>
                    </SubmitFeedbackModal>
                    <Button v-else-if="isGuest" variant="outline" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                </EmptyState>
            </section>

            <aside class="space-y-8 lg:sticky lg:top-24 lg:self-start">
                <nav aria-label="Filter by status" class="space-y-1">
                    <p
                        class="text-muted-foreground mb-2 px-2.5 text-xs font-medium tracking-wide uppercase"
                    >
                        Status
                    </p>
                    <button
                        type="button"
                        data-test="status-filter-all"
                        :class="statusRowClass(null)"
                        @click="applyFilters({ status: null })"
                    >
                        <span>Active requests</span>
                        <span class="tabular-nums">{{ activeCount }}</span>
                    </button>
                    <button
                        v-for="status in statuses"
                        :key="status.value"
                        type="button"
                        data-test="status-filter"
                        :class="statusRowClass(status.value)"
                        @click="applyFilters({ status: status.value })"
                    >
                        <span>{{ status.label }}</span>
                        <span class="tabular-nums">{{ status.count }}</span>
                    </button>
                </nav>

                <nav class="space-y-1 border-t pt-6">
                    <p
                        class="text-muted-foreground px-2.5 pb-1 text-xs font-medium tracking-wide uppercase"
                    >
                        Type
                    </p>
                    <button
                        type="button"
                        data-test="type-filter-all"
                        :class="typeRowClass(null)"
                        @click="applyFilters({ type: null })"
                    >
                        <span>Everything</span>
                    </button>
                    <button
                        v-for="option in types"
                        :key="option.value"
                        type="button"
                        data-test="type-filter"
                        :class="typeRowClass(option.value)"
                        @click="applyFilters({ type: option.value })"
                    >
                        <span>{{ option.label }}</span>
                        <span class="tabular-nums">{{ option.count }}</span>
                    </button>
                </nav>

                <div class="space-y-3 border-t pt-6">
                    <p
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        How it works
                    </p>
                    <ol
                        class="text-muted-foreground space-y-2 text-sm leading-relaxed"
                    >
                        <li>
                            Submit feedback, or find something that already
                            matches.
                        </li>
                        <li>Vote for the requests you want most.</li>
                        <li>
                            Follow the status as {{ organization.name }} plans,
                            builds and ships.
                        </li>
                    </ol>
                </div>
            </aside>
        </div>
    </div>
</template>
