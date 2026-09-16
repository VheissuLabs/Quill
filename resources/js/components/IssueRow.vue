<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import IssuePriorityBadge from '@/components/IssuePriorityBadge.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import { show } from '@/routes/issues';
import type { IssueListItem } from '@/types';

defineProps<{
    organization: string;
    issue: IssueListItem;
}>();
</script>

<template>
    <div class="flex min-w-2xl flex-1 items-center gap-4">
        <IssuePriorityBadge
            :priority="issue.priority"
            :label="issue.priorityLabel"
        />
        <span
            class="text-muted-foreground w-28 shrink-0 truncate text-xs tabular-nums"
        >
            {{ issue.project.name }} #{{ issue.number }}
        </span>
        <Link
            :href="show([organization, issue.id])"
            class="min-w-0 flex-1 truncate text-sm font-medium hover:underline"
        >
            {{ issue.title }}
        </Link>
        <span class="flex w-4 shrink-0 justify-center">
            <Lightbulb
                v-if="issue.hasFeature"
                class="text-muted-foreground size-3.5"
                aria-label="Converted from a feature request"
            />
        </span>
        <span class="flex w-8 shrink-0 justify-end">
            <span
                v-if="issue.points !== null"
                class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums"
            >
                {{ issue.points }}
            </span>
        </span>
        <IssueStatusBadge :status="issue.status" :label="issue.statusLabel" />
        <span
            class="text-muted-foreground w-28 shrink-0 truncate text-right text-xs"
        >
            {{ issue.assigneeName ?? 'Unassigned' }}
        </span>
    </div>
</template>
