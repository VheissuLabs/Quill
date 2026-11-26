<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import EmailLink from '@/components/EmailLink.vue';
import PhoneLink from '@/components/PhoneLink.vue';
import { Button } from '@/components/ui/button';
import { mutate } from '@/lib/optimistic';
import { destroy, show } from '@/routes/clients/contacts';

type Contact = {
    id: number;
    name: string | null;
    title: string | null;
    email: string;
    phone: string | null;
};

defineProps<{
    organization: string;
    client: string;
    contacts: Contact[];
    canManage: boolean;
}>();
</script>

<template>
    <div class="space-y-4">
        <ul v-if="contacts.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="contact in contacts"
                :key="contact.id"
                class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                data-test="client-contact"
            >
                <span class="min-w-0 truncate">
                    <Link
                        :href="show([organization, client, contact.id])"
                        class="font-medium underline-offset-2 hover:underline"
                    >
                        {{ contact.name ?? contact.email }}
                    </Link>
                    <span v-if="contact.title" class="text-muted-foreground">
                        · {{ contact.title }}
                    </span>
                    <span class="text-muted-foreground">
                        · <EmailLink :email="contact.email" />
                    </span>
                    <span v-if="contact.phone" class="text-muted-foreground">
                        · <PhoneLink :phone="contact.phone" />
                    </span>
                </span>
                <Button
                    v-if="canManage"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Remove ${contact.name ?? contact.email}`"
                    @click="
                        mutate(destroy([organization, client, contact.id]), {
                            errorMessage: 'The contact could not be removed.',
                        })
                    "
                >
                    <X />
                </Button>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            No contacts yet. People who email support from this client's domains
            are added here.
        </p>
    </div>
</template>
