<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import AddressList from '@/components/AddressList.vue';
import EmailLink from '@/components/EmailLink.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import InputError from '@/components/InputError.vue';
import PhoneLink from '@/components/PhoneLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show as showClient } from '@/routes/clients';
import { destroy, update } from '@/routes/clients/contacts';
import {
    destroy as destroyAddress,
    store as storeAddress,
    update as updateAddress,
} from '@/routes/clients/contacts/addresses';
import type {
    AddressTypeOption,
    ClientContactDetail,
    Organization,
    OrganizationPermissions,
} from '@/types';

type Props = {
    currentOrganization: Organization;
    client: { id: number; name: string; slug: string };
    contact: ClientContactDetail;
    addressTypes: AddressTypeOption[];
    permissions: OrganizationPermissions;
};

const props = defineProps<Props>();

const canManage = computed(() => props.permissions.canManageClients);

const contactArgs = (): [string, string, number] => [
    props.currentOrganization.slug,
    props.client.slug,
    props.contact.id,
];

const remove = () => {
    if (
        window.confirm(
            `Remove ${props.contact.name ?? props.contact.email} from ${props.client.name}?`,
        )
    ) {
        router.delete(destroy(contactArgs()));
    }
};

defineOptions({
    layout: (props: {
        currentOrganization: Organization;
        client: { name: string; slug: string };
        contact: ClientContactDetail;
    }) => ({
        breadcrumbs: [
            { title: 'Clients', href: index(props.currentOrganization.slug) },
            {
                title: props.client.name,
                href: showClient([
                    props.currentOrganization.slug,
                    props.client.slug,
                ]),
            },
            { title: props.contact.name ?? props.contact.email },
        ],
    }),
});
</script>

<template>
    <Head :title="contact.name ?? contact.email" />

    <div
        class="flex max-w-7xl flex-col gap-6 p-6"
        data-test="client-contact-page"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight">
                    {{ contact.name ?? contact.email }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    <template v-if="contact.title">
                        {{ contact.title }} ·
                    </template>
                    {{ client.name }}
                </p>
                <p class="text-muted-foreground mt-1 text-sm">
                    <EmailLink :email="contact.email" />
                    <template v-if="contact.phone">
                        · <PhoneLink :phone="contact.phone" />
                    </template>
                </p>
            </div>
            <Button
                v-if="canManage"
                variant="outline"
                size="sm"
                data-test="client-contact-delete"
                @click="remove"
            >
                <Trash2 /> Remove
            </Button>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <Form
                v-bind="update.form(contactArgs())"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <SettingsCard title="Details">
                    <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="contact-name">Name</Label>
                            <Input
                                id="contact-name"
                                name="name"
                                :default-value="contact.name ?? ''"
                                :disabled="!canManage"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-title">Title</Label>
                            <Input
                                id="contact-title"
                                name="title"
                                :default-value="contact.title ?? ''"
                                :disabled="!canManage"
                                placeholder="CTO"
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-email">Email</Label>
                            <Input
                                id="contact-email"
                                name="email"
                                type="email"
                                :default-value="contact.email"
                                :disabled="!canManage"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-phone">Phone</Label>
                            <Input
                                id="contact-phone"
                                name="phone"
                                type="tel"
                                :default-value="contact.phone ?? ''"
                                :disabled="!canManage"
                                placeholder="+1 555 123 4567"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>
                    <template v-if="canManage" #footer>
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="client-contact-save"
                        >
                            Save
                        </Button>
                        <span
                            v-if="recentlySuccessful"
                            class="text-muted-foreground text-sm"
                        >
                            Saved.
                        </span>
                    </template>
                </SettingsCard>
            </Form>

            <SettingsCard title="Addresses">
                <AddressList
                    :addresses="contact.addresses"
                    :types="addressTypes"
                    :can-manage="canManage"
                    :store="storeAddress.form(contactArgs())"
                    :update="(id) => updateAddress.form([...contactArgs(), id])"
                    :destroy="(id) => destroyAddress([...contactArgs(), id])"
                />
            </SettingsCard>
        </div>
    </div>
</template>
