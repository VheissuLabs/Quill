<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ListTodo,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Timer,
    Trash2,
} from '@lucide/vue';
import { useDebounceFn, useLocalStorage } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import CreateIssueModal from '@/components/CreateIssueModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import IssueRow from '@/components/IssueRow.vue';
import TeamActivityFeed from '@/components/TeamActivityFeed.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateRange } from '@/lib/dates';
import { useRowDrag } from '@/composables/useRowDrag';
import { mutate } from '@/lib/optimistic';
import { index, update } from '@/routes/issues';
import { update as updateOrder } from '@/routes/issues/order';
import {
    destroy as destroySprint,
    show as showSprint,
    update as updateSprint,
} from '@/routes/sprints';
import type {
    IssueActivityEntry,
    IssueFilters,
    IssueFormOptions,
    IssueListItem,
    IssueSprintOption,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = IssueFormOptions & {
    currentOrganization: Organization;
    issues: IssueListItem[];
    filters: IssueFilters;
    activity: IssueActivityEntry[];
    permissions: OrganizationPermissions;
};

type DragChange = {
    moved?: { element: IssueListItem };
};

type DropTarget = { kind: 'sprint'; id: number } | { kind: 'backlog' } | null;

type DragEndEvent = {
    item: HTMLElement;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Issues',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const all = 'all';
const page = usePage();
const { markDragEnded } = useRowDrag();
const currentTeam = computed(() => page.props.currentTeam);

const options = computed<IssueFormOptions>(() => ({
    projects: props.projects,
    teams: props.teams,
    sprints: props.sprints,
    sprintsEnabled: props.sprintsEnabled,
    boardColumns: props.boardColumns,
    members: props.members,
    statuses: props.statuses,
    priorities: props.priorities,
}));

/**
 * The list keeps its own copy so a drag reorders immediately; the server response
 * then replaces it with the persisted order.
 */
const list = ref<IssueListItem[]>([...props.issues]);
const sprintLists = ref<Record<number, IssueListItem[]>>({});
const dragging = ref(false);
const hoveredTarget = ref<DropTarget>(null);

const buildSprintLists = () =>
    Object.fromEntries(
        props.sprints.map((sprint) => [sprint.id, [...sprint.issues]]),
    );

sprintLists.value = buildSprintLists();
const collapsedSprintIds = useLocalStorage<number[]>(
    `quill.${props.currentOrganization.slug}.collapsed-sprints`,
    [],
);
const collapsedSprints = computed(() => new Set(collapsedSprintIds.value));

const toggleSprint = (sprintId: number) => {
    collapsedSprintIds.value = collapsedSprints.value.has(sprintId)
        ? collapsedSprintIds.value.filter((id) => id !== sprintId)
        : [...collapsedSprintIds.value, sprintId];
};

const sprintToDelete = ref<IssueSprintOption | null>(null);
const sprintBeingRenamed = ref<number | null>(null);
const sprintName = ref('');
const renameFocused = ref(false);

const startRenaming = (sprint: IssueSprintOption) => {
    if (!props.permissions.canManageSprints) {
        return;
    }

    sprintName.value = sprint.name;
    renameFocused.value = false;
    sprintBeingRenamed.value = sprint.id;
};

/**
 * Focus the field once it exists. Until it has focus a blur is something else letting go, not
 * the user leaving the field, so it must not close the rename.
 */
const focusRename = (el: unknown) => {
    const input = (el as { $el?: HTMLInputElement } | null)?.$el;

    // Vue re-runs an inline ref on every patch; focus and select belong to the first one only.
    if (!input || renameFocused.value) {
        return;
    }

    void nextTick(() => {
        input.focus();
        input.select();
        renameFocused.value = true;
    });
};

/**
 * The sprint form validates its dates too, so a rename sends them back unchanged.
 */
const saveSprintName = (sprint: IssueSprintOption) => {
    if (!renameFocused.value) {
        return;
    }

    const name = sprintName.value.trim();

    sprintBeingRenamed.value = null;
    renameFocused.value = false;

    if (!name || name === sprint.name) {
        return;
    }

    router.patch(
        updateSprint([props.currentOrganization.slug, sprint.id]),
        { name, starts_at: sprint.startsAt, ends_at: sprint.endsAt },
        {
            // Only the sprints change. Reloading the rest tears the lists down and rebuilds them,
            // which moves the page under the user before the scroll is restored.
            only: ['sprints'],
            preserveScroll: true,
            preserveState: true,
        },
    );
};

watch(
    () => [props.issues, props.sprints],
    () => {
        list.value = [...props.issues];
        sprintLists.value = buildSprintLists();
    },
);

const applyFilters = (
    changes: Partial<Record<keyof IssueFilters, string | null>>,
) => {
    const next = { ...props.filters, ...changes };

    router.visit(
        index(props.currentOrganization.slug, {
            query: Object.fromEntries(
                Object.entries(next).map(([key, value]) => [
                    key,
                    value && value !== all ? value : undefined,
                ]),
            ),
        }),
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const filterModel = (key: keyof IssueFilters) =>
    computed({
        get: () => props.filters[key] ?? all,
        set: (value: string) => applyFilters({ [key]: value }),
    });

const statusFilter = filterModel('status');
const projectFilter = filterModel('project');

const assignedToMe = computed(() => props.filters.assignee === 'me');

const search = ref(props.filters.search ?? '');

const runSearch = useDebounceFn(
    () => applyFilters({ search: search.value.trim() || null }),
    250,
);

watch(
    () => props.filters.search,
    (value) => {
        search.value = value ?? '';
    },
);

/**
 * A row sits either in the backlog list or inside a sprint card, so a change is applied
 * wherever the issue is found.
 */
const withIssue = (
    current: Record<string, unknown>,
    issueId: number,
    changes: Partial<IssueListItem>,
) => {
    const apply = (issues: IssueListItem[]) =>
        issues.map((issue) =>
            issue.id === issueId ? { ...issue, ...changes } : issue,
        );

    return {
        issues: apply(current.issues as IssueListItem[]),
        sprints: (current.sprints as IssueSprintOption[]).map((sprint) => ({
            ...sprint,
            issues: apply(sprint.issues),
        })),
    };
};

const assignTeam = (issueId: number, teamId: number | null) => {
    mutate(update([props.currentOrganization.slug, issueId]), {
        data: { team_id: teamId },
        errorMessage: 'That team could not be changed.',
        optimistic: (current) =>
            withIssue(current, issueId, {
                teamId,
                teamName:
                    props.teams.find((team) => team.id === teamId)?.name ??
                    null,
            }),
    });
};

const assignTo = (issueId: number, userId: number | null) => {
    mutate(update([props.currentOrganization.slug, issueId]), {
        data: { assignee_id: userId },
        errorMessage: 'That assignee could not be changed.',
        optimistic: (current) =>
            withIssue(current, issueId, {
                assigneeId: userId,
                assigneeName:
                    props.members.find((member) => member.id === userId)
                        ?.name ?? null,
            }),
    });
};

/**
 * Stages are the columns of the board the issue belongs to; without a team the
 * organization's own board applies.
 */
const columnsFor = (issue: IssueListItem) =>
    issue.teamId === null
        ? props.boardColumns
        : (props.teams.find((team) => team.id === issue.teamId)?.columns ?? []);

const moveTo = (issueId: number, columnId: number) => {
    const column = [
        ...props.boardColumns,
        ...props.teams.flatMap((team) => team.columns),
    ].find((candidate) => candidate.id === columnId);

    mutate(update([props.currentOrganization.slug, issueId]), {
        data: { board_column_id: columnId },
        errorMessage: 'That status could not be changed.',
        optimistic: (current) =>
            column
                ? withIssue(current, issueId, {
                      boardColumnId: columnId,
                      status: column.status,
                      statusLabel: column.name,
                  })
                : undefined,
    });
};

const plannableSprints = computed(() =>
    props.sprints.filter((sprint) => sprint.status !== 'completed'),
);

/**
 * Moves the row between the backlog and a sprint in the page props first; Inertia puts it
 * back if the request is refused.
 */
const planInto = (issueId: number, sprintId: number | null) => {
    mutate(update([props.currentOrganization.slug, issueId]), {
        data: { sprint_id: sprintId },
        errorMessage: 'That issue could not be moved.',
        optimistic: (current) => {
            const issues = [...(current.issues as IssueListItem[])];
            const sprints = (current.sprints as IssueSprintOption[]).map(
                (sprint) => ({
                    ...sprint,
                    issues: [...sprint.issues],
                }),
            );

            let moving = issues.find((issue) => issue.id === issueId) ?? null;
            const from = sprints.find((sprint) =>
                sprint.issues.some((issue) => issue.id === issueId),
            );

            if (moving) {
                issues.splice(
                    issues.findIndex((issue) => issue.id === issueId),
                    1,
                );
            } else if (from) {
                moving =
                    from.issues.find((issue) => issue.id === issueId) ?? null;
                from.issues.splice(
                    from.issues.findIndex((issue) => issue.id === issueId),
                    1,
                );
            }

            if (!moving) {
                return;
            }

            const to = sprints.find((sprint) => sprint.id === sprintId);

            if (to) {
                to.issues.push(moving);
            } else {
                issues.unshift(moving);
            }

            return { issues, sprints };
        },
    });
};

const onListChange = (change: DragChange) => {
    if (!change.moved) {
        return;
    }

    // Sortable has already moved the row in the local list, so there is nothing to apply
    // ahead of the response; an optimistic prop would only give Inertia an older order to
    // roll back to.
    mutate(updateOrder(props.currentOrganization.slug), {
        data: { issue_ids: list.value.map((issue) => issue.id) },
        errorMessage: 'That order could not be saved.',
    });
};

/**
 * While a row is being dragged, remember what the pointer is over: a sprint card or the backlog
 * list. Sortable's drag ghost sits under the pointer at release, so the target is read from
 * pointer moves rather than at the drop itself.
 */
const trackPointer = (event: PointerEvent) => {
    const element = document.elementFromPoint(event.clientX, event.clientY);
    const card = element?.closest<HTMLElement>('[data-sprint-id]');

    if (card) {
        hoveredTarget.value = {
            kind: 'sprint',
            id: Number(card.dataset.sprintId),
        };
    } else if (element?.closest('[data-backlog-list]')) {
        hoveredTarget.value = { kind: 'backlog' };
    } else {
        hoveredTarget.value = null;
    }
};

const onDragStart = () => {
    dragging.value = true;
    hoveredTarget.value = null;
    document.addEventListener('pointermove', trackPointer);
};

/**
 * Dropping a row on a sprint card plans it there; dropping a planned row on the backlog list
 * unplans it. Sortable puts the row back where it came from, and the server response then shows
 * it in its new place.
 */
const onDragEnd = (event: DragEndEvent, currentSprintId: number | null) => {
    document.removeEventListener('pointermove', trackPointer);
    dragging.value = false;
    markDragEnded();

    const issueId = Number(event.item.dataset.issueId);
    const target = hoveredTarget.value;

    hoveredTarget.value = null;

    if (!issueId || !target) {
        return;
    }

    if (target.kind === 'sprint' && target.id !== currentSprintId) {
        planInto(issueId, target.id);
    } else if (target.kind === 'backlog' && currentSprintId !== null) {
        planInto(issueId, null);
    }
};

const isHovered = (target: DropTarget) =>
    JSON.stringify(hoveredTarget.value) === JSON.stringify(target);

const totalPoints = computed(() =>
    list.value.reduce((total, issue) => total + (issue.points ?? 0), 0),
);
</script>

<template>
    <Head title="Issues" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Issues"
                :description="
                    currentTeam
                        ? sprintsEnabled
                            ? `${currentTeam.name}'s backlog, with its sprints below. Drag an issue onto a sprint to plan it, or back into the list to unplan it.`
                            : `${currentTeam.name}'s backlog.`
                        : 'Every open issue. Create a team to start planning sprints.'
                "
            />

            <CreateIssueModal
                :organization="currentOrganization.slug"
                :options="options"
            >
                <Button data-test="issues-new-issue-button">
                    <Plus />
                    New issue
                </Button>
            </CreateIssueModal>
        </div>

        <div class="grid gap-8">
            <div class="grid min-w-0 gap-8">
                <section class="min-w-0 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Select v-model="statusFilter">
                            <SelectTrigger
                                class="w-40"
                                data-test="issue-status-filter"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="all"
                                    >Open issues</SelectItem
                                >
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="projectFilter">
                            <SelectTrigger class="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="all"
                                    >All projects</SelectItem
                                >
                                <SelectItem
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.slug"
                                >
                                    {{ project.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                            />
                            <Input
                                v-model="search"
                                type="search"
                                placeholder="Search issues"
                                class="h-9 w-56 pl-8"
                                data-test="issue-search"
                                @input="runSearch"
                            />
                        </div>

                        <Button
                            :variant="assignedToMe ? 'secondary' : 'ghost'"
                            size="sm"
                            data-test="issue-assignee-filter"
                            @click="
                                applyFilters({
                                    assignee: assignedToMe ? null : 'me',
                                })
                            "
                        >
                            Assigned to me
                        </Button>

                        <span
                            class="text-muted-foreground ml-auto text-sm tabular-nums"
                        >
                            {{ list.length }}
                            {{ list.length === 1 ? 'issue' : 'issues' }}
                            <template v-if="totalPoints > 0"
                                >· {{ totalPoints }} pts</template
                            >
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <draggable
                            :list="list"
                            group="issues"
                            item-key="id"
                            :force-fallback="true"
                            draggable="[data-issue-id]"
                            data-backlog-list
                            :class="[
                                'min-w-max divide-y rounded-xl border transition-colors',
                                isHovered({ kind: 'backlog' }) &&
                                    'border-foreground ring-foreground/20 ring-2',
                            ]"
                            ghost-class="opacity-40"
                            :animation="150"
                            @start="onDragStart"
                            @end="onDragEnd($event, null)"
                            @change="onListChange"
                        >
                            <template #item="{ element: issue }">
                                <div
                                    data-test="issue-row"
                                    :data-issue-id="issue.id"
                                    class="bg-card hover:bg-accent/50 flex cursor-grab items-center px-4 py-3 transition-colors first:rounded-t-xl last:rounded-b-xl active:cursor-grabbing"
                                >
                                    <IssueRow
                                        :organization="currentOrganization.slug"
                                        :issue="issue"
                                        :sprints="plannableSprints"
                                        :teams="teams"
                                        :members="members"
                                        :columns="columnsFor(issue)"
                                        :current-sprint-id="null"
                                        @plan="planInto(issue.id, $event)"
                                        @assign-team="
                                            assignTeam(issue.id, $event)
                                        "
                                        @assign="assignTo(issue.id, $event)"
                                        @move-to="moveTo(issue.id, $event)"
                                    />
                                </div>
                            </template>
                            <template #footer>
                                <EmptyState
                                    v-if="list.length === 0"
                                    class="border-0"
                                    :icon="ListTodo"
                                    title="No issues here"
                                    description="Nothing matches these filters. Create an issue, or convert a feature request from one of your boards."
                                />
                            </template>
                        </draggable>
                    </div>
                </section>

                <aside v-if="sprintsEnabled" class="min-w-0 space-y-3">
                    <h2
                        class="text-muted-foreground px-1 text-xs font-medium tracking-wide uppercase"
                    >
                        Sprints
                    </h2>

                    <div
                        v-for="sprint in sprints"
                        :key="sprint.id"
                        :data-sprint-id="sprint.id"
                        :data-test="`sprint-drop-${sprint.id}`"
                        :class="[
                            'bg-card rounded-xl border transition-colors',
                            dragging && 'border-foreground/40 border-dashed',
                            isHovered({ kind: 'sprint', id: sprint.id }) &&
                                'border-foreground ring-foreground/20 ring-2',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2 p-4">
                            <div class="min-w-0">
                                <Input
                                    v-if="sprintBeingRenamed === sprint.id"
                                    :ref="(el) => focusRename(el)"
                                    v-model="sprintName"
                                    class="h-7 text-sm font-medium"
                                    :data-test="`sprint-rename-${sprint.id}`"
                                    @keydown.enter.prevent="
                                        saveSprintName(sprint)
                                    "
                                    @keydown.esc.prevent="
                                        sprintBeingRenamed = null
                                    "
                                    @blur="saveSprintName(sprint)"
                                />
                                <Link
                                    v-else
                                    :href="
                                        showSprint([
                                            currentOrganization.slug,
                                            sprint.id,
                                        ])
                                    "
                                    class="block truncate text-sm font-medium hover:underline"
                                >
                                    {{ sprint.name }}
                                </Link>
                                <p class="text-muted-foreground mt-0.5 text-xs">
                                    {{ sprint.teamName }} ·
                                    {{
                                        formatDateRange(
                                            sprint.startsAt,
                                            sprint.endsAt,
                                        )
                                    }}
                                </p>
                                <div class="mt-2 flex items-center gap-1.5">
                                    <Badge
                                        variant="secondary"
                                        class="tabular-nums"
                                    >
                                        {{ sprint.issuesCount }}
                                        {{
                                            sprint.issuesCount === 1
                                                ? 'issue'
                                                : 'issues'
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        class="tabular-nums"
                                    >
                                        {{ sprint.points }} pts
                                    </Badge>
                                </div>
                            </div>
                            <span class="flex shrink-0 items-center gap-1">
                                <Badge
                                    :variant="
                                        sprint.status === 'active'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ sprint.statusLabel }}
                                </Badge>
                                <DropdownMenu
                                    v-if="permissions.canManageSprints"
                                >
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            :data-test="`sprint-menu-${sprint.id}`"
                                            aria-label="Sprint actions"
                                        >
                                            <MoreHorizontal />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        @close-auto-focus="
                                            (event) => event.preventDefault()
                                        "
                                    >
                                        <DropdownMenuItem
                                            class="cursor-pointer"
                                            @click="startRenaming(sprint)"
                                        >
                                            <Pencil />
                                            Rename
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="cursor-pointer"
                                            variant="destructive"
                                            @click="sprintToDelete = sprint"
                                        >
                                            <Trash2 />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    data-test="sprint-toggle"
                                    :aria-expanded="
                                        !collapsedSprints.has(sprint.id)
                                    "
                                    :aria-label="
                                        collapsedSprints.has(sprint.id)
                                            ? 'Expand sprint'
                                            : 'Collapse sprint'
                                    "
                                    @click="toggleSprint(sprint.id)"
                                >
                                    <ChevronDown
                                        class="transition-transform"
                                        :class="
                                            collapsedSprints.has(sprint.id) &&
                                            '-rotate-90'
                                        "
                                    />
                                </Button>
                            </span>
                        </div>

                        <div
                            v-if="
                                !collapsedSprints.has(sprint.id) &&
                                sprint.issues.length > 0
                            "
                            class="overflow-x-auto"
                        >
                            <draggable
                                :list="sprintLists[sprint.id]"
                                :group="`sprint-${sprint.id}`"
                                :sort="false"
                                item-key="id"
                                :force-fallback="true"
                                tag="ul"
                                class="min-w-max divide-y border-t"
                                ghost-class="opacity-40"
                                @start="onDragStart"
                                @end="onDragEnd($event, sprint.id)"
                            >
                                <template #item="{ element: issue }">
                                    <li
                                        :data-issue-id="issue.id"
                                        class="hover:bg-accent/50 flex cursor-grab items-center px-4 py-2.5 transition-colors active:cursor-grabbing"
                                    >
                                        <IssueRow
                                            :organization="
                                                currentOrganization.slug
                                            "
                                            :issue="issue"
                                            :sprints="plannableSprints"
                                            :teams="teams"
                                            :members="members"
                                            :columns="columnsFor(issue)"
                                            :current-sprint-id="sprint.id"
                                            @plan="planInto(issue.id, $event)"
                                            @assign-team="
                                                assignTeam(issue.id, $event)
                                            "
                                            @assign="assignTo(issue.id, $event)"
                                            @move-to="moveTo(issue.id, $event)"
                                        />
                                    </li>
                                </template>
                            </draggable>
                        </div>
                        <p
                            v-else-if="!collapsedSprints.has(sprint.id)"
                            class="text-muted-foreground border-t px-4 py-3 text-xs"
                        >
                            Nothing planned yet. Drag an issue onto this card,
                            or drag a planned issue back into the list to unplan
                            it.
                        </p>
                    </div>

                    <div
                        v-if="sprints.length === 0"
                        class="text-muted-foreground rounded-xl border border-dashed p-4 text-center text-xs"
                    >
                        <Timer class="mx-auto mb-2 size-4" />
                        <template v-if="currentTeam">
                            No open sprints for {{ currentTeam.name }}. Create
                            one on the Sprints page to start planning.
                        </template>
                        <template v-else>
                            Sprints belong to teams. Create a team to start
                            planning.
                        </template>
                    </div>
                </aside>
            </div>

            <TeamActivityFeed
                v-if="permissions.canViewActivity"
                :entries="activity"
                :organization="currentOrganization.slug"
            />
        </div>
    </div>

    <ConfirmDeleteModal
        :open="sprintToDelete !== null"
        title="Delete sprint"
        confirm-label="Delete sprint"
        :href="
            sprintToDelete
                ? destroySprint([currentOrganization.slug, sprintToDelete.id])
                : null
        "
        @update:open="sprintToDelete = null"
    >
        This will delete
        <strong>{{ sprintToDelete?.name }}</strong>
        . Its issues return to the backlog.
    </ConfirmDeleteModal>
</template>
