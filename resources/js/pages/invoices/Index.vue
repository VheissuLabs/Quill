<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Receipt } from '@lucide/vue';
import CreateInvoiceModal from '@/components/CreateInvoiceModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InvoiceStatusBadge from '@/components/InvoiceStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showClient } from '@/routes/clients';
import { index, show } from '@/routes/invoices';
import type {
    InvoiceListItem,
    InvoiceProjectOption,
    Organization,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    invoices: InvoiceListItem[];
    filters: { status: string | null; client: number | null };
    statuses: { value: string; label: string }[];
    clients: { id: number; name: string }[];
    projects: InvoiceProjectOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentOrganization: Organization }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: index(props.currentOrganization.slug),
            },
        ],
    }),
});

const all = 'all';

const filter = (key: 'status' | 'client', value: unknown) => {
    const next: Record<string, string | number | null> = {
        status: props.filters.status,
        client: props.filters.client,
        [key]: value === all ? null : (value as string),
    };

    router.get(
        index(props.currentOrganization.slug, {
            query: Object.fromEntries(
                Object.entries(next).filter(([, v]) => v !== null),
            ),
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const money = (value: string) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
</script>

<template>
    <Head title="Invoices" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <Heading variant="small" title="Invoices" />

            <CreateInvoiceModal
                :organization="currentOrganization.slug"
                :clients="clients"
                :projects="projects"
            >
                <Button data-test="invoices-new-invoice-button">
                    <Plus /> New invoice
                </Button>
            </CreateInvoiceModal>
        </div>

        <div class="flex flex-wrap gap-3">
            <Select
                :model-value="filters.status ?? all"
                @update:model-value="filter('status', $event)"
            >
                <SelectTrigger class="w-44" data-test="invoice-status-filter">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.client ? String(filters.client) : all"
                @update:model-value="filter('client', $event)"
            >
                <SelectTrigger class="w-56" data-test="invoice-client-filter">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="all">All clients</SelectItem>
                    <SelectItem
                        v-for="client in clients"
                        :key="client.id"
                        :value="String(client.id)"
                    >
                        {{ client.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="invoices.length > 0"
            class="overflow-x-auto rounded-lg border"
        >
            <table class="w-full text-sm">
                <thead class="text-muted-foreground border-b text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Number</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Issued</th>
                        <th class="px-4 py-3 font-medium">Due</th>
                        <th class="px-4 py-3 text-right font-medium">Total</th>
                        <th class="px-4 py-3 text-right font-medium">Due</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        data-test="invoice-row"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    show([currentOrganization.slug, invoice.id])
                                "
                                class="font-medium hover:underline"
                                data-test="invoice-link"
                            >
                                {{ invoice.number }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    showClient([
                                        currentOrganization.slug,
                                        invoice.clientSlug,
                                    ])
                                "
                                class="hover:underline"
                                data-test="invoice-client-link"
                            >
                                {{ invoice.clientName }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ invoice.issueDate }}</td>
                        <td class="px-4 py-3">{{ invoice.dueDate }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(invoice.total) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(invoice.amountDue) }}
                        </td>
                        <td class="px-4 py-3">
                            <InvoiceStatusBadge
                                :status="invoice.status"
                                :label="invoice.statusLabel"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            :icon="Receipt"
            title="No invoices"
            description="Invoices you create will show up here."
        />
    </div>
</template>
