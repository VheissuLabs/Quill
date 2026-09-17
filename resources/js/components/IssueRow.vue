<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Timer } from '@lucide/vue';
import { computed } from 'vue';
import IssuePriorityBadge from '@/components/IssuePriorityBadge.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show } from '@/routes/issues';
import type { IssueListItem, IssueSprintOption } from '@/types';

const {
    issue,
    sprints = [],
    currentSprintId = null,
} = defineProps<{
    organization: string;
    issue: IssueListItem;
    sprints?: IssueSprintOption[];
    currentSprintId?: number | null;
}>();

const emit = defineEmits<{ plan: [sprintId: number | null] }>();

const canPlan = computed(() => sprints.length > 0);

const currentSprint = computed(
    () => sprints.find((sprint) => sprint.id === currentSprintId) ?? null,
);

const planned = computed(() => currentSprintId !== null);

const page = usePage();

const assignedToMe = computed(
    () =>
        issue.assigneeId !== null &&
        issue.assigneeId === page.props.auth.user?.id,
);
</script>

<template>
    <div class="flex min-w-2xl flex-1 items-center gap-4">
        <IssuePriorityBadge
            :priority="issue.priority"
            :label="issue.priorityLabel"
        />

        <span class="flex w-8 shrink-0 justify-end">
            <span
                v-if="issue.points !== null"
                class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums"
            >
                {{ issue.points }}
            </span>
        </span>

        <div class="min-w-0 flex-1">
            <Link
                :href="show([organization, issue.id])"
                class="block truncate text-sm font-medium hover:underline"
            >
                {{ issue.title }}
            </Link>
            <span
                class="text-muted-foreground block truncate text-xs tabular-nums"
            >
                {{ issue.project.name }} #{{ issue.number }}
            </span>
        </div>

        <DropdownMenu v-if="canPlan">
            <DropdownMenuTrigger
                :data-test="planned ? 'issue-sprint-chip' : 'issue-status-chip'"
                :aria-label="
                    planned
                        ? `Move ${issue.reference} to another sprint`
                        : `Plan ${issue.reference} into a sprint`
                "
                class="cursor-pointer rounded-md focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                @pointerdown.stop
            >
                <Badge
                    v-if="planned"
                    variant="outline"
                    class="text-muted-foreground gap-1"
                >
                    <Timer class="size-3" />
                    {{ currentSprint?.name ?? 'Sprint' }}
                </Badge>
                <IssueStatusBadge
                    v-else
                    :status="issue.status"
                    :label="issue.statusLabel"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuItem
                    v-for="sprint in sprints"
                    :key="sprint.id"
                    :disabled="sprint.id === currentSprintId"
                    data-test="issue-plan-sprint"
                    @select="emit('plan', sprint.id)"
                >
                    <Timer />
                    <span class="truncate">{{ sprint.name }}</span>
                    <Check
                        v-if="sprint.id === currentSprintId"
                        class="ml-auto size-3.5"
                    />
                    <span
                        v-else
                        class="text-muted-foreground ml-auto text-xs capitalize"
                    >
                        {{ sprint.statusLabel }}
                    </span>
                </DropdownMenuItem>
                <template v-if="planned">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        data-test="issue-unplan"
                        @select="emit('plan', null)"
                    >
                        Move to backlog
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <IssueStatusBadge
            v-if="!canPlan || planned"
            :status="issue.status"
            :label="issue.statusLabel"
        />

        <span
            :class="[
                'w-28 shrink-0 truncate text-right text-xs',
                assignedToMe
                    ? 'text-foreground font-medium'
                    : 'text-muted-foreground',
            ]"
            :data-test="assignedToMe ? 'issue-assignee-me' : 'issue-assignee'"
        >
            {{ issue.assigneeName ?? 'Unassigned' }}
        </span>
    </div>
</template>
