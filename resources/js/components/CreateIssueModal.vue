<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/issues';
import type { IssueFormOptions } from '@/types';
import { pointOptions } from '@/lib/points';

type Props = {
    organization: string;
    options: IssueFormOptions;
    defaultProjectId?: number | null;
    defaultSprintId?: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    defaultProjectId: null,
    defaultSprintId: null,
});

const none = 'none';

const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const form = useForm({
    project_id: props.defaultProjectId ? String(props.defaultProjectId) : '',
    title: '',
    description: '',
    priority: 'none',
    points: none,
    sprint_id: props.defaultSprintId ? String(props.defaultSprintId) : none,
    assignee_id: none,
});

const currentTeamId = computed(() => page.props.currentTeam?.id ?? null);

const sprintsForTeam = computed(() =>
    currentTeamId.value === null
        ? props.options.sprints
        : props.options.sprints.filter(
              (sprint) => sprint.teamId === currentTeamId.value,
          ),
);

const handleOpenChange = (value: boolean) => {
    open.value = value;

    if (!value) {
        form.reset();
        form.clearErrors();
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        project_id: Number(data.project_id),
        points: data.points === none ? null : Number(data.points),
        team_id: currentTeamId.value,
        sprint_id: data.sprint_id === none ? null : Number(data.sprint_id),
        assignee_id:
            data.assignee_id === none ? null : Number(data.assignee_id),
    })).submit(store(props.organization), {
        onSuccess: () => handleOpenChange(false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogTrigger v-if="$slots.default" as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>New issue</DialogTitle>
                    <DialogDescription>
                        Issues start in the backlog unless you plan them into a
                        sprint.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="issue-project">Project</Label>
                        <Select
                            v-model="form.project_id"
                            data-test="create-issue-project"
                        >
                            <SelectTrigger id="issue-project" class="w-full">
                                <SelectValue placeholder="Select a project" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="project in options.projects"
                                    :key="project.id"
                                    :value="String(project.id)"
                                >
                                    {{ project.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.project_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="issue-title">Title</Label>
                        <Input
                            id="issue-title"
                            v-model="form.title"
                            data-test="create-issue-title"
                            placeholder="What needs to be done?"
                            required
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="issue-description">Description</Label>
                        <Textarea
                            id="issue-description"
                            v-model="form.description"
                            data-test="create-issue-description"
                            placeholder="Context, acceptance criteria, links"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="issue-priority">Priority</Label>
                            <Select v-model="form.priority">
                                <SelectTrigger
                                    id="issue-priority"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="priority in options.priorities"
                                        :key="priority.value"
                                        :value="priority.value"
                                    >
                                        {{ priority.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.priority" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="issue-points">Points</Label>
                            <Select v-model="form.points">
                                <SelectTrigger
                                    id="issue-points"
                                    class="w-full"
                                    data-test="create-issue-points"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="none">
                                        No estimate
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in pointOptions(null)"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.points" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="issue-assignee">Assignee</Label>
                            <Select v-model="form.assignee_id">
                                <SelectTrigger
                                    id="issue-assignee"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="none">
                                        Unassigned
                                    </SelectItem>
                                    <SelectItem
                                        v-for="member in options.members"
                                        :key="member.id"
                                        :value="String(member.id)"
                                    >
                                        {{ member.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.assignee_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="issue-sprint">Sprint</Label>
                            <Select v-model="form.sprint_id">
                                <SelectTrigger id="issue-sprint" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="none">
                                        Backlog
                                    </SelectItem>
                                    <SelectItem
                                        v-for="sprint in sprintsForTeam"
                                        :key="sprint.id"
                                        :value="String(sprint.id)"
                                    >
                                        {{ sprint.name }}
                                        <span class="text-muted-foreground">
                                            · {{ sprint.teamName }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.sprint_id" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        data-test="create-issue-submit"
                        :disabled="form.processing || form.project_id === ''"
                    >
                        Create issue
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
