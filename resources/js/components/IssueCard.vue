<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import IssuePriorityBadge from '@/components/IssuePriorityBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { show } from '@/routes/issues';
import type { SprintIssue } from '@/types';

defineProps<{
    organization: string;
    issue: SprintIssue;
}>();

const { getInitials } = useInitials();
</script>

<template>
    <article
        data-test="board-card"
        class="bg-card hover:border-foreground/20 cursor-grab space-y-2 rounded-lg border p-3 shadow-xs transition-colors active:cursor-grabbing"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-muted-foreground truncate text-xs tabular-nums">
                {{ issue.projectName }} #{{ issue.number }}
            </span>
            <span class="flex items-center gap-1.5">
                <Lightbulb
                    v-if="issue.hasFeature"
                    class="text-muted-foreground size-3.5"
                    aria-label="Converted from a feature request"
                />
                <IssuePriorityBadge
                    :priority="issue.priority"
                    :label="issue.priorityLabel"
                />
            </span>
        </div>
        <Link
            :href="show([organization, issue.id])"
            class="block text-sm leading-snug font-medium hover:underline"
        >
            {{ issue.title }}
        </Link>
        <div class="flex items-center justify-between gap-2">
            <span
                v-if="issue.points !== null"
                class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums"
                :title="`${issue.points} points`"
            >
                {{ issue.points }}
            </span>
            <span v-else />
            <Avatar
                v-if="issue.assigneeName"
                class="size-6"
                :title="issue.assigneeName"
            >
                <AvatarFallback class="text-[10px]">
                    {{ getInitials(issue.assigneeName) }}
                </AvatarFallback>
            </Avatar>
            <span v-else class="text-muted-foreground text-xs">Unassigned</span>
        </div>
    </article>
</template>
