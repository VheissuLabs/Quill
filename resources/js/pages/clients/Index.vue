<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, Plus, Settings } from '@lucide/vue';
import CreateClientModal from '@/components/CreateClientModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index, show } from '@/routes/clients';
import type {
    ClientListItem,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    clients: ClientListItem[];
    permissions: OrganizationPermissions;
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Clients',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-col gap-8 p-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Clients"
                description="Customers whose projects you manage"
            />

            <CreateClientModal
                v-if="permissions.canManageClients"
                :organization="currentOrganization.slug"
            >
                <Button data-test="clients-new-client-button">
                    <Plus /> New client
                </Button>
            </CreateClientModal>
        </div>

        <div class="space-y-3">
            <div
                v-for="client in clients"
                :key="client.id"
                data-test="client-row"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <Link
                    :href="show([currentOrganization.slug, client.slug])"
                    class="group min-w-0 flex-1"
                    data-test="client-link"
                >
                    <div class="font-medium group-hover:underline">
                        {{ client.name }}
                    </div>
                    <span class="text-muted-foreground text-sm">
                        {{ client.projectsCount }}
                        {{
                            client.projectsCount === 1 ? 'project' : 'projects'
                        }}
                    </span>
                </Link>

                <TooltipProvider v-if="permissions.canManageClients">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                data-test="client-edit-button"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        edit([
                                            currentOrganization.slug,
                                            client.slug,
                                        ])
                                    "
                                >
                                    <Settings class="h-4 w-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Client settings</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <EmptyState
                v-if="clients.length === 0"
                :icon="Briefcase"
                title="No clients yet"
                :description="
                    permissions.canManageClients
                        ? 'Add the customers you run projects for, then assign projects to them.'
                        : 'Clients will show up here once an admin adds one.'
                "
            >
                <CreateClientModal
                    v-if="permissions.canManageClients"
                    :organization="currentOrganization.slug"
                >
                    <Button variant="outline"><Plus /> New client</Button>
                </CreateClientModal>
            </EmptyState>
        </div>
    </div>
</template>
