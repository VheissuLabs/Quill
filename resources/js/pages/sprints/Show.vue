<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck, Play, Settings2, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import IssueCard from '@/components/IssueCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { mutate } from '@/lib/optimistic';
import { index as issuesIndex } from '@/routes/issues';
import { complete, destroy, index, show, start } from '@/routes/sprints';
import { update as updateColumn } from '@/routes/sprints/columns';
import { edit as editTeam } from '@/routes/teams';
import { useLiveIssues } from '@/composables/useLiveIssues';
import type {
    BoardColumn,
    Organization,
    OrganizationPermissions,
    SprintIssue,
    SprintListItem,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    sprint: SprintListItem;
    issues: SprintIssue[];
    columns: BoardColumn[];
    permissions: OrganizationPermissions;
};

type BoardLane = BoardColumn & {
    issues: SprintIssue[];
};

type DragChange = {
    added?: { element: SprintIssue };
    moved?: { element: SprintIssue };
};

const props = defineProps<Props>();

useLiveIssues(['sprint', 'issues', 'columns']);

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        sprint: SprintListItem;
    }) => ({
        breadcrumbs: [
            {
                title: 'Sprints',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.sprint.name,
                href: show([props.currentOrganization.slug, props.sprint.id]),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);
const completeDialogOpen = ref(false);

const buildLanes = (): BoardLane[] =>
    props.columns.map((column) => ({
        ...column,
        issues: props.issues.filter(
            (issue) => issue.boardColumnId === column.id,
        ),
    }));

/**
 * The board keeps its own copies of the cards so a drag moves immediately; the server
 * response then replaces them with the persisted order.
 */
const lanes = ref<BoardLane[]>(buildLanes());

watch(
    () => [props.issues, props.columns],
    () => (lanes.value = buildLanes()),
);

const lanePoints = (lane: BoardLane) =>
    lane.issues.reduce((total, issue) => total + (issue.points ?? 0), 0);

const onLaneChange = (lane: BoardLane, change: DragChange) => {
    if (!change.added && !change.moved) {
        return;
    }

    mutate(
        updateColumn([
            props.currentOrganization.slug,
            props.sprint.id,
            lane.id,
        ]),
        {
            data: { issue_ids: lane.issues.map((issue) => issue.id) },
            errorMessage: 'That board change could not be saved.',
        },
    );
};

const startSprint = () => {
    router.visit(start([props.currentOrganization.slug, props.sprint.id]), {
        preserveScroll: true,
    });
};

const unfinishedCount = computed(
    () => props.issues.filter((issue) => issue.status !== 'done').length,
);
</script>

<template>
    <Head :title="sprint.name" />

    <div class="flex h-[calc(100svh-4rem)] flex-col gap-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ sprint.name }}
                    </h1>
                    <Badge
                        :variant="
                            sprint.status === 'active' ? 'default' : 'secondary'
                        "
                    >
                        {{ sprint.statusLabel }}
                    </Badge>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{ sprint.owner.name }} ·
                    {{ formatDate(sprint.startsAt) }} to
                    {{ formatDate(sprint.endsAt) }} ·
                    <span class="tabular-nums">
                        {{ sprint.doneIssuesCount }} of
                        {{ sprint.issuesCount }} done ·
                        {{ sprint.points }} points
                    </span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="permissions.canManageTeams && sprint.owner.isTeam"
                    variant="ghost"
                    size="icon"
                    aria-label="Edit board columns"
                    as-child
                >
                    <Link
                        :href="
                            editTeam([
                                currentOrganization.slug,
                                sprint.owner.slug,
                            ])
                        "
                    >
                        <Settings2 />
                    </Link>
                </Button>
                <template v-if="permissions.canManageSprints">
                    <Button
                        v-if="sprint.status === 'planned'"
                        data-test="start-sprint-button"
                        @click="startSprint"
                    >
                        <Play /> Start sprint
                    </Button>
                    <Button
                        v-else-if="sprint.status === 'active'"
                        data-test="complete-sprint-button"
                        @click="completeDialogOpen = true"
                    >
                        <CheckCheck /> Complete sprint
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test="delete-sprint-button"
                        aria-label="Delete sprint"
                        @click="deleteDialogOpen = true"
                    >
                        <Trash2 />
                    </Button>
                </template>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 gap-4 overflow-x-auto pb-2">
            <section
                v-for="lane in lanes"
                :key="lane.id"
                :data-test="`column-${lane.id}`"
                class="bg-muted/40 flex w-72 shrink-0 flex-col gap-3 rounded-xl p-3"
            >
                <h2
                    class="flex items-center justify-between px-1 text-sm font-medium"
                >
                    {{ lane.name }}
                    <span class="text-muted-foreground text-xs tabular-nums">
                        {{ lane.issues.length }}
                        <template v-if="lanePoints(lane) > 0">
                            · {{ lanePoints(lane) }} pts
                        </template>
                    </span>
                </h2>

                <draggable
                    :list="lane.issues"
                    group="board"
                    item-key="id"
                    class="flex min-h-24 flex-1 flex-col gap-3 overflow-y-auto"
                    ghost-class="opacity-40"
                    :animation="150"
                    :force-fallback="true"
                    @change="onLaneChange(lane, $event)"
                >
                    <template #item="{ element: issue }">
                        <IssueCard
                            :organization="currentOrganization.slug"
                            :issue="issue"
                        />
                    </template>
                </draggable>
            </section>
        </div>

        <p
            v-if="issues.length === 0"
            class="text-muted-foreground -mt-2 text-sm"
        >
            Nothing planned yet. Drag issues onto this sprint from the
            <Link
                :href="issuesIndex(currentOrganization.slug)"
                class="text-foreground underline underline-offset-4"
            >
                issues list</Link
            >.
        </p>
    </div>

    <ConfirmDeleteModal
        v-model:open="completeDialogOpen"
        title="Complete sprint"
        confirm-label="Complete sprint"
        :href="complete([currentOrganization.slug, sprint.id])"
    >
        <template v-if="unfinishedCount > 0">
            {{ unfinishedCount }}
            {{ unfinishedCount === 1 ? 'issue is' : 'issues are' }} not done and
            will go back to the backlog.
        </template>
        <template v-else>Every issue is done. Nice.</template>
    </ConfirmDeleteModal>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete sprint"
        confirm-label="Delete sprint"
        :href="destroy([currentOrganization.slug, sprint.id])"
    >
        This deletes <strong>{{ sprint.name }}</strong
        >. Its issues stay and return to the backlog.
    </ConfirmDeleteModal>
</template>
