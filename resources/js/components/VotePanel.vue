<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ThumbsDown, ThumbsUp } from '@lucide/vue';
import { computed, ref } from 'vue';
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
};

const props = defineProps<Props>();

const processing = ref(false);

const choices: {
    direction: VoteDirection;
    label: string;
    icon: typeof ThumbsUp;
    count: keyof FeedbackSummary;
    activeClass: string;
}[] = [
    {
        direction: 'up',
        label: 'For',
        icon: ThumbsUp,
        count: 'votesCount',
        activeClass:
            'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
    },
    {
        direction: 'down',
        label: 'Against',
        icon: ThumbsDown,
        count: 'downvotesCount',
        activeClass:
            'border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/15',
    },
];

const choiceClass =
    'flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm font-medium whitespace-nowrap tabular-nums transition-colors outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50';

const inactiveClass =
    'bg-background hover:bg-accent hover:text-accent-foreground';

const people = (count: number) =>
    count === 1 ? '1 person' : `${count} people`;

const others = (count: number) => (count === 1 ? '1 other' : `${count} others`);

const summary = computed(() => {
    const { votesCount, downvotesCount, vote } = props.feedback;

    if (votesCount + downvotesCount === 0) {
        return props.isGuest
            ? 'Log in to be the first to vote.'
            : 'No votes yet.';
    }

    const forLabel =
        vote === 'up'
            ? votesCount === 1
                ? 'You want this'
                : `You and ${others(votesCount - 1)} want this`
            : `${people(votesCount)} ${votesCount === 1 ? 'wants' : 'want'} this`;

    if (downvotesCount === 0) {
        return `${forLabel}.`;
    }

    const againstLabel =
        vote === 'down'
            ? downvotesCount === 1
                ? 'you would rather it never shipped'
                : `you and ${others(downvotesCount - 1)} would rather it never shipped`
            : `${people(downvotesCount)} would rather it never shipped`;

    return `${forLabel}, ${againstLabel}.`;
});

const vote = (direction: VoteDirection) => {
    const args: [string, string, number] = [
        props.organization,
        props.project,
        props.feedback.id,
    ];
    const withdrawing = props.feedback.vote === direction;

    router.visit(withdrawing ? withdrawVote(args) : castVote(args), {
        data: withdrawing ? {} : { direction },
        preserveScroll: true,
        preserveState: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
};
</script>

<template>
    <div class="space-y-3 rounded-xl border p-4" data-test="vote-panel">
        <div class="grid grid-cols-2 gap-2">
            <template v-for="choice in choices" :key="choice.direction">
                <Link
                    v-if="isGuest"
                    :href="login()"
                    :data-test="`vote-${choice.direction}-login-link`"
                    :class="cn(choiceClass, inactiveClass)"
                    :aria-label="`Log in to vote: ${choice.label}`"
                >
                    <span class="flex items-center gap-2">
                        <component :is="choice.icon" class="size-4" />
                        {{ choice.label }}
                    </span>
                    <span>{{ feedback[choice.count] }}</span>
                </Link>
                <button
                    v-else
                    type="button"
                    :data-test="`vote-${choice.direction}-button`"
                    :aria-pressed="feedback.vote === choice.direction"
                    :aria-label="
                        feedback.vote === choice.direction
                            ? 'Withdraw your vote'
                            : choice.label
                    "
                    :disabled="!canVote || processing"
                    :class="
                        cn(
                            choiceClass,
                            feedback.vote === choice.direction
                                ? choice.activeClass
                                : inactiveClass,
                        )
                    "
                    @click="vote(choice.direction)"
                >
                    <span class="flex items-center gap-2">
                        <component :is="choice.icon" class="size-4" />
                        {{ choice.label }}
                    </span>
                    <span>{{ feedback[choice.count] }}</span>
                </button>
            </template>
        </div>
        <p class="text-muted-foreground text-sm" data-test="vote-summary">
            {{ summary }}
        </p>
    </div>
</template>
