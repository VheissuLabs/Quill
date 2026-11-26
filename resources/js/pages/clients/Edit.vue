<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AddressList from '@/components/AddressList.vue';
import DatePicker from '@/components/DatePicker.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, edit, index, show, update } from '@/routes/clients';
import {
    destroy as destroyAddress,
    store as storeAddress,
    update as updateAddress,
} from '@/routes/clients/addresses';
import {
    destroy as destroyDomain,
    store as storeDomain,
} from '@/routes/clients/domains';
import { mutate } from '@/lib/optimistic';
import type {
    AddressTypeOption,
    Client,
    ClientBilling,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    client: Client;
    billing: ClientBilling | null;
    permissions: OrganizationPermissions;
    addressTypes: AddressTypeOption[];
};

const props = defineProps<Props>();

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
            {
                title: 'Settings',
                href: edit([props.currentOrganization.slug, props.client.slug]),
            },
        ],
    }),
});

const deleteDialogOpen = ref(false);

const none = 'none';

const form = useForm({
    name: props.client.name,
    billing_rate: props.billing?.rate ?? '',
    billing_contact_id: props.billing?.contactId
        ? String(props.billing.contactId)
        : none,
    billed_through_client_id: props.billing?.billedThroughClientId
        ? String(props.billing.billedThroughClientId)
        : none,
    billing_cycle: props.billing?.cycle ?? none,
    billing_cycle_starts_on: props.billing?.cycleStartsOn ?? '',
    billing_cc_emails: (props.billing?.ccEmails ?? []).join(', '),
    payment_terms_days: props.billing?.paymentTermsDays ?? '',
});

const cyclesEnabled = computed(() => props.billing?.cycles !== undefined);
const showsCycle = computed(
    () =>
        props.billing?.cycles !== undefined &&
        form.billed_through_client_id === none,
);
const hasCycle = computed(() => form.billing_cycle !== none);
const needsStart = computed(
    () => hasCycle.value && form.billing_cycle !== 'monthly',
);

watch(
    () => form.billed_through_client_id,
    (billedThrough) => {
        if (billedThrough !== none) {
            form.billing_cycle = none;
        }
    },
);

const formatDay = (day: string) =>
    new Date(`${day}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        ...(props.billing
            ? {
                  billing_rate: data.billing_rate,
                  billing_contact_id:
                      data.billing_contact_id === none
                          ? null
                          : Number(data.billing_contact_id),
                  billed_through_client_id:
                      data.billed_through_client_id === none
                          ? null
                          : Number(data.billed_through_client_id),
                  ...(cyclesEnabled.value
                      ? {
                            billing_cycle:
                                data.billing_cycle === none
                                    ? null
                                    : data.billing_cycle,
                            billing_cycle_starts_on: needsStart.value
                                ? data.billing_cycle_starts_on || null
                                : null,
                        }
                      : {}),
                  billing_cc_emails: data.billing_cc_emails
                      .split(',')
                      .map((email) => email.trim())
                      .filter((email) => email !== ''),
                  payment_terms_days:
                      data.payment_terms_days === ''
                          ? null
                          : Number(data.payment_terms_days),
              }
            : {}),
    })).submit(update([props.currentOrganization.slug, props.client.slug]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="client.name" />

    <div class="grid max-w-7xl items-start gap-6 p-6 lg:grid-cols-2">
        <form
            v-if="permissions.canManageClients"
            class="flex flex-col gap-6"
            data-test="client-settings-form"
            @submit.prevent="submit"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Client</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            data-test="client-name-input"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                </CardContent>
                <CardFooter v-if="!billing" class="justify-end">
                    <Button
                        type="submit"
                        data-test="client-save-button"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                </CardFooter>
            </Card>

            <Card v-if="billing">
                <CardHeader>
                    <CardTitle>Billing</CardTitle>
                    <CardDescription>
                        How and when this client is invoiced
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="billing-rate">Hourly rate</Label>
                        <Input
                            id="billing-rate"
                            v-model="form.billing_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Organization default"
                            data-test="client-billing-rate-input"
                        />
                        <InputError :message="form.errors.billing_rate" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment-terms">Payment terms (days)</Label>
                        <Input
                            id="payment-terms"
                            v-model="form.payment_terms_days"
                            type="number"
                            min="0"
                            max="365"
                            placeholder="Organization default"
                            data-test="client-payment-terms-input"
                        />
                        <InputError :message="form.errors.payment_terms_days" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="billing-contact">Billing contact</Label>
                        <Select
                            v-model="form.billing_contact_id"
                            data-test="client-billing-contact-select"
                        >
                            <SelectTrigger id="billing-contact" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none">None</SelectItem>
                                <SelectItem
                                    v-for="contact in billing.contacts"
                                    :key="contact.id"
                                    :value="String(contact.id)"
                                >
                                    {{ contact.name ?? contact.email }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.billing_contact_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="billed-through">Billed through</Label>
                        <Select
                            v-model="form.billed_through_client_id"
                            data-test="client-billed-through-select"
                        >
                            <SelectTrigger id="billed-through" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none">Nobody</SelectItem>
                                <SelectItem
                                    v-for="option in billing.billedThroughOptions"
                                    :key="option.id"
                                    :value="String(option.id)"
                                >
                                    {{ option.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="form.errors.billed_through_client_id"
                        />
                    </div>

                    <div v-if="showsCycle" class="grid gap-2">
                        <Label for="billing-cycle">Billing cycle</Label>
                        <Select
                            v-model="form.billing_cycle"
                            data-test="client-billing-cycle-select"
                        >
                            <SelectTrigger id="billing-cycle" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="none">
                                    None, invoice manually
                                </SelectItem>
                                <SelectItem
                                    v-for="cycle in billing.cycles"
                                    :key="cycle.value"
                                    :value="cycle.value"
                                >
                                    {{ cycle.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.billing_cycle" />
                    </div>

                    <div v-if="showsCycle && needsStart" class="grid gap-2">
                        <Label for="billing-cycle-starts-on">
                            Cycle starts on
                        </Label>
                        <DatePicker
                            id="billing-cycle-starts-on"
                            v-model="form.billing_cycle_starts_on"
                            data-test="client-billing-cycle-starts-on-input"
                        />
                        <InputError
                            :message="form.errors.billing_cycle_starts_on"
                        />
                    </div>

                    <p
                        v-if="showsCycle && hasCycle && billing.currentPeriod"
                        class="text-muted-foreground text-sm sm:col-span-2"
                        data-test="client-current-period"
                    >
                        Current period:
                        {{ formatDay(billing.currentPeriod.start) }} –
                        {{ formatDay(billing.currentPeriod.end) }}
                    </p>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="billing-cc">CC on invoices</Label>
                        <Input
                            id="billing-cc"
                            v-model="form.billing_cc_emails"
                            placeholder="ap@acme.com, finance@acme.com"
                            data-test="client-billing-cc-input"
                        />
                        <InputError :message="form.errors.billing_cc_emails" />
                    </div>
                </CardContent>
                <CardFooter class="justify-end">
                    <Button
                        type="submit"
                        data-test="client-save-button"
                        :disabled="form.processing"
                    >
                        Save
                    </Button>
                </CardFooter>
            </Card>
        </form>
        <Card v-else>
            <CardHeader>
                <CardTitle>{{ client.name }}</CardTitle>
            </CardHeader>
        </Card>

        <div class="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Addresses</CardTitle>
                    <CardDescription>
                        The Billing address prints under "Billed to" on invoices
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AddressList
                        :addresses="client.addresses ?? []"
                        :types="addressTypes"
                        :can-manage="permissions.canManageClients"
                        :store="
                            storeAddress.form([
                                currentOrganization.slug,
                                client.slug,
                            ])
                        "
                        :update="
                            (id) =>
                                updateAddress.form([
                                    currentOrganization.slug,
                                    client.slug,
                                    id,
                                ])
                        "
                        :destroy="
                            (id) =>
                                destroyAddress([
                                    currentOrganization.slug,
                                    client.slug,
                                    id,
                                ])
                        "
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Support domains</CardTitle>
                    <CardDescription>
                        Mail to support from these domains is filed under this
                        client
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <ul
                        v-if="client.domains?.length"
                        class="flex flex-wrap gap-2"
                    >
                        <li
                            v-for="domain in client.domains"
                            :key="domain.id"
                            class="inline-flex items-center gap-1 rounded-md border py-1 pr-1 pl-2.5 text-sm"
                            data-test="client-domain"
                        >
                            {{ domain.domain }}
                            <Button
                                v-if="permissions.canManageClients"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Remove ${domain.domain}`"
                                @click="
                                    mutate(
                                        destroyDomain([
                                            currentOrganization.slug,
                                            client.slug,
                                            domain.id,
                                        ]),
                                        {
                                            errorMessage:
                                                'The domain could not be removed.',
                                        },
                                    )
                                "
                            >
                                <X />
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground text-sm">
                        No domains yet. Mail from this client waits unassigned.
                    </p>
                    <Form
                        v-if="permissions.canManageClients"
                        v-bind="
                            storeDomain.form([
                                currentOrganization.slug,
                                client.slug,
                            ])
                        "
                        class="flex items-start gap-2"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid flex-1 gap-2">
                            <Input
                                name="domain"
                                placeholder="acme.com"
                                aria-label="Domain"
                                data-test="client-domain-input"
                            />
                            <InputError :message="errors.domain" />
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            data-test="client-domain-add"
                            :disabled="processing"
                        >
                            Add domain
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <Card
                v-if="permissions.canManageClients"
                class="border-red-200 dark:border-red-900/50"
            >
                <CardHeader>
                    <CardTitle>Delete client</CardTitle>
                    <CardDescription>
                        The client is hidden. Its invoices are kept for your
                        records and its projects are detached.
                    </CardDescription>
                </CardHeader>
                <CardFooter>
                    <Button
                        data-test="delete-client-button"
                        variant="destructive"
                        @click="deleteDialogOpen = true"
                    >
                        Delete client
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>

    <ConfirmDeleteModal
        v-model:open="deleteDialogOpen"
        title="Delete client"
        confirm-label="Delete client"
        :href="destroy([currentOrganization.slug, client.slug])"
    >
        This will delete <strong>{{ client.name }}</strong>. Its invoices are
        kept for your records, its projects are detached and its billing cycle
        stops.
    </ConfirmDeleteModal>
</template>
