<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Mail, Plus, Settings } from '@lucide/vue';
import { computed } from 'vue';
import ClientContacts from '@/components/ClientContacts.vue';
import CreateContactModal from '@/components/CreateContactModal.vue';
import CreateInvoiceModal from '@/components/CreateInvoiceModal.vue';
import CreateProjectModal from '@/components/CreateProjectModal.vue';
import Heading from '@/components/Heading.vue';
import InvoiceStatusBadge from '@/components/InvoiceStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/clients';
import { show as showInvoice } from '@/routes/invoices';
import { formatDate } from '@/lib/dates';
import { show as showBoard } from '@/routes/portal/projects';
import { show as showTicket } from '@/routes/tickets';
import type {
    Client,
    ClientProject,
    InvoiceListItem,
    LinkedTicket,
    Organization,
    OrganizationPermissions,
    SelectOption,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    client: Client;
    projects: ClientProject[];
    tickets: LinkedTicket[];
    invoices: InvoiceListItem[] | null;
    permissions: OrganizationPermissions;
    visibilities: SelectOption[];
};

const props = defineProps<Props>();

const invoiceProjects = computed(() =>
    props.projects.map((project) => ({
        id: project.id,
        name: project.name,
        billToClientId: props.client.id,
    })),
);

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));

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
            <Button size="sm" as-child>
                <Link
                    :href="edit([currentOrganization.slug, client.slug])"
                    data-test="client-settings-link"
                >
                    <Settings />
                    Settings
                </Link>
            </Button>
        </div>

        <div class="grid gap-10">
            <section class="space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Contacts"
                        description="People at this client you open tickets for"
                    />
                    <CreateContactModal
                        v-if="permissions.canManageClients"
                        :organization="currentOrganization.slug"
                        :client="client.slug"
                    >
                        <Button size="sm" data-test="client-new-contact-button">
                            <Plus /> Add contact
                        </Button>
                    </CreateContactModal>
                </div>
                <ClientContacts
                    :organization="currentOrganization.slug"
                    :client="client.slug"
                    :contacts="client.contacts ?? []"
                    :can-manage="permissions.canManageClients"
                />
            </section>

            <section class="space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Projects"
                        description="Projects run for this client"
                    />
                    <CreateProjectModal
                        v-if="permissions.canManageProjects"
                        :organization="currentOrganization.slug"
                        :visibilities="visibilities"
                        :client-id="client.id"
                    >
                        <Button size="sm" data-test="client-new-project-button">
                            <Plus /> New project
                        </Button>
                    </CreateProjectModal>
                </div>
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
        <section v-if="invoices !== null" class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <Heading variant="small" title="Invoices" />
                <CreateInvoiceModal
                    :organization="currentOrganization.slug"
                    :clients="[{ id: client.id, name: client.name }]"
                    :projects="invoiceProjects"
                    :client-id="client.id"
                >
                    <Button size="sm" data-test="client-new-invoice-button">
                        <Plus /> New invoice
                    </Button>
                </CreateInvoiceModal>
            </div>
            <ul v-if="invoices.length > 0" class="divide-y rounded-lg border">
                <li v-for="invoice in invoices" :key="invoice.id">
                    <Link
                        :href="
                            showInvoice([currentOrganization.slug, invoice.id])
                        "
                        class="hover:bg-muted/50 flex items-center gap-4 px-4 py-3 text-sm"
                        data-test="client-invoice"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">
                                {{ invoice.number }}
                                <Badge
                                    v-if="invoice.isCurrentPeriod"
                                    variant="secondary"
                                    class="ml-2"
                                    data-test="current-period-badge"
                                >
                                    Current period
                                </Badge>
                            </span>
                            <span class="text-muted-foreground block">
                                Issued {{ formatDate(invoice.issueDate) }} · Due
                                {{ formatDate(invoice.dueDate) }}
                            </span>
                        </span>
                        <span class="text-right tabular-nums">
                            <span class="block">{{
                                money(invoice.total)
                            }}</span>
                            <span
                                v-if="Number(invoice.amountDue) > 0"
                                class="text-muted-foreground block"
                            >
                                {{ money(invoice.amountDue) }} due
                            </span>
                        </span>
                        <InvoiceStatusBadge
                            :status="invoice.status"
                            :label="invoice.statusLabel"
                        />
                    </Link>
                </li>
            </ul>
            <p v-else class="text-muted-foreground text-sm">
                No invoices for this client yet.
            </p>
        </section>
    </div>
</template>
