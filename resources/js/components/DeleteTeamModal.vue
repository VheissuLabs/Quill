<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy } from '@/routes/teams';
import type { TeamMergeTarget } from '@/types';

const props = defineProps<{
    open: boolean;
    organization: string;
    team: { name: string; slug: string } | null;
    otherTeams: TeamMergeTarget[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const DELETE_ONLY = 'none';

const destination = ref(DELETE_ONLY);
const processing = ref(false);

/**
 * Default to keeping the work: the first other team, falling back to a plain delete
 * when this is the only team in the organization.
 */
watch(
    () => props.open,
    (open) => {
        if (open) {
            destination.value = props.otherTeams[0]
                ? String(props.otherTeams[0].id)
                : DELETE_ONLY;
        }
    },
);

const movingTo = computed(() =>
    props.otherTeams.find((team) => String(team.id) === destination.value),
);

const confirm = () => {
    if (!props.team) {
        return;
    }

    router.delete(destroy([props.organization, props.team.slug]), {
        data: movingTo.value ? { target_team_id: movingTo.value.id } : {},
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete {{ team?.name }}</DialogTitle>
                <DialogDescription>
                    <template v-if="movingTo">
                        Its members, projects, sprints and issues move to
                        <strong>{{ movingTo.name }}</strong
                        >. Issues keep their status and land in the matching
                        column on that board.
                    </template>
                    <template v-else>
                        Its sprints and board columns are deleted and its issues
                        return to the backlog. Members stay in the organization,
                        and boards restricted to this team become visible to
                        every organization member.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div v-if="otherTeams.length > 0" class="grid gap-2">
                <Label for="delete-team-destination">Move its work to</Label>
                <Select v-model="destination">
                    <SelectTrigger
                        id="delete-team-destination"
                        class="w-full"
                        data-test="delete-team-destination"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in otherTeams"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                        <SelectItem :value="DELETE_ONLY">
                            Nowhere — delete the work too
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>

                <Button
                    data-test="confirm-delete-team"
                    variant="destructive"
                    :disabled="processing"
                    @click="confirm"
                >
                    {{ movingTo ? 'Move and delete team' : 'Delete team' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
