<script setup lang="ts">
import { Lock, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CommentBody from '@/components/CommentBody.vue';
import CommentForm from '@/components/CommentForm.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/dates';
import type { CommentMention, FeatureComment } from '@/types';

type Props = {
    comment: FeatureComment;
    action: string;
    organizationName?: string;
    canComment: boolean;
    canCommentInternally?: boolean;
    mentionable: CommentMention[];
};

withDefaults(defineProps<Props>(), {
    organizationName: '',
    canCommentInternally: false,
});

const emit = defineEmits<{
    delete: [comment: FeatureComment];
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
                    @posted="replying = false"
                    @cancel="replying = false"
                />
            </li>
        </ol>
    </li>
</template>
