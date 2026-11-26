<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { show as showBoard } from '@/routes/portal/projects';
import { destroy, edit, index, update } from '@/routes/projects';
import type {
    ClientOption,
    Organization,
    ProjectBilling,
    ProjectSettings,
    SelectOption,
    TeamOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    project: ProjectSettings;
    clients: ClientOption[];
    teams: TeamOption[];
    visibilities: SelectOption[];
    billing: ProjectBilling | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        project: ProjectSettings;
    }) => ({
        breadcrumbs: [
            {
                title: 'Projects',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.project.name,
                href: edit([
                    props.currentOrganization.slug,
                    props.project.slug,
                ]),
            },
        ],
    }),
});

const noClient = 'none';

const form = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    visibility: props.project.visibility,
    client_id: props.project.clientId
        ? String(props.project.clientId)
        : noClient,
    team_ids: props.project.teamIds,
    billing_rate: props.billing?.rate ?? '',
});

const deleteDialogOpen = ref(false);

const boardUrl = showBoard([
    props.currentOrganization.slug,
    props.project.slug,
]);

const toggleTeam = (teamId: number, checked: boolean) => {
    form.team_ids = checked
        ? [...form.team_ids, teamId]
        : form.team_ids.filter((id) => id !== teamId);
};

const submit = () => {
    form.transform(({ billing_rate, ...data }) => ({
        ...data,
        ...(props.billing ? { billing_rate } : {}),
        client_id: data.client_id === noClient ? null : Number(data.client_id),
    })).submit(update([props.currentOrganization.slug, props.project.slug]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${project.name}`" />

    <div class="grid max-w-7xl items-start gap-6 p-6 lg:grid-cols-2">
        <form @submit.prevent="submit">
            <SettingsCard
                title="Project settings"
                description="Name, client, and who can see the board"
            >
                <div class="grid gap-2">
                    <Label for="name">Project name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        data-test="project-name-input"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <RichTextEditor
                        v-model="form.description"
                        data-test="project-description-input"
                        placeholder="What is this project about?"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="client">Client</Label>
                    <Select
                        v-model="form.client_id"
                        data-test="project-client-select"
                    >
                        <SelectTrigger id="client" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="noClient">No client</SelectItem>
                            <SelectItem
                                v-for="client in clients"
                                :key="client.id"
                                :value="String(client.id)"
                            >
                                {{ client.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="visibility">Board visibility</Label>
                    <Select
                        v-model="form.visibility"
                        data-test="project-visibility-select"
                    >
                        <SelectTrigger id="visibility" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in visibilities"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-muted-foreground text-sm">
                        {{
                            form.visibility === 'public'
                                ? 'Anyone can view the board. Signed in users can submit feedback and vote.'
                                : 'Only members of this organization can view the board.'
                        }}
                    </p>
                    <InputError :message="form.errors.visibility" />
                </div>

                <div v-if="billing" class="grid gap-2">
                    <Label for="billing-rate">Hourly rate</Label>
                    <Input
                        id="billing-rate"
                        v-model="form.billing_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        data-test="project-billing-rate-input"
                    />
                    <InputError :message="form.errors.billing_rate" />
                </div>

                <div v-if="form.visibility === 'private'" class="grid gap-3">
                    <div>
                        <Label>Restrict to teams</Label>
                        <p class="text-muted-foreground text-sm">
                            Leave every team unchecked to let all organization
                            members see the board. Admins always can.
                        </p>
                    </div>
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`team-${team.id}`"
                            data-test="project-team-checkbox"
                            :model-value="form.team_ids.includes(team.id)"
                            @update:model-value="
                                toggleTeam(team.id, $event === true)
                            "
                        />
                        <Label :for="`team-${team.id}`" class="font-normal">
                            {{ team.name }}
                        </Label>
                    </div>
                    <p
                        v-if="teams.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        This organization has no teams yet.
                    </p>
                    <InputError :message="form.errors.team_ids" />
                </div>

                <template #footer>
                    <Button
                        type="submit"
                        data-test="project-save-button"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-muted-foreground text-sm"
                    >
                        Saved.
                    </p>
                </template>
            </SettingsCard>
        </form>

        <div class="flex flex-col gap-6">
            <SettingsCard
                title="Board"
                description="Share this link with the people you want feedback from"
            >
                <div
                    class="flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <code class="truncate text-sm">{{ boardUrl.url }}</code>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="boardUrl">
                            <ExternalLink class="h-4 w-4" /> Open board
                        </Link>
                    </Button>
                </div>
            </SettingsCard>

            <SettingsCard
                title="Delete project"
                description="Permanently delete this project and all the feedback on its board. This cannot be undone."
                destructive
            >
                <template #footer>
                    <Button
                        data-test="delete-project-button"
                        variant="destructive"
                        @click="deleteDialogOpen = true"
                    >
                        Delete project
                    </Button>
                </template>
            </SettingsCard>
        </div>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete project"
        confirm-label="Delete project"
        :href="destroy([currentOrganization.slug, project.slug])"
    >
        This will permanently delete
        <strong>{{ project.name }}</strong> and every feedback request, vote and
        comment on its board.
    </ConfirmDeleteModal>
</template>
