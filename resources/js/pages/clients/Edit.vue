<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, edit, index, update } from '@/routes/clients';
import { show as showBoard } from '@/routes/portal/projects';
import type {
    Client,
    ClientProject,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    client: Client;
    projects: ClientProject[];
    permissions: OrganizationPermissions;
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization; client: Client }) => ({
        breadcrumbs: [
            {
                title: 'Clients',
                href: index(props.currentOrganization.slug),
            },
            {
                title: props.client.name,
                href: edit([props.currentOrganization.slug, props.client.slug]),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);
</script>

<template>
    <Head :title="client.name" />

    <div class="flex flex-col gap-10 p-6">
        <div class="max-w-xl space-y-6">
            <Heading variant="small" title="Client settings" />

            <Form
                v-if="permissions.canManageClients"
                v-bind="update.form([currentOrganization.slug, client.slug])"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Client name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="client-name-input"
                        :default-value="client.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button
                    type="submit"
                    data-test="client-save-button"
                    :disabled="processing"
                >
                    Save
                </Button>
            </Form>
            <p v-else class="font-medium">{{ client.name }}</p>
        </div>

        <div class="max-w-xl space-y-6">
            <Heading
                variant="small"
                title="Projects"
                description="Projects run for this client"
            />
            <div class="space-y-3">
                <div
                    v-for="project in projects"
                    :key="project.id"
                    class="flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <Link
                        :href="
                            showBoard([currentOrganization.slug, project.slug])
                        "
                        class="font-medium hover:underline"
                    >
                        {{ project.name }}
                    </Link>
                    <Badge variant="secondary">{{ project.visibility }}</Badge>
                </div>
                <p
                    v-if="projects.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    No projects are assigned to this client.
                </p>
            </div>
        </div>

        <div v-if="permissions.canManageClients" class="max-w-xl space-y-6">
            <Heading
                variant="small"
                title="Delete client"
                description="Projects for this client are kept and simply detached"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <Button
                    data-test="delete-client-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                >
                    Delete client
                </Button>
            </div>
        </div>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete client"
        confirm-label="Delete client"
        :href="destroy([currentOrganization.slug, client.slug])"
    >
        This will delete <strong>{{ client.name }}</strong
        >. Its projects are kept.
    </ConfirmDeleteModal>
</template>
