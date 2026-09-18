<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import {
    destroy as withdrawVote,
    store as castVote,
} from '@/routes/portal/feedback/vote';
import type { FeedbackSummary, VoteDirection } from '@/types';

type Props = {
    organization: string;
    project: string;
    feedback: FeedbackSummary;
    canVote: boolean;
    isGuest: boolean;
    size?: 'default' | 'lg';
};

const props = withDefaults(defineProps<Props>(), {
    size: 'default',
});

const processing = ref(false);

const containerClass =
    'flex shrink-0 flex-col items-center self-center rounded-lg border bg-background font-semibold tabular-nums';

const sizeClass = {
    default: 'min-w-12 text-sm [&_svg]:size-4',
    lg: 'min-w-14 text-base [&_svg]:size-5',
};

const arrowClass =
    'flex w-full items-center justify-center py-1 transition-colors outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50';

const inactiveArrowClass =
    'text-muted-foreground/60 hover:bg-accent hover:text-foreground';

const activeArrowClass = '[&_svg]:stroke-[2.75]';

const vote = (direction: VoteDirection) => {
    const args: [string, string, number] = [
        props.organization,
        props.project,
        props.feedback.id,
    ];

    const visit =
        props.feedback.vote === direction ? withdrawVote(args) : castVote(args);

    router.visit(visit, {
        data: props.feedback.vote === direction ? {} : { direction },
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
};
</script>

<template>
    <Link
        v-if="isGuest"
        :href="login()"
        data-test="vote-login-link"
        :class="
            cn(
                containerClass,
                sizeClass[size],
                'hover:bg-accent hover:text-accent-foreground text-muted-foreground transition-colors',
            )
        "
        aria-label="Log in to vote"
    >
        <span class="py-1"><ChevronUp /></span>
        <span class="text-foreground">{{ feedback.score }}</span>
        <span class="py-1"><ChevronDown /></span>
    </Link>

    <div
        v-else
        :class="cn(containerClass, sizeClass[size])"
        data-test="vote-control"
    >
        <button
            type="button"
            data-test="vote-up-button"
            :aria-pressed="feedback.vote === 'up'"
            :aria-label="
                feedback.vote === 'up'
                    ? 'Withdraw your vote'
                    : 'Vote for this feedback'
            "
            :disabled="!canVote || processing"
            :class="
                cn(
                    arrowClass,
                    'rounded-t-[calc(var(--radius-lg)-1px)]',
                    feedback.vote === 'up'
                        ? cn(activeArrowClass, 'text-primary hover:bg-accent')
                        : inactiveArrowClass,
                )
            "
            @click="vote('up')"
        >
            <ChevronUp />
        </button>
        <span
            data-test="vote-score"
            :class="
                cn('leading-none', feedback.score < 0 && 'text-destructive')
            "
        >
            {{ feedback.score }}
        </span>
        <button
            type="button"
            data-test="vote-down-button"
            :aria-pressed="feedback.vote === 'down'"
            :aria-label="
                feedback.vote === 'down'
                    ? 'Withdraw your vote'
                    : 'Vote against this feedback'
            "
            :disabled="!canVote || processing"
            :class="
                cn(
                    arrowClass,
                    'rounded-b-[calc(var(--radius-lg)-1px)]',
                    feedback.vote === 'down'
                        ? cn(
                              activeArrowClass,
                              'text-destructive/80 hover:bg-accent',
                          )
                        : inactiveArrowClass,
                )
            "
            @click="vote('down')"
        >
            <ChevronDown />
        </button>
    </div>
</template>
