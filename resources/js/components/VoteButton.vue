<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import {
    destroy as withdrawVote,
    store as castVote,
} from '@/routes/portal/features/vote';
import type { FeatureSummary, VoteDirection } from '@/types';

type Props = {
    organization: string;
    project: string;
    feature: FeatureSummary;
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
    'text-muted-foreground hover:bg-accent hover:text-accent-foreground';

const vote = (direction: VoteDirection) => {
    const args: [string, string, number] = [
        props.organization,
        props.project,
        props.feature.id,
    ];

    const visit =
        props.feature.vote === direction ? withdrawVote(args) : castVote(args);

    router.visit(visit, {
        data: props.feature.vote === direction ? {} : { direction },
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
        <span class="text-foreground">{{ feature.score }}</span>
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
            :aria-pressed="feature.vote === 'up'"
            :aria-label="
                feature.vote === 'up'
                    ? 'Withdraw your vote'
                    : 'Vote for this feature'
            "
            :disabled="!canVote || processing"
            :class="
                cn(
                    arrowClass,
                    'rounded-t-[calc(var(--radius-lg)-1px)]',
                    feature.vote === 'up'
                        ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                        : inactiveArrowClass,
                )
            "
            @click="vote('up')"
        >
            <ChevronUp />
        </button>
        <span
            data-test="vote-score"
            :class="cn('leading-none', feature.score < 0 && 'text-destructive')"
        >
            {{ feature.score }}
        </span>
        <button
            type="button"
            data-test="vote-down-button"
            :aria-pressed="feature.vote === 'down'"
            :aria-label="
                feature.vote === 'down'
                    ? 'Withdraw your vote'
                    : 'Vote against this feature'
            "
            :disabled="!canVote || processing"
            :class="
                cn(
                    arrowClass,
                    'rounded-b-[calc(var(--radius-lg)-1px)]',
                    feature.vote === 'down'
                        ? 'bg-destructive hover:bg-destructive/90 text-white'
                        : inactiveArrowClass,
                )
            "
            @click="vote('down')"
        >
            <ChevronDown />
        </button>
    </div>
</template>
