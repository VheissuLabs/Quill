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
    organization: { name: string; slug: string };
    team: { name: string; slug: string } | null;
    otherTeams: TeamMergeTarget[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const ORGANIZATION = 'organization';

const destination = ref(ORGANIZATION);
const processing = ref(false);

/**
 * The organization takes the work back by default. Nothing is thrown away either way.
 */
watch(
    () => props.open,
    (open) => {
        if (open) {
            destination.value = ORGANIZATION;
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

    router.delete(destroy([props.organization.slug, props.team.slug]), {
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
                    Its sprints and issues move to
                    <strong>{{ movingTo?.name ?? organization.name }}</strong
                    >, keeping their status and landing in the matching column
                    on that board.
                    <template v-if="!movingTo">
                        Members stay in the organization, and boards restricted
                        to this team become visible to every organization
                        member.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
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
                        <SelectItem :value="ORGANIZATION">
                            {{ organization.name }} (no team)
                        </SelectItem>
                        <SelectItem
                            v-for="option in otherTeams"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
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
                    Move and delete team
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
