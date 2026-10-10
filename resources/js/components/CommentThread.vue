<script setup lang="ts">
import { Lock, Mail, Paperclip, RotateCw, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CommentBody from '@/components/CommentBody.vue';
import CommentForm from '@/components/CommentForm.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/dates';
import type { CommentMention, FeedbackComment } from '@/types';

type Props = {
    comment: FeedbackComment;
    action: string;
    organizationName?: string;
    canComment: boolean;
    canCommentInternally?: boolean;
    mentionable: CommentMention[];
    attachmentHref?: (id: number) => string;
};

withDefaults(defineProps<Props>(), {
    organizationName: '',
    canCommentInternally: false,
    attachmentHref: undefined,
});

const emit = defineEmits<{
    delete: [comment: FeedbackComment];
    retry: [comment: FeedbackComment];
    typing: [];
}>();

const { getInitials } = useInitials();
const replying = ref(false);
</script>

<template>
    <li
        data-test="comment"
        :class="[
            'space-y-4',
            comment.isInternal && 'bg-muted/50 -mx-3 rounded-lg px-3 py-3',
        ]"
    >
        <div class="flex gap-3">
            <Avatar class="size-8 shrink-0">
                <AvatarFallback class="text-xs">
                    {{ getInitials(comment.authorName ?? '?') }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm">
                        <span class="font-medium">
                            {{ comment.authorName ?? 'Deleted user' }}
                        </span>
                        <span class="text-muted-foreground">
                            · {{ formatDate(comment.createdAt) }}
                        </span>
                        <span
                            v-if="comment.isInternal"
                            class="text-muted-foreground ml-2 inline-flex items-center gap-1 text-xs"
                        >
                            <Lock class="size-3" /> Internal
                        </span>
                        <span
                            v-if="comment.email"
                            class="text-muted-foreground ml-2 inline-flex items-center gap-1 text-xs"
                            data-test="comment-email"
                        >
                            <Mail class="size-3" />
                            {{
                                comment.email.direction === 'inbound'
                                    ? `from ${comment.email.from}`
                                    : `emailed to ${comment.email.to.join(', ')}`
                            }}
                        </span>
                        <span
                            v-if="comment.email?.deliveryStatus === 'failed'"
                            class="text-destructive ml-2 inline-flex items-center gap-1 text-xs font-medium"
                        >
                            Not delivered
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 underline"
                                data-test="retry-delivery-button"
                                @click="emit('retry', comment)"
                            >
                                <RotateCw class="size-3" /> Retry
                            </button>
                        </span>
                    </p>
                    <Button
                        v-if="comment.canDelete"
                        variant="ghost"
                        size="icon-sm"
                        class="-my-1.5"
                        data-test="delete-comment-button"
                        aria-label="Delete comment"
                        @click="emit('delete', comment)"
                    >
                        <Trash2 />
                    </Button>
                </div>
                <CommentBody
                    :body="comment.body"
                    :mentions="comment.mentions"
                />
                <ul
                    v-if="
                        comment.email &&
                        comment.email.attachments.length > 0 &&
                        attachmentHref
                    "
                    class="flex flex-wrap gap-2 pt-1"
                >
                    <li
                        v-for="attachment in comment.email.attachments"
                        :key="attachment.id"
                    >
                        <a
                            :href="attachmentHref(attachment.id)"
                            class="hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs"
                            data-test="comment-attachment"
                        >
                            <Paperclip class="size-3" />
                            {{ attachment.filename }}
                        </a>
                    </li>
                </ul>
                <button
                    v-if="canComment && !replying"
                    type="button"
                    data-test="reply-button"
                    class="text-muted-foreground hover:text-foreground text-xs font-medium"
                    @click="replying = true"
                >
                    Reply
                </button>
            </div>
        </div>

        <ol
            v-if="comment.replies.length > 0 || replying"
            class="ml-11 space-y-4 border-l pl-4"
        >
            <li
                v-for="reply in comment.replies"
                :key="reply.id"
                data-test="comment-reply"
                class="flex gap-3"
            >
                <Avatar class="size-7 shrink-0">
                    <AvatarFallback class="text-[10px]">
                        {{ getInitials(reply.authorName ?? '?') }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm">
                            <span class="font-medium">
                                {{ reply.authorName ?? 'Deleted user' }}
                            </span>
                            <span class="text-muted-foreground">
                                · {{ formatDate(reply.createdAt) }}
                            </span>
                        </p>
                        <Button
                            v-if="reply.canDelete"
                            variant="ghost"
                            size="icon-sm"
                            class="-my-1.5"
                            data-test="delete-comment-button"
                            aria-label="Delete reply"
                            @click="emit('delete', reply)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <CommentBody
                        :body="reply.body"
                        :mentions="reply.mentions"
                    />
                </div>
            </li>
            <li v-if="replying">
                <CommentForm
                    :action="action"
                    :organization-name="organizationName"
                    :mentionable="mentionable"
                    :can-comment-internally="canCommentInternally"
                    :parent-id="comment.id"
                    :force-internal="comment.isInternal"
                    :placeholder="`Reply to ${comment.authorName ?? 'this comment'}`"
                    submit-label="Reply"
                    autofocus
                    @typing="emit('typing')"
                    @posted="replying = false"
                    @cancel="replying = false"
                />
            </li>
        </ol>
    </li>
</template>
