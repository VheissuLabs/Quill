<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, edit, index, show, update } from '@/routes/clients';
import {
    destroy as destroyDomain,
    store as storeDomain,
} from '@/routes/clients/domains';
import { mutate } from '@/lib/optimistic';
import type { Client, Organization, OrganizationPermissions } from '@/types';

type Props = {
    currentOrganization: Organization;
    client: Client;
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
            {
                title: 'Settings',
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
                title="Support domains"
                description="Mail to support from these domains is filed under this client"
            />
            <ul v-if="client.domains?.length" class="flex flex-wrap gap-2">
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
                    storeDomain.form([currentOrganization.slug, client.slug])
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
