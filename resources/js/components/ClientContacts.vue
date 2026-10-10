<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { mutate } from '@/lib/optimistic';
import { destroy, store, update } from '@/routes/clients/contacts';

type Contact = { id: number; name: string | null; email: string };

defineProps<{
    organization: string;
    client: string;
    contacts: Contact[];
    canManage: boolean;
}>();

const editing = ref<number | null>(null);
</script>

<template>
    <div class="space-y-4">
        <ul v-if="contacts.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="contact in contacts"
                :key="contact.id"
                class="px-4 py-3"
                data-test="client-contact"
            >
                <Form
                    v-if="editing === contact.id"
                    v-bind="update.form([organization, client, contact.id])"
                    class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                    v-slot="{ errors, processing }"
                    @success="editing = null"
                >
                    <div class="grid gap-1">
                        <Input
                            name="name"
                            :default-value="contact.name ?? ''"
                            aria-label="Name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-1">
                        <Input
                            name="email"
                            type="email"
                            :default-value="contact.email"
                            aria-label="Email"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="flex gap-1">
                        <Button type="submit" size="sm" :disabled="processing">
                            Save
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="editing = null"
                        >
                            Cancel
                        </Button>
                    </div>
                </Form>
                <div
                    v-else
                    class="flex items-center justify-between gap-3 text-sm"
                >
                    <span class="min-w-0 truncate">
                        <span class="font-medium">
                            {{ contact.name ?? contact.email }}
                        </span>
                        <span class="text-muted-foreground">
                            · {{ contact.email }}
                        </span>
                    </span>
                    <span v-if="canManage" class="flex shrink-0 gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Edit ${contact.name ?? contact.email}`"
                            @click="editing = contact.id"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Remove ${contact.name ?? contact.email}`"
                            @click="
                                mutate(
                                    destroy([organization, client, contact.id]),
                                    {
                                        errorMessage:
                                            'The contact could not be removed.',
                                    },
                                )
                            "
                        >
                            <X />
                        </Button>
                    </span>
                </div>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            No contacts yet. People who email support from this client's domains
            are added here.
        </p>

        <Form
            v-if="canManage"
            v-bind="store.form([organization, client])"
            class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1">
                <Input
                    name="name"
                    placeholder="Name"
                    aria-label="Name"
                    data-test="client-contact-name"
                />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-1">
                <Input
                    name="email"
                    type="email"
                    placeholder="name@acme.com"
                    aria-label="Email"
                    data-test="client-contact-email"
                />
                <InputError :message="errors.email" />
            </div>
            <Button
                type="submit"
                variant="secondary"
                data-test="client-contact-add"
                :disabled="processing"
            >
                Add contact
            </Button>
        </Form>
    </div>
</template>
