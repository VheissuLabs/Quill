<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Mail, Settings } from '@lucide/vue';
import ClientContacts from '@/components/ClientContacts.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/clients';
import { show as showBoard } from '@/routes/portal/projects';
import { show as showTicket } from '@/routes/tickets';
import type {
    Client,
    ClientProject,
    LinkedTicket,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    client: Client;
    projects: ClientProject[];
    tickets: LinkedTicket[];
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
                href: show([props.currentOrganization.slug, props.client.slug]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="client.name" />

    <div class="flex flex-col gap-10 p-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold" data-test="client-name">
                {{ client.name }}
            </h1>
            <Button variant="outline" size="sm" as-child>
                <Link
                    :href="edit([currentOrganization.slug, client.slug])"
                    data-test="client-settings-link"
                >
                    <Settings />
                    Settings
                </Link>
            </Button>
        </div>

        <div class="grid gap-10 lg:grid-cols-2">
            <section class="space-y-6">
                <Heading
                    variant="small"
                    title="Contacts"
                    description="People at this client you open tickets for"
                />
                <ClientContacts
                    :organization="currentOrganization.slug"
                    :client="client.slug"
                    :contacts="client.contacts ?? []"
                    :can-manage="permissions.canManageClients"
                />
            </section>

            <section class="space-y-6">
                <Heading
                    variant="small"
                    title="Projects"
                    description="Projects run for this client"
                />
                <ul
                    v-if="projects.length > 0"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="project in projects"
                        :key="project.id"
                        class="flex items-center justify-between gap-4 px-4 py-3"
                        data-test="client-project"
                    >
                        <Link
                            :href="
                                showBoard([
                                    currentOrganization.slug,
                                    project.slug,
                                ])
                            "
                            class="font-medium hover:underline"
                        >
                            {{ project.name }}
                        </Link>
                        <Badge variant="secondary">{{
                            project.visibility
                        }}</Badge>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">
                    No projects are assigned to this client.
                </p>
            </section>
        </div>

        <section v-if="tickets.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Recent tickets"
                description="The latest support conversations with this client"
            />
            <ul class="divide-y rounded-lg border">
                <li v-for="ticket in tickets" :key="ticket.id">
                    <Link
                        :href="
                            showTicket([currentOrganization.slug, ticket.id])
                        "
                        class="hover:bg-muted/50 flex items-center gap-3 px-4 py-3 text-sm"
                        data-test="client-ticket"
                    >
                        <Mail class="text-muted-foreground size-4 shrink-0" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">
                                {{ ticket.subject }}
                            </span>
                            <span class="text-muted-foreground block truncate">
                                {{ ticket.reference }} · {{ ticket.requester }}
                            </span>
                        </span>
                        <span
                            v-if="ticket.readyToReply"
                            class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                        >
                            <CircleCheck class="size-4" />
                            Ready to reply
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{ ticket.statusLabel }}
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
