<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ListTodo, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityFeed from '@/components/ActivityFeed.vue';
import CommentForm from '@/components/CommentForm.vue';
import CommentThread from '@/components/CommentThread.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import FeatureStatusBadge from '@/components/FeatureStatusBadge.vue';
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
import { destroy, update } from '@/routes/portal/features';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/routes/portal/features/comments';
import { store as convertToIssue } from '@/routes/portal/features/issue';
import { update as updateStatus } from '@/routes/portal/features/status';
import { show as showBoard } from '@/routes/portal/projects';
import type {
    ActivityEntry,
    CommentCommand,
    CommentMention,
    FeatureComment,
    FeatureSummary,
    FeatureViewer,
    LinkedIssue,
    PortalOrganization,
    PortalProject,
    SelectOption,
} from '@/types';

type Props = {
    organization: PortalOrganization;
    project: PortalProject;
    feature: FeatureSummary;
    comments: FeatureComment[];
    mentionableUsers: CommentMention[];
    commands: CommentCommand[];
    activity: ActivityEntry[];
    issue: LinkedIssue | null;
    statuses: SelectOption[];
    viewer: FeatureViewer;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        organization: PortalOrganization;
        project: PortalProject;
        viewer: FeatureViewer;
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
    props.feature.id,
]);

const editing = ref(false);
const deleteDialogOpen = ref(false);
const commentToDelete = ref<FeatureComment | null>(null);
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

const commentCount = computed(() =>
    props.comments.reduce(
        (total, comment) => total + 1 + comment.replies.length,
        0,
    ),
);
</script>

<template>
    <Head :title="feature.title" />

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
                                data-test="edit-feature-title"
                                :default-value="feature.title"
                                required
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="description">Details</Label>
                            <Textarea
                                id="description"
                                name="description"
                                data-test="edit-feature-description"
                                :default-value="feature.description ?? ''"
                                class="min-h-32"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                data-test="edit-feature-save"
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
                                    {{ feature.title }}
                                </h1>
                                <div
                                    v-if="viewer.canUpdate || viewer.canDelete"
                                    class="flex shrink-0 items-center gap-1"
                                >
                                    <Button
                                        v-if="viewer.canUpdate"
                                        variant="ghost"
                                        size="icon-sm"
                                        data-test="edit-feature-button"
                                        aria-label="Edit feature"
                                        @click="editing = true"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        v-if="viewer.canDelete"
                                        variant="ghost"
                                        size="icon-sm"
                                        data-test="delete-feature-button"
                                        aria-label="Delete feature"
                                        @click="deleteDialogOpen = true"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </div>

                            <p class="text-muted-foreground text-sm">
                                <template v-if="feature.authorName">
                                    Requested by
                                    <span class="text-foreground font-medium">{{
                                        feature.authorName
                                    }}</span>
                                    on
                                </template>
                                <template v-else>Requested on</template>
                                {{ formatDate(feature.createdAt) }}
                            </p>
                        </div>

                        <p
                            v-if="feature.description"
                            class="max-w-prose leading-7 whitespace-pre-line"
                        >
                            {{ feature.description }}
                        </p>
                        <p v-else class="text-muted-foreground text-sm italic">
                            No details were added to this request.
                        </p>
                    </template>
                </article>

                <section class="space-y-6 border-t pt-8">
                    <h2 class="font-semibold">
                        {{ commentCount }}
                        {{ commentCount === 1 ? 'comment' : 'comments' }}
                    </h2>

                    <ol v-if="comments.length > 0" class="space-y-6">
                        <CommentThread
                            v-for="comment in comments"
                            :key="comment.id"
                            :comment="comment"
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
                </section>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <VotePanel
                    v-if="feature.acceptsVotes"
                    :organization="organization.slug"
                    :project="project.slug"
                    :feature="feature"
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
                                :model-value="feature.status"
                                @update:model-value="
                                    changeStatus(String($event))
                                "
                            >
                                <SelectTrigger
                                    class="w-full"
                                    data-test="feature-status-select"
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
                            <FeatureStatusBadge
                                v-else
                                :status="feature.status"
                                :label="feature.statusLabel"
                            />
                        </dd>
                    </div>
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Requested by
                        </dt>
                        <dd>{{ feature.authorName ?? 'Deleted user' }}</dd>
                    </div>
                    <div class="space-y-1.5">
                        <dt
                            class="text-muted-foreground text-xs font-medium tracking-wide uppercase"
                        >
                            Requested on
                        </dt>
                        <dd>{{ formatDate(feature.createdAt) }}</dd>
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
                        data-test="convert-feature-button"
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
        title="Delete feature request"
        confirm-label="Delete"
        :href="destroy(routeArgs)"
    >
        This permanently deletes <strong>{{ feature.title }}</strong> along with
        its votes and comments.
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
