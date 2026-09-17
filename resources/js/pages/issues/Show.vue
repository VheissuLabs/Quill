<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Lightbulb, Pencil, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import ActivityFeed from '@/components/ActivityFeed.vue';
import CommentForm from '@/components/CommentForm.vue';
import CommentThread from '@/components/CommentThread.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import FeatureStatusBadge from '@/components/FeatureStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, show, update } from '@/routes/issues';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/routes/issues/comments';
import { show as showFeature } from '@/routes/portal/features';
import { pointOptions } from '@/lib/points';
import { show as showSprint } from '@/routes/sprints';
import type {
    CommentCommand,
    CommentMention,
    FeatureComment,
    ActivityEntry,
    IssueDetail,
    IssueFormOptions,
    IssueOriginFeature,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = IssueFormOptions & {
    currentOrganization: Organization;
    issue: IssueDetail;
    comments: FeatureComment[];
    commands: CommentCommand[];
    mentionables: CommentMention[];
    feature: IssueOriginFeature | null;
    activity: ActivityEntry[];
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        issue: IssueDetail;
    }) => ({
        breadcrumbs: [
            {
                title: 'Issues',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.issue.reference,
                href: show([props.currentOrganization.slug, props.issue.id]),
            },
        ],
    }),
});

const none = 'none';
const editing = ref<'title' | 'description' | null>(null);
const deleteDialogOpen = ref(false);
const commentToDelete = ref<FeatureComment | null>(null);

const routeArgs = computed((): [string, number] => [
    props.currentOrganization.slug,
    props.issue.id,
]);

const detailsForm = useForm({
    title: props.issue.title,
    description: props.issue.description ?? '',
});

const startEditing = (field: 'title' | 'description') => {
    detailsForm.title = props.issue.title;
    detailsForm.description = props.issue.description ?? '';
    editing.value = field;

    void nextTick(() => document.getElementById(field)?.focus());
};

const saveDetails = () => {
    detailsForm.submit(update(routeArgs.value), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
};

const patch = (data: Record<string, string | number | null>) => {
    router.visit(update(routeArgs.value), { data, preserveScroll: true });
};

const toId = (value: string) => (value === none ? null : Number(value));

/**
 * While an issue is on a board its stage is one of the team's columns; off the board it is a
 * plain status. Both are offered in one select, encoded as "column:ID" or "status:VALUE".
 */
const stageOptions = computed(() => {
    const onBoard =
        props.issue.sprintId !== null && props.issue.columns.length > 0;

    if (onBoard) {
        return [
            ...props.issue.columns.map((column) => ({
                value: `column:${column.id}`,
                label: column.name,
            })),
            { value: 'status:cancelled', label: 'Cancelled' },
        ];
    }

    return props.statuses
        .filter((status) =>
            ['backlog', 'done', 'cancelled'].includes(status.value),
        )
        .map((status) => ({
            value: `status:${status.value}`,
            label: status.label,
        }));
});

const currentStage = computed(() =>
    props.issue.boardColumnId
        ? `column:${props.issue.boardColumnId}`
        : `status:${props.issue.status}`,
);

const changeStage = (value: string) => {
    const [kind, id] = value.split(':');

    patch(kind === 'column' ? { board_column_id: Number(id) } : { status: id });
};

const sprintsForTeam = computed(() =>
    props.issue.teamId
        ? props.sprints.filter((sprint) => sprint.teamId === props.issue.teamId)
        : props.sprints,
);

const commandStatuses = computed(() =>
    props.statuses.filter((status) =>
        ['backlog', 'done', 'cancelled'].includes(status.value),
    ),
);

const currentSprint = computed(() =>
    props.sprints.find((sprint) => sprint.id === props.issue.sprintId),
);
</script>

<template>
    <Head :title="`${issue.reference} ${issue.title}`" />

    <div class="grid gap-10 p-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-10">
            <div class="space-y-4">
                <p class="text-muted-foreground text-sm tabular-nums">
                    {{ issue.reference }}
                </p>

                <form
                    v-if="editing !== null"
                    class="space-y-4"
                    @submit.prevent="saveDetails"
                >
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            v-model="detailsForm.title"
                            data-test="edit-issue-title"
                            required
                        />
                        <InputError :message="detailsForm.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="detailsForm.description"
                            data-test="edit-issue-description"
                            class="min-h-40"
                        />
                        <InputError :message="detailsForm.errors.description" />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            data-test="edit-issue-save"
                            :disabled="detailsForm.processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="editing = null"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>

                <template v-else>
                    <div class="flex items-start justify-between gap-4">
                        <h1
                            class="hover:bg-muted/60 -mx-2 cursor-text rounded-md px-2 text-2xl font-semibold tracking-tight text-balance"
                            role="button"
                            tabindex="0"
                            data-test="issue-title"
                            title="Click to edit"
                            @click="startEditing('title')"
                            @keydown.enter.prevent="startEditing('title')"
                        >
                            {{ issue.title }}
                        </h1>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                data-test="edit-issue-button"
                                aria-label="Edit issue"
                                @click="startEditing('title')"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                v-if="permissions.canManageIssues"
                                variant="ghost"
                                size="icon-sm"
                                data-test="delete-issue-button"
                                aria-label="Delete issue"
                                @click="deleteDialogOpen = true"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                    <p
                        v-if="issue.description"
                        class="hover:bg-muted/60 -mx-2 max-w-prose cursor-text rounded-md px-2 leading-7 whitespace-pre-line"
                        role="button"
                        tabindex="0"
                        data-test="issue-description"
                        title="Click to edit"
                        @click="startEditing('description')"
                        @keydown.enter.prevent="startEditing('description')"
                    >
                        {{ issue.description }}
                    </p>
                    <p
                        v-else
                        class="hover:bg-muted/60 text-muted-foreground -mx-2 cursor-text rounded-md px-2 text-sm italic"
                        role="button"
                        tabindex="0"
                        data-test="issue-description"
                        @click="startEditing('description')"
                        @keydown.enter.prevent="startEditing('description')"
                    >
                        No description yet.
                    </p>
                </template>
            </div>

            <section v-if="feature" class="space-y-5 border-t pt-8">
                <Heading variant="small" title="Context" />

                <div class="rounded-xl border p-5">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg"
                            >
                                <Lightbulb
                                    class="text-muted-foreground size-4"
                                />
                            </span>
                            <div>
                                <Link
                                    :href="
                                        showFeature([
                                            currentOrganization.slug,
                                            issue.project.slug,
                                            feature.id,
                                        ])
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ feature.title }}
                                </Link>
                                <p class="text-muted-foreground text-sm">
                                    Requested
                                    <template v-if="feature.authorName">
                                        by {{ feature.authorName }}
                                    </template>
                                    on {{ formatDate(feature.createdAt) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-muted-foreground inline-flex items-center gap-1 text-sm tabular-nums"
                            >
                                <ChevronUp class="size-4" />
                                {{ feature.votesCount }}
                            </span>
                            <span
                                v-if="feature.downvotesCount > 0"
                                class="text-destructive inline-flex items-center gap-1 text-sm tabular-nums"
                            >
                                <ChevronDown class="size-4" />
                                {{ feature.downvotesCount }}
                            </span>
                            <FeatureStatusBadge
                                :status="feature.status"
                                :label="feature.statusLabel"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t pt-8"
                data-test="issue-discussion"
            >
                <Heading variant="small" title="Discussion" />

                <ol v-if="comments.length > 0" class="space-y-6">
                    <CommentThread
                        v-for="comment in comments"
                        :key="comment.id"
                        :comment="comment"
                        :action="storeComment(routeArgs).url"
                        :can-comment="true"
                        :mentionable="mentionables"
                        @delete="commentToDelete = $event"
                    />
                </ol>
                <p v-else class="text-muted-foreground text-sm italic">
                    Nothing here yet. Start the conversation.
                </p>

                <CommentForm
                    :action="storeComment(routeArgs).url"
                    :mentionable="mentionables"
                    :commands="commands"
                    :statuses="commandStatuses"
                />
            </section>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
            <dl class="space-y-4 text-sm">
                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Status
                    </dt>
                    <dd>
                        <Select
                            :model-value="currentStage"
                            @update:model-value="changeStage(String($event))"
                        >
                            <SelectTrigger
                                class="w-full"
                                data-test="issue-status-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in stageOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Priority
                    </dt>
                    <dd>
                        <Select
                            :model-value="issue.priority"
                            @update:model-value="
                                patch({ priority: String($event) })
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="priority in priorities"
                                    :key="priority.value"
                                    :value="priority.value"
                                >
                                    {{ priority.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Points
                    </dt>
                    <dd>
                        <Select
                            :model-value="
                                issue.points === null
                                    ? none
                                    : String(issue.points)
                            "
                            @update:model-value="
                                patch({ points: toId(String($event)) })
                            "
                        >
                            <SelectTrigger
                                class="w-full"
                                data-test="issue-points-select"
                            >
                                <SelectValue placeholder="Estimate" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none"
                                    >No estimate</SelectItem
                                >
                                <SelectItem
                                    v-for="option in pointOptions(issue.points)"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Assignee
                    </dt>
                    <dd>
                        <Select
                            :model-value="
                                issue.assigneeId
                                    ? String(issue.assigneeId)
                                    : none
                            "
                            @update:model-value="
                                patch({ assignee_id: toId(String($event)) })
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none"
                                    >Unassigned</SelectItem
                                >
                                <SelectItem
                                    v-for="member in members"
                                    :key="member.id"
                                    :value="String(member.id)"
                                >
                                    {{ member.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Team
                    </dt>
                    <dd>
                        <Select
                            :model-value="
                                issue.teamId ? String(issue.teamId) : none
                            "
                            @update:model-value="
                                patch({ team_id: toId(String($event)) })
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none">No team</SelectItem>
                                <SelectItem
                                    v-for="team in teams"
                                    :key="team.id"
                                    :value="String(team.id)"
                                >
                                    {{ team.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </dd>
                </div>

                <div class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Sprint
                    </dt>
                    <dd class="space-y-1.5">
                        <Select
                            :model-value="
                                issue.sprintId ? String(issue.sprintId) : none
                            "
                            @update:model-value="
                                patch({ sprint_id: toId(String($event)) })
                            "
                        >
                            <SelectTrigger
                                class="w-full"
                                data-test="issue-sprint-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none"
                                    >Not planned</SelectItem
                                >
                                <SelectItem
                                    v-for="sprint in sprintsForTeam"
                                    :key="sprint.id"
                                    :value="String(sprint.id)"
                                >
                                    {{ sprint.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Link
                            v-if="currentSprint"
                            :href="
                                showSprint([
                                    currentOrganization.slug,
                                    currentSprint.id,
                                ])
                            "
                            class="text-muted-foreground hover:text-foreground block text-xs underline underline-offset-4"
                        >
                            Open sprint board
                        </Link>
                    </dd>
                </div>

                <div class="space-y-1.5 border-t pt-4">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Created
                    </dt>
                    <dd>
                        {{ formatDate(issue.createdAt) }}
                        <span
                            v-if="issue.creatorName"
                            class="text-muted-foreground"
                        >
                            by {{ issue.creatorName }}
                        </span>
                    </dd>
                </div>
                <div v-if="issue.completedAt" class="space-y-1.5">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                    >
                        Completed
                    </dt>
                    <dd>{{ formatDate(issue.completedAt) }}</dd>
                </div>
            </dl>

            <ActivityFeed
                class="border-t pt-5"
                :entries="activity"
                subject="issue"
            />

            <IssueStatusBadge
                class="sr-only"
                :status="issue.status"
                :label="issue.statusLabel"
            />
        </aside>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete issue"
        confirm-label="Delete issue"
        :href="destroy(routeArgs)"
    >
        This permanently deletes <strong>{{ issue.reference }}</strong
        >. The feature request it came from, if any, is kept.
    </ConfirmDeleteModal>
</template>
