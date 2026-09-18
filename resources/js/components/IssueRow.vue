<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Timer, Users } from '@lucide/vue';
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
import { useRowDrag } from '@/composables/useRowDrag';
import { show } from '@/routes/issues';
import type {
    BoardColumn,
    IssueListItem,
    IssueMemberOption,
    IssueSprintOption,
    IssueTeamOption,
} from '@/types';

const {
    issue,
    sprints = [],
    teams = [],
    members = [],
    columns = [],
    currentSprintId = null,
} = defineProps<{
    organization: string;
    issue: IssueListItem;
    sprints?: IssueSprintOption[];
    teams?: IssueTeamOption[];
    members?: IssueMemberOption[];
    columns?: BoardColumn[];
    currentSprintId?: number | null;
}>();

const emit = defineEmits<{
    plan: [sprintId: number | null];
    assignTeam: [teamId: number | null];
    assign: [userId: number | null];
    moveTo: [columnId: number];
}>();

const canPlan = computed(() => sprints.length > 0);

const currentSprint = computed(
    () => sprints.find((sprint) => sprint.id === currentSprintId) ?? null,
);

const planned = computed(() => currentSprintId !== null);

/**
 * The chip reports where the issue sits, so it only means something on a board that runs
 * sprints. Without them an unplanned issue has no location to report, and backlog is not a
 * column anyone configured.
 */
const showsStatus = computed(
    () => planned.value || (!canPlan.value && issue.status !== 'backlog'),
);

const page = usePage();

const assignedToMe = computed(
    () =>
        issue.assigneeId !== null &&
        issue.assigneeId === page.props.auth.user?.id,
);

const { draggedJustNow } = useRowDrag();

/**
 * Caught on the way down, before Inertia's own click handler on the link can navigate.
 */
const onRowClickCapture = (event: MouseEvent) => {
    if (draggedJustNow()) {
        event.preventDefault();
        event.stopPropagation();
    }
};
</script>

<template>
    <div
        class="group flex min-w-2xl flex-1 items-center gap-4"
        @click.capture="onRowClickCapture"
    >
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

        <div v-if="canPlan" class="flex w-32 shrink-0 justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger
                    :data-test="
                        planned ? 'issue-sprint-chip' : 'issue-status-chip'
                    "
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
                        class="text-muted-foreground max-w-full gap-1"
                    >
                        <Timer class="size-3 shrink-0" />
                        <span class="truncate">{{
                            currentSprint?.name ?? 'Sprint'
                        }}</span>
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
        </div>

        <div class="flex w-28 shrink-0 justify-start">
            <template v-if="showsStatus">
                <DropdownMenu v-if="columns.length > 0">
                    <DropdownMenuTrigger
                        data-test="issue-status-menu"
                        :aria-label="`Change the status of ${issue.reference}`"
                        class="cursor-pointer rounded-md focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        @pointerdown.stop
                    >
                        <IssueStatusBadge
                            :status="issue.status"
                            :label="issue.statusLabel"
                        />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            v-for="column in columns"
                            :key="column.id"
                            data-test="issue-status-option"
                            :disabled="column.id === issue.boardColumnId"
                            @select="emit('moveTo', column.id)"
                        >
                            <span class="truncate">{{ column.name }}</span>
                            <Check
                                v-if="column.id === issue.boardColumnId"
                                class="ml-auto size-3.5"
                            />
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <IssueStatusBadge
                    v-else
                    :status="issue.status"
                    :label="issue.statusLabel"
                />
            </template>
        </div>

        <div class="w-28 shrink-0 text-right">
            <DropdownMenu v-if="members.length > 0">
                <DropdownMenuTrigger
                    :class="[
                        'block w-full cursor-pointer truncate rounded text-right text-xs',
                        assignedToMe
                            ? 'text-foreground font-medium'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :data-test="
                        assignedToMe ? 'issue-assignee-me' : 'issue-assignee'
                    "
                    :aria-label="`Change the assignee for ${issue.reference}`"
                    @pointerdown.stop
                >
                    {{ issue.assigneeName ?? 'Unassigned' }}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuItem
                        v-for="member in members"
                        :key="member.id"
                        data-test="issue-assignee-option"
                        :disabled="member.id === issue.assigneeId"
                        @select="emit('assign', member.id)"
                    >
                        <span class="truncate">{{ member.name }}</span>
                        <Check
                            v-if="member.id === issue.assigneeId"
                            class="ml-auto size-3.5"
                        />
                    </DropdownMenuItem>
                    <template v-if="issue.assigneeId !== null">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            data-test="issue-assignee-clear"
                            @select="emit('assign', null)"
                        >
                            Unassigned
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
            <span
                v-else
                :class="[
                    'block truncate text-xs',
                    assignedToMe
                        ? 'text-foreground font-medium'
                        : 'text-muted-foreground',
                ]"
                :data-test="
                    assignedToMe ? 'issue-assignee-me' : 'issue-assignee'
                "
            >
                {{ issue.assigneeName ?? 'Unassigned' }}
            </span>
            <span class="text-muted-foreground block truncate text-xs">
                <DropdownMenu v-if="teams.length > 0">
                    <DropdownMenuTrigger
                        data-test="issue-team"
                        :aria-label="`Change the team for ${issue.reference}`"
                        class="hover:text-foreground cursor-pointer rounded"
                        @pointerdown.stop
                    >
                        <span v-if="issue.teamName">
                            {{ issue.teamName }}
                        </span>
                        <span
                            v-else
                            class="opacity-0 transition-opacity group-hover:opacity-70"
                        >
                            Set team
                        </span>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            v-for="team in teams"
                            :key="team.id"
                            data-test="issue-team-option"
                            :disabled="team.id === issue.teamId"
                            @select="emit('assignTeam', team.id)"
                        >
                            <Users />
                            <span class="truncate">{{ team.name }}</span>
                            <Check
                                v-if="team.id === issue.teamId"
                                class="ml-auto size-3.5"
                            />
                        </DropdownMenuItem>
                        <template v-if="issue.teamName">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                data-test="issue-team-clear"
                                @select="emit('assignTeam', null)"
                            >
                                No team
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
                <span v-else-if="issue.teamName">{{ issue.teamName }}</span>
            </span>
        </div>
    </div>
</template>
