<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { GripVertical, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store, update } from '@/routes/teams/columns';
import { update as updateOrder } from '@/routes/teams/columns/order';
import type { SelectOption, Team, TeamBoardColumn } from '@/types';

type Props = {
    organization: string;
    team: Team;
    columns: TeamBoardColumn[];
    statuses: SelectOption[];
    canManage: boolean;
};

type EditableColumn = TeamBoardColumn & {
    draftName: string;
    draftStatus: string;
};

const props = defineProps<Props>();

const toEditable = (): EditableColumn[] =>
    props.columns.map((column) => ({
        ...column,
        draftName: column.name,
        draftStatus: column.status,
    }));

const rows = ref<EditableColumn[]>(toEditable());
const columnToDelete = ref<TeamBoardColumn | null>(null);
const newStatus = ref('in_progress');
const addFormKey = ref(0);

watch(
    () => props.columns,
    () => (rows.value = toEditable()),
);

const isDirty = (row: EditableColumn) =>
    row.draftName.trim() !== row.name || row.draftStatus !== row.status;

const save = (row: EditableColumn) => {
    router.visit(update([props.organization, props.team.slug, row.id]), {
        data: { name: row.draftName.trim(), status: row.draftStatus },
        preserveScroll: true,
    });
};

const onReorder = () => {
    router.visit(updateOrder([props.organization, props.team.slug]), {
        data: { column_ids: rows.value.map((row) => row.id) },
        preserveScroll: true,
        preserveState: true,
    });
};

const statusLabel = (value: string) =>
    props.statuses.find((status) => status.value === value)?.label ?? value;
</script>

<template>
    <div class="space-y-4">
        <draggable
            :list="rows"
            item-key="id"
            handle="[data-handle]"
            :disabled="!canManage"
            :animation="150"
            class="divide-y rounded-lg border"
            @change="onReorder"
        >
            <template #item="{ element: row }">
                <div
                    data-test="board-column-row"
                    class="flex flex-wrap items-center gap-2 p-3"
                >
                    <button
                        v-if="canManage"
                        type="button"
                        data-handle
                        class="text-muted-foreground hover:text-foreground cursor-grab active:cursor-grabbing"
                        aria-label="Drag to reorder"
                    >
                        <GripVertical class="size-4" />
                    </button>

                    <template v-if="canManage">
                        <Input
                            v-model="row.draftName"
                            class="min-w-32 flex-1"
                            data-test="board-column-name"
                            @keydown.enter.prevent="save(row)"
                        />
                        <Select v-model="row.draftStatus">
                            <SelectTrigger
                                class="w-36"
                                data-test="board-column-status"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            v-if="isDirty(row)"
                            data-test="board-column-save"
                            @click="save(row)"
                        >
                            Save
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            data-test="board-column-delete"
                            aria-label="Remove column"
                            :disabled="rows.length === 1"
                            @click="columnToDelete = row"
                        >
                            <Trash2 />
                        </Button>
                    </template>
                    <template v-else>
                        <span class="flex-1 text-sm font-medium">{{
                            row.name
                        }}</span>
                        <span class="text-muted-foreground text-xs">
                            {{ statusLabel(row.status) }}
                        </span>
                    </template>

                    <span
                        class="text-muted-foreground w-full text-xs tabular-nums sm:w-auto sm:text-right"
                    >
                        {{ row.issuesCount }}
                        {{ row.issuesCount === 1 ? 'issue' : 'issues' }}
                    </span>
                </div>
            </template>
        </draggable>

        <Form
            v-if="canManage"
            :key="addFormKey"
            v-bind="store.form([organization, team.slug])"
            class="flex flex-wrap items-end gap-2"
            v-slot="{ errors, processing }"
            @success="addFormKey++"
        >
            <div class="grid min-w-40 flex-1 gap-1.5">
                <Label for="new-column-name" class="text-xs">New column</Label>
                <Input
                    id="new-column-name"
                    name="name"
                    data-test="new-board-column-name"
                    placeholder="In review"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="new-column-status" class="text-xs">Counts as</Label>
                <Select v-model="newStatus" name="status">
                    <SelectTrigger id="new-column-status" class="w-36">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <Button
                type="submit"
                variant="outline"
                data-test="new-board-column-submit"
                :disabled="processing"
            >
                <Plus /> Add
            </Button>
        </Form>

        <ConfirmDeleteModal
            :open="columnToDelete !== null"
            title="Remove column"
            confirm-label="Remove column"
            :href="
                columnToDelete
                    ? destroy([organization, team.slug, columnToDelete.id])
                    : null
            "
            @update:open="columnToDelete = null"
        >
            Issues in <strong>{{ columnToDelete?.name }}</strong> move to the
            column before it.
        </ConfirmDeleteModal>
    </div>
</template>
