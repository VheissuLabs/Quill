<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ListTodo, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ActivityFeed from '@/components/ActivityFeed.vue';
import CommentForm from '@/components/CommentForm.vue';
import CommentThread from '@/components/CommentThread.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import FeedbackStatusBadge from '@/components/FeedbackStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import VotePanel from '@/components/VotePanel.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { useInitials } from '@/composables/useInitials';
import { login } from '@/routes';
import { show as showIssue } from '@/routes/issues';
import { destroy, update } from '@/routes/portal/feedback';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/routes/portal/feedback/comments';
import { store as convertToIssue } from '@/routes/portal/feedback/issue';
import { update as updateStatus } from '@/routes/portal/feedback/status';
import { show as showBoard } from '@/routes/portal/projects';
import { useLocalStorage } from '@vueuse/core';
import { useCommentPresence } from '@/composables/useCommentPresence';
import { useLiveFeedback } from '@/composables/useLiveFeedback';
import type {
    ActivityEntry,
    CommentCommand,
    CommentMention,
    FeedbackComment,
    FeedbackSummary,
    FeedbackViewer,
    LinkedIssue,
    PortalOrganization,
    PortalProject,
    SelectOption,
} from '@/types';

type Props = {
    organization: PortalOrganization;
    project: PortalProject;
    feedback: FeedbackSummary;
    comments: FeedbackComment[];
    mentionableUsers: CommentMention[];
    commands: CommentCommand[];
    activity: ActivityEntry[];
    issue: LinkedIssue | null;
    statuses: SelectOption[];
    viewer: FeedbackViewer;
};

const props = defineProps<Props>();

useLiveFeedback(props.project.id, ['feedback', 'comments'], props.feedback.id);

const currentUser = usePage().props.auth.user;

const { typingLabel, reportTyping } = useCommentPresence(
    `feedback.${props.feedback.id}`,
    currentUser ? { id: currentUser.id, name: currentUser.name } : null,
);

defineOptions({
    layout: (props: {
        organization: PortalOrganization;
        project: PortalProject;
        viewer: FeedbackViewer;
    }) => ({
        organization: props.organization,
        project: props.project,
        isMember: props.viewer.isMember,
    }),
});

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const { getInitials } = useInitials();

const routeArgs = computed((): [string, string, number] => [
    props.organization.slug,
    props.project.slug,
    props.feedback.id,
]);

const editing = ref(false);
const editedDescription = ref(props.feedback.description ?? '');

watch(
    () => props.feedback.description,
    (value) => (editedDescription.value = value ?? ''),
);
const deleteDialogOpen = ref(false);
const commentToDelete = ref<FeedbackComment | null>(null);
const converting = ref(false);

const convert = () => {
    router.visit(convertToIssue(routeArgs.value), {
        onStart: () => (converting.value = true),
        onFinish: () => (converting.value = false),
    });
};

const changeStatus = (status: string) => {
    router.visit(updateStatus(routeArgs.value), {
        data: { status },
        preserveScroll: true,
    });
};

/**
 * Only the top level flips. A conversation read backwards is nonsense, so replies stay in the
 * order they were written.
 */
const newestFirst = useLocalStorage('quill.comments.newest-first', false);

const sortedComments = computed(() =>
    newestFirst.value ? [...props.comments].reverse() : props.comments,
);

const commentCount = computed(() =>
    props.comments.reduce(
        (total, comment) => total + 1 + comment.replies.length,
        0,
    ),
);
</script>

<template>
    <Head :title="feedback.title" />

    <div class="space-y-8">
        <Link
            :href="showBoard([organization.slug, project.slug])"
            class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
        >
            <ArrowLeft class="size-4" /> All requests for {{ project.name }}
        </Link>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="min-w-0 space-y-10">
                <article class="space-y-5">
                    <Form
                        v-if="editing"
                        v-bind="update.form(routeArgs)"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                        @success="editing = false"
                    >
                        <div class="grid gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                name="title"
                                data-test="edit-feedback-title"
                                :default-value="feedback.title"
                                required
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="description">Details</Label>
                            <input
                                type="hidden"
                                name="description"
                                :value="editedDescription"
                            />
                            <RichTextEditor
                                v-model="editedDescription"
                                data-test="edit-feedback-description"
                                class="min-h-32"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                data-test="edit-feedback-save"
                                :disabled="processing"
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                @click="editing = false"
                            >
                                Cancel
                            </Button>
                        </div>
                    </Form>

                    <template v-else>
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <h1
                                    class="text-3xl font-semibold tracking-tight text-balance"
                                >
                                    {{ feedback.title }}
                                </h1>
                                <div
                                    v-if="viewer.canUpdate || viewer.canDelete"
                                    class="flex shrink-0 items-center gap-1"
                                >
                                    <Button
                                        v-if="viewer.canUpdate"
                                        variant="ghost"
                                        size="icon-sm"
                                        data-test="edit-feedback-button"
                                        aria-label="Edit feedback"
                                        @click="editing = true"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        v-if="viewer.canDelete"
                                        variant="ghost"
                                        size="icon-sm"
                                        data-test="delete-feedback-button"
                                        aria-label="Delete feedback"
                                        @click="deleteDialogOpen = true"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </div>

                            <p class="text-muted-foreground text-sm">
                                <template v-if="feedback.authorName">
                                    Requested by
                                    <span class="text-foreground font-medium">{{
                                        feedback.authorName
                                    }}</span>
                                    on
                                </template>
                                <template v-else>Requested on</template>
                                {{ formatDate(feedback.createdAt) }}
                            </p>
                        </div>

                        <div
                            v-if="feedback.description"
                            class="prose prose-sm dark:prose-invert max-w-prose leading-7"
                            v-html="feedback.description"
                        />
                        <p v-else class="text-muted-foreground text-sm italic">
                            No details were added to this request.
                        </p>
                    </template>
                </article>

                <section class="space-y-6 border-t pt-8">
                    <h2
                        class="flex flex-wrap items-baseline gap-x-2 font-semibold"
                    >
                        <span>
                            {{ commentCount }}
                            {{ commentCount === 1 ? 'comment' : 'comments' }}
                        </span>
                        <span
                            v-if="typingLabel"
                            data-test="typing-indicator"
                            class="text-muted-foreground animate-pulse text-xs font-normal"
                        >
                            {{ typingLabel }}
                        </span>
                        <button
                            v-if="commentCount > 1"
                            type="button"
                            data-test="comment-sort"
                            class="text-muted-foreground hover:text-foreground ml-auto cursor-pointer text-xs font-normal"
                            @click="newestFirst = !newestFirst"
                        >
                            {{ newestFirst ? 'Newest first' : 'Oldest first' }}
                        </button>
                    </h2>

                    <div v-if="viewer.canComment" class="flex gap-3">
                        <Avatar class="size-8 shrink-0">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(page.props.auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1">
                            <CommentForm
                                :action="storeComment(routeArgs).url"
                                :organization-name="organization.name"
                                :mentionable="mentionableUsers"
                                :commands="commands"
                                @typing="reportTyping"
                                :statuses="statuses"
                                :can-comment-internally="
                                    viewer.canCommentInternally
                                "
                            />
                        </div>
                    </div>
                    <p
                        v-else-if="isGuest"
                        class="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-sm"
                    >
                        <Link :href="login()" class="text-foreground underline">
                            Log in
                        </Link>
                        to join the discussion.
                    </p>

                    <ol v-if="comments.length > 0" class="space-y-6">
                        <CommentThread
                            v-for="comment in sortedComments"
                            :key="comment.id"
                            :comment="comment"
                            @typing="reportTyping"
                            :action="storeComment(routeArgs).url"
                            :organization-name="organization.name"
                            :can-comment="viewer.canComment"
                            :can-comment-internally="
                                viewer.canCommentInternally
                            "
                            :mentionable="mentionableUsers"
                            @delete="commentToDelete = $event"
                        />
                    </ol>
                </section>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <VotePanel
                    v-if="feedback.acceptsVotes"
                    :organization="organization.slug"
                    :project="project.slug"
                    :feedback="feedback"
                    :can-vote="viewer.canVote"
                    :is-guest="isGuest"
                />

                <dl class="space-y-4 px-1 text-sm">
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Status
                        </dt>
                        <dd>
                            <Select
                                v-if="viewer.canUpdateStatus"
                                :model-value="feedback.status"
                                @update:model-value="
                                    changeStatus(String($event))
                                "
                            >
                                <SelectTrigger
                                    class="w-full"
                                    data-test="feedback-status-select"
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
                            <FeedbackStatusBadge
                                v-else
                                :status="feedback.status"
                                :label="feedback.statusLabel"
                            />
                        </dd>
                    </div>
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Type
                        </dt>
                        <dd data-test="feedback-type">
                            {{ feedback.typeLabel }}
                        </dd>
                    </div>
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Submitted by
                        </dt>
                        <dd>{{ feedback.authorName ?? 'Deleted user' }}</dd>
                    </div>
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Submitted on
                        </dt>
                        <dd>{{ formatDate(feedback.createdAt) }}</dd>
                    </div>
                </dl>

                <div
                    v-if="issue"
                    class="bg-muted/40 space-y-2 rounded-xl border p-4 text-sm"
                    data-test="linked-issue"
                >
                    <p
                        class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
                    >
                        <ListTodo class="size-3.5" /> Tracked as
                    </p>
                    <div class="flex items-center justify-between gap-2">
                        <Link
                            :href="showIssue([organization.slug, issue.id])"
                            class="font-medium hover:underline"
                        >
                            {{ issue.reference }}
                        </Link>
                        <IssueStatusBadge
                            :status="issue.status"
                            :label="issue.statusLabel"
                        />
                    </div>
                </div>
                <div
                    v-else-if="viewer.canConvert"
                    class="space-y-3 rounded-xl border border-dashed p-4 text-sm"
                >
                    <p class="text-muted-foreground">
                        Like the idea? Convert it into a backlog issue. The
                        request moves to Planned and keeps its votes and
                        discussion.
                    </p>
                    <Button
                        variant="outline"
                        class="w-full"
                        data-test="convert-feedback-button"
                        :disabled="converting"
                        @click="convert"
                    >
                        <ListTodo /> Convert to issue
                    </Button>
                </div>

                <ActivityFeed
                    v-if="viewer.canViewActivity"
                    class="border-t pt-5"
                    :entries="activity"
                    subject="request"
                />
            </aside>
        </div>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete feedback"
        confirm-label="Delete"
        :href="destroy(routeArgs)"
    >
        This permanently deletes <strong>{{ feedback.title }}</strong> along
        with its votes and comments.
    </ConfirmDeleteModal>

    <ConfirmDeleteModal
        :open="commentToDelete !== null"
        title="Delete comment"
        confirm-label="Delete"
        :href="
            commentToDelete
                ? destroyComment([...routeArgs, commentToDelete.id])
                : null
        "
        @update:open="commentToDelete = null"
    >
        This comment will be permanently removed.
    </ConfirmDeleteModal>
</template>
